( function () {
	'use strict';

	if ( typeof asdevsQpmAdmin === 'undefined' || typeof wp === 'undefined' || ! wp.apiFetch ) {
		return;
	}

	const i18n = asdevsQpmAdmin.i18n;
	const perPage = asdevsQpmAdmin.perPage || 10;
	const batchSize = asdevsQpmAdmin.batchSize || 10;
	const defaultImage = asdevsQpmAdmin.defaultImage || '';

	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( asdevsQpmAdmin.restNonce ) );

	const els = {
		app: document.getElementById( 'asdevs-qpm-app' ),
		search: document.getElementById( 'asdevs-qpm-search' ),
		filterType: document.getElementById( 'asdevs-qpm-filter-type' ),
		filterCategory: document.getElementById( 'asdevs-qpm-filter-category' ),
		filterStock: document.getElementById( 'asdevs-qpm-filter-stock' ),
		filterBrand: document.getElementById( 'asdevs-qpm-filter-brand' ),
		filterPostStatus: document.getElementById( 'asdevs-qpm-filter-status' ),
		clearFilters: document.getElementById( 'asdevs-qpm-clear-filters' ),
		list: document.getElementById( 'asdevs-qpm-list' ),
		sentinel: document.getElementById( 'asdevs-qpm-scroll-sentinel' ),
		saveBtn: document.getElementById( 'asdevs-qpm-save' ),
		selectAll: document.getElementById( 'asdevs-qpm-select-all' ),
		bulkEditBtn: document.getElementById( 'asdevs-qpm-bulk-edit' ),
		selectedCount: document.getElementById( 'asdevs-qpm-selected-count' ),
		notice: document.getElementById( 'asdevs-qpm-notice' ),
		overlay: document.getElementById( 'asdevs-qpm-overlay' ),
		bulkOverlay: document.getElementById( 'asdevs-qpm-bulk-overlay' ),
		bulkCancel: document.getElementById( 'asdevs-qpm-bulk-cancel' ),
		bulkApply: document.getElementById( 'asdevs-qpm-bulk-apply' ),
		progressBar: document.getElementById( 'asdevs-qpm-progress-bar' ),
		progressText: document.getElementById( 'asdevs-qpm-progress-text' ),
	};

	const state = {
		page: 1,
		totalPages: 1,
		loading: false,
		saving: false,
	};

	const selection = {
		mode: 'none',
		ids: new Set(),
		itemsById: new Map(),
		total: 0,
		loading: false,
		syncing: false,
	};

	let searchDebounce = null;
	let observer = null;

	function apiPath( query ) {
		return 'asdevs-qpm/v1/products?' + new URLSearchParams( query ).toString();
	}

	function apiPathSelectable( query ) {
		return 'asdevs-qpm/v1/products/selectable?' + new URLSearchParams( query ).toString();
	}

	function filtersToQuery( filters ) {
		const query = {};
		Object.keys( filters ).forEach( function ( k ) {
			if ( filters[ k ] ) {
				query[ k ] = filters[ k ];
			}
		} );
		return query;
	}

	function clearSelection() {
		selection.mode = 'none';
		selection.ids.clear();
		selection.itemsById.clear();
		selection.total = 0;
	}

	function isRowSelected( id ) {
		return selection.mode === 'all-filtered' || selection.ids.has( id );
	}

	function getSelectionCount() {
		if ( selection.mode === 'all-filtered' && selection.total > 0 ) {
			return selection.total;
		}
		if ( selection.ids.size > 0 ) {
			return selection.ids.size;
		}
		return 0;
	}

	function hasSelection() {
		return selection.mode === 'all-filtered' ? selection.total > 0 : selection.ids.size > 0;
	}

	function syncSelectionToDom() {
		selection.syncing = true;
		els.list.querySelectorAll( '.asdevs-qpm-card--editable' ).forEach( function ( card ) {
			const id = parseInt( card.getAttribute( 'data-id' ), 10 );
			const cb = card.querySelector( '.asdevs-qpm-select-row' );
			if ( cb ) {
				cb.checked = isRowSelected( id );
			}
		} );
		selection.syncing = false;
	}

	function populateSelectionFromSelectable( data ) {
		clearSelection();
		if ( ! data.items || ! data.items.length ) {
			return;
		}
		selection.mode = 'all-filtered';
		selection.total = parseInt( data.total, 10 ) || data.items.length;
		data.items.forEach( function ( item ) {
			selection.ids.add( item.id );
			selection.itemsById.set( item.id, item );
		} );
	}

	function fetchAndSelectAllFiltered() {
		if ( selection.loading || state.saving ) {
			return;
		}
		selection.loading = true;
		if ( els.selectAll ) {
			els.selectAll.disabled = true;
		}
		showNotice( i18n.selectingAll || 'Selecting…', 'info' );

		wp.apiFetch( { path: apiPathSelectable( filtersToQuery( getFilters() ) ) } )
			.then( function ( data ) {
				if ( ! data.items || ! data.items.length ) {
					clearSelection();
					if ( els.selectAll ) {
						els.selectAll.checked = false;
					}
					syncSelectionToDom();
					return;
				}
				populateSelectionFromSelectable( data );
				syncSelectionToDom();
			} )
			.catch( function () {
				clearSelection();
				if ( els.selectAll ) {
					els.selectAll.checked = false;
				}
				syncSelectionToDom();
				showNotice( i18n.selectAllError || i18n.loadError, 'error' );
			} )
			.finally( function () {
				selection.loading = false;
				hideNotice();
				if ( els.selectAll ) {
					els.selectAll.disabled = false;
				}
				updateSelectionUI();
			} );
	}

	function onRowSelectChange( card, checked ) {
		if ( selection.syncing ) {
			return;
		}
		const id = parseInt( card.getAttribute( 'data-id' ), 10 );
		if ( ! id ) {
			return;
		}
		if ( selection.mode === 'none' && checked ) {
			selection.mode = 'manual';
		}
		if ( selection.mode === 'all-filtered' ) {
			selection.mode = 'manual';
		}
		if ( checked ) {
			selection.ids.add( id );
		} else {
			selection.ids.delete( id );
		}
		updateSelectionUI();
	}

	/**
	 * WP admin often scrolls #wpcontent instead of the window.
	 *
	 * @return {HTMLElement|null}
	 */
	function getAdminScrollRoot() {
		const wpcontent = document.getElementById( 'wpcontent' );
		if ( wpcontent && wpcontent.scrollHeight > wpcontent.clientHeight + 1 ) {
			return wpcontent;
		}
		return null;
	}

	function getFilters() {
		const filters = {
			search: els.search ? els.search.value.trim() : '',
			type: els.filterType ? els.filterType.value : '',
			category: els.filterCategory ? els.filterCategory.value : '',
			stock_status: els.filterStock ? els.filterStock.value : '',
			brand: els.filterBrand && ! els.filterBrand.disabled ? els.filterBrand.value : '',
			post_status: els.filterPostStatus ? els.filterPostStatus.value : '',
		};

		if ( filters.brand && asdevsQpmAdmin.brandTaxonomy ) {
			filters.brand_taxonomy = asdevsQpmAdmin.brandTaxonomy;
		}

		return filters;
	}

	function escAttr( val ) {
		if ( val === null || val === undefined ) {
			return '';
		}
		return String( val )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function formatPrice( val ) {
		if ( val === null || val === undefined || val === '' ) {
			return '';
		}
		return String( val );
	}

	function showNotice( message, type ) {
		if ( ! els.notice ) {
			return;
		}
		els.notice.textContent = message;
		els.notice.hidden = false;
		els.notice.className = 'asdevs-qpm-notice asdevs-qpm-notice--' + ( type || 'info' );
		if ( window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message );
		}
		maybeAutoLoadMore();
	}

	function hideNotice() {
		if ( els.notice ) {
			els.notice.hidden = true;
		}
		maybeAutoLoadMore();
	}

	function lockPage( locked ) {
		if ( els.app ) {
			els.app.classList.toggle( 'asdevs-qpm-app--locked', locked );
		}
		document.body.classList.toggle( 'asdevs-qpm-body-locked', locked );
	}

	function showProgressModal( percent ) {
		if ( els.overlay ) {
			els.overlay.hidden = false;
			updateProgress( percent );
		}
	}

	function hideProgressModal() {
		if ( els.overlay ) {
			els.overlay.hidden = true;
		}
	}

	function updateProgress( percent ) {
		const p = Math.min( 100, Math.max( 0, percent ) );
		if ( els.progressBar ) {
			els.progressBar.style.width = p + '%';
		}
		if ( els.progressText ) {
			const tpl = i18n.progressPercent || '%d%% complete';
			els.progressText.textContent = tpl.replace( '%d', String( p ) );
		}
	}

	function setSaveEnabled( enabled ) {
		if ( els.saveBtn ) {
			els.saveBtn.disabled = ! enabled || state.saving;
		}
	}

	function updateSaveState() {
		const dirty = els.list.querySelectorAll( '.asdevs-qpm-card--dirty' );
		setSaveEnabled( dirty.length > 0 );
	}

	function getBulkTargets() {
		const targets = [];
		const ids =
			selection.mode === 'all-filtered'
				? Array.from( selection.ids )
				: Array.from( selection.ids );

		ids.forEach( function ( id ) {
			const card = els.list.querySelector( '.asdevs-qpm-card[data-id="' + id + '"]' );
			const item = selection.itemsById.get( id );
			if ( card && card.dataset.readonly === '0' ) {
				targets.push( { card: card, item: item || null } );
			} else if ( item ) {
				targets.push( { card: null, item: item } );
			}
		} );
		return targets;
	}

	function updateSelectionUI() {
		const count = getSelectionCount();
		const selected = hasSelection();

		if ( els.selectedCount ) {
			if ( selected ) {
				els.selectedCount.hidden = false;
				els.selectedCount.textContent = ( i18n.selectedCount || '%d selected' ).replace(
					'%d',
					String( count )
				);
			} else {
				els.selectedCount.hidden = true;
			}
		}

		if ( els.bulkEditBtn ) {
			els.bulkEditBtn.disabled = ! selected || state.saving || selection.loading;
		}

		if ( ! els.selectAll ) {
			return;
		}

		if ( selection.loading ) {
			els.selectAll.indeterminate = true;
			return;
		}

		if ( selection.mode === 'all-filtered' && selection.total > 0 ) {
			els.selectAll.checked = true;
			els.selectAll.indeterminate = false;
			return;
		}

		const loadedIds = [];
		els.list.querySelectorAll( '.asdevs-qpm-card--editable' ).forEach( function ( card ) {
			loadedIds.push( parseInt( card.getAttribute( 'data-id' ), 10 ) );
		} );

		if ( selection.total > 0 && count === selection.total ) {
			els.selectAll.checked = true;
			els.selectAll.indeterminate = false;
			return;
		}

		if ( selected ) {
			const allLoadedSelected = loadedIds.length > 0 && loadedIds.every( isRowSelected );
			els.selectAll.checked = allLoadedSelected && count === loadedIds.length;
			els.selectAll.indeterminate = true;
			return;
		}

		els.selectAll.checked = false;
		els.selectAll.indeterminate = false;
	}

	function thumbHtml( item ) {
		const src = item.image_url || defaultImage;
		return (
			'<img class="asdevs-qpm-card__thumb" src="' +
			escAttr( src ) +
			'" alt="" width="44" height="44" loading="lazy" data-fallback="' +
			escAttr( defaultImage ) +
			'" onerror="if(this.dataset.fallback){this.src=this.dataset.fallback;delete this.dataset.fallback;}" />'
		);
	}

	function fieldInline( label, controlHtml, extraClass ) {
		let cls = 'asdevs-qpm-field-block asdevs-qpm-field-block--inline';
		if ( extraClass ) {
			cls += ' ' + extraClass;
		}
		if ( ! label ) {
			cls += ' asdevs-qpm-field-block--no-label';
		}
		const labelHtml = label
			? '<span class="asdevs-qpm-field-block__label">' + escAttr( label ) + '</span>'
			: '';
		return (
			'<div class="' +
			cls +
			'">' +
			labelHtml +
			'<div class="asdevs-qpm-field-block__control">' +
			controlHtml +
			'</div></div>'
		);
	}

	function cardClass( item ) {
		let cls = 'asdevs-qpm-card';
		if ( item.readonly ) {
			cls += ' asdevs-qpm-card--parent';
		} else {
			cls += ' asdevs-qpm-card--editable';
		}
		if ( item.row_type === 'variation' ) {
			cls += ' asdevs-qpm-card--variation';
		}
		return cls;
	}

	function buildStatusBadge( item ) {
		if ( ! item.post_status_label ) {
			return '';
		}
		const slug = item.post_status || 'unknown';
		return (
			'<span class="asdevs-qpm-card__status asdevs-qpm-card__status--' +
			escAttr( slug ) +
			'">' +
			escAttr( item.post_status_label ) +
			'</span>'
		);
	}

	function buildProductInfo( item ) {
		const title = item.edit_url
			? '<a href="' + escAttr( item.edit_url ) + '" class="asdevs-qpm-card__title">' + escAttr( item.title ) + '</a>'
			: '<span class="asdevs-qpm-card__title-text">' + escAttr( item.title ) + '</span>';
		return (
			'<div class="asdevs-qpm-card__product">' +
			thumbHtml( item ) +
			'<div class="asdevs-qpm-card__meta">' +
			'<div class="asdevs-qpm-card__tags">' +
			'<span class="asdevs-qpm-card__id">#' +
			item.id +
			'</span>' +
			buildStatusBadge( item ) +
			'</div>' +
			title +
			( item.row_type === 'parent'
				? '<span class="asdevs-qpm-card__badge">' + escAttr( item.product_type ) + '</span>'
				: '' ) +
			( item.sku ? '<span class="asdevs-qpm-card__sku">SKU: ' + escAttr( item.sku ) + '</span>' : '' ) +
			'</div></div>'
		);
	}

	function buildParentCard( item ) {
		return (
			'<article class="' +
			cardClass( item ) +
			'" data-id="' +
			item.id +
			'" data-readonly="1">' +
			'<div class="asdevs-qpm-card__row">' +
			buildProductInfo( item ) +
			'<span class="asdevs-qpm-readonly-note">—</span>' +
			'</div></article>'
		);
	}

	function buildEditableCard( item ) {
		const saleFrom = item.date_on_sale_from || '';
		const saleTo = item.date_on_sale_to || '';
		const hasSale = item.sale_price !== '' && item.sale_price !== null;
		const scheduleHidden = hasSale ? '' : ' asdevs-qpm-schedule--hidden';
		const stockQty = item.stock_quantity !== null ? item.stock_quantity : '';
		const manageChecked = item.manage_stock ? ' checked' : '';
		const qtyDisabled = item.manage_stock ? '' : ' disabled';

		const stockInput =
			'<input type="number" class="asdevs-qpm-mdc-input asdevs-qpm-field-stock" min="0" step="1" value="' +
			escAttr( stockQty ) +
			'" data-original="' +
			escAttr( stockQty ) +
			'"' +
			qtyDisabled +
			' />';
		const manageInput =
			'<input type="checkbox" class="asdevs-qpm-field-manage-stock"' +
			manageChecked +
			' data-original="' +
			( item.manage_stock ? '1' : '0' ) +
			'" />';
		const regularInput =
			'<input type="text" class="asdevs-qpm-mdc-input asdevs-qpm-field-regular" inputmode="decimal" value="' +
			escAttr( formatPrice( item.regular_price ) ) +
			'" data-original="' +
			escAttr( formatPrice( item.regular_price ) ) +
			'" />';
		const saleInput =
			'<input type="text" class="asdevs-qpm-mdc-input asdevs-qpm-field-sale" inputmode="decimal" value="' +
			escAttr( formatPrice( item.sale_price ) ) +
			'" data-original="' +
			escAttr( formatPrice( item.sale_price ) ) +
			'" />';
		const scheduleInput =
			'<div class="asdevs-qpm-schedule' +
			scheduleHidden +
			'"><input type="date" class="asdevs-qpm-mdc-input asdevs-qpm-field-sale-from" value="' +
			escAttr( saleFrom ) +
			'" data-original="' +
			escAttr( saleFrom ) +
			'" /><input type="date" class="asdevs-qpm-mdc-input asdevs-qpm-field-sale-to" value="' +
			escAttr( saleTo ) +
			'" data-original="' +
			escAttr( saleTo ) +
			'" /></div>';

		return (
			'<article class="' +
			cardClass( item ) +
			'" data-id="' +
			item.id +
			'" data-readonly="0">' +
			'<div class="asdevs-qpm-card__select"><input type="checkbox" class="asdevs-qpm-select-row" aria-label="Select" /></div>' +
			'<div class="asdevs-qpm-card__row">' +
			buildProductInfo( item ) +
			fieldInline( i18n.manageStock, manageInput, 'asdevs-qpm-field-block--manage' ) +
			fieldInline( i18n.quantity || 'Quantity', stockInput, 'asdevs-qpm-field-block--stock' ) +
			fieldInline( i18n.regularPrice || 'Regular', regularInput, 'asdevs-qpm-field-block--regular' ) +
			fieldInline( i18n.salePrice || 'Sale', saleInput, 'asdevs-qpm-field-block--sale' ) +
			fieldInline( '', scheduleInput, 'asdevs-qpm-field-block--schedule' ) +
			'</div></article>'
		);
	}

	function renderCard( item ) {
		return item.readonly ? buildParentCard( item ) : buildEditableCard( item );
	}

	function setListState( message ) {
		els.list.innerHTML = '<div class="asdevs-qpm-list__state">' + escAttr( message ) + '</div>';
	}

	function markDirty( card ) {
		card.classList.add( 'asdevs-qpm-card--dirty' );
		updateSaveState();
	}

	function checkCardDirty( card ) {
		let dirty = false;
		card.querySelectorAll( '[data-original]' ).forEach( function ( el ) {
			const current = el.type === 'checkbox' ? ( el.checked ? '1' : '0' ) : el.value;
			if ( current !== el.getAttribute( 'data-original' ) ) {
				dirty = true;
			}
		} );
		if ( dirty ) {
			markDirty( card );
		} else {
			card.classList.remove( 'asdevs-qpm-card--dirty' );
			updateSaveState();
		}
	}

	function toggleSchedule( card ) {
		const saleInput = card.querySelector( '.asdevs-qpm-field-sale' );
		const schedule = card.querySelector( '.asdevs-qpm-schedule' );
		if ( saleInput && schedule ) {
			schedule.classList.toggle( 'asdevs-qpm-schedule--hidden', saleInput.value.trim() === '' );
		}
	}

	function bindCardEvents( card ) {
		if ( card.dataset.asdevsQpmBound === '1' ) {
			return;
		}
		card.dataset.asdevsQpmBound = '1';

		if ( card.dataset.readonly === '0' ) {
			card.addEventListener( 'input', function ( e ) {
				const t = e.target;
				if ( t.classList.contains( 'asdevs-qpm-field-manage-stock' ) ) {
					const qty = card.querySelector( '.asdevs-qpm-field-stock' );
					if ( qty ) {
						qty.disabled = ! t.checked;
					}
				}
				if ( t.classList.contains( 'asdevs-qpm-field-sale' ) ) {
					toggleSchedule( card );
				}
				checkCardDirty( card );
			} );
			card.addEventListener( 'change', function ( e ) {
				if ( e.target.classList.contains( 'asdevs-qpm-select-row' ) ) {
					onRowSelectChange( card, e.target.checked );
					return;
				}
				checkCardDirty( card );
			} );
		}
	}

	function loadProducts( reset ) {
		if ( state.loading || state.saving ) {
			return;
		}
		if ( reset ) {
			state.page = 1;
			state.totalPages = 1;
			setListState( i18n.loading );
			clearSelection();
			if ( els.selectAll ) {
				els.selectAll.checked = false;
				els.selectAll.indeterminate = false;
			}
			updateSelectionUI();
		}

		state.loading = true;
		const query = Object.assign( { page: state.page, per_page: perPage }, filtersToQuery( getFilters() ) );

		wp.apiFetch( { path: apiPath( query ) } )
			.then( function ( data ) {
				state.totalPages = Math.max( 1, parseInt( data.total_pages, 10 ) || 1 );
				if ( reset ) {
					els.list.innerHTML = '';
				}
				if ( ! data.items || ! data.items.length ) {
					if ( reset ) {
						setListState( i18n.noResults );
					}
					return;
				}
				const html = data.items.map( renderCard ).join( '' );
				if ( reset ) {
					els.list.innerHTML = html;
				} else {
					els.list.insertAdjacentHTML( 'beforeend', html );
				}
				els.list.querySelectorAll( '.asdevs-qpm-card' ).forEach( bindCardEvents );
				syncSelectionToDom();
				updateSelectionUI();
				updateSaveState();
			} )
			.catch( function () {
				if ( reset ) {
					setListState( i18n.loadError );
				}
				showNotice( i18n.loadError, 'error' );
			} )
			.finally( function () {
				state.loading = false;
				setTimeout( maybeAutoLoadMore, 80 );
			} );
	}

	function maybeAutoLoadMore() {
		if ( state.loading || state.saving || selection.loading || state.page >= state.totalPages ) {
			return;
		}
		const root = getAdminScrollRoot();
		const scrollHeight = root ? root.scrollHeight : document.documentElement.scrollHeight;
		const clientHeight = root ? root.clientHeight : window.innerHeight;
		if ( scrollHeight <= clientHeight + 80 ) {
			loadNextPage();
		}
	}

	function loadNextPage() {
		if ( state.loading || state.saving || state.page >= state.totalPages ) {
			return;
		}
		state.page += 1;
		loadProducts( false );
	}

	function collectChangesFromCard( card ) {
		const id = parseInt( card.getAttribute( 'data-id' ), 10 );
		const change = { id: id };
		const sku = card.querySelector( '.asdevs-qpm-field-sku' );
		if ( sku && sku.value !== sku.getAttribute( 'data-original' ) ) {
			change.sku = sku.value;
		}
		const manage = card.querySelector( '.asdevs-qpm-field-manage-stock' );
		if ( manage ) {
			const cur = manage.checked ? '1' : '0';
			if ( cur !== manage.getAttribute( 'data-original' ) ) {
				change.manage_stock = manage.checked;
			}
		}
		const stock = card.querySelector( '.asdevs-qpm-field-stock' );
		if ( stock && stock.value !== stock.getAttribute( 'data-original' ) ) {
			change.stock_quantity = stock.value;
		}
		const regular = card.querySelector( '.asdevs-qpm-field-regular' );
		if ( regular && regular.value !== regular.getAttribute( 'data-original' ) ) {
			change.regular_price = regular.value;
		}
		const sale = card.querySelector( '.asdevs-qpm-field-sale' );
		if ( sale && sale.value !== sale.getAttribute( 'data-original' ) ) {
			change.sale_price = sale.value;
		}
		const saleFrom = card.querySelector( '.asdevs-qpm-field-sale-from' );
		if ( saleFrom && saleFrom.value !== saleFrom.getAttribute( 'data-original' ) ) {
			change.date_on_sale_from = saleFrom.value;
		}
		const saleTo = card.querySelector( '.asdevs-qpm-field-sale-to' );
		if ( saleTo && saleTo.value !== saleTo.getAttribute( 'data-original' ) ) {
			change.date_on_sale_to = saleTo.value;
		}
		return Object.keys( change ).length > 1 ? change : null;
	}

	function collectAllChanges() {
		const changes = [];
		els.list.querySelectorAll( '.asdevs-qpm-card--dirty' ).forEach( function ( card ) {
			const c = collectChangesFromCard( card );
			if ( c ) {
				changes.push( c );
			}
		} );
		return changes;
	}

	function syncCardOriginals( card ) {
		card.querySelectorAll( '[data-original]' ).forEach( function ( el ) {
			if ( el.type === 'checkbox' ) {
				el.setAttribute( 'data-original', el.checked ? '1' : '0' );
			} else {
				el.setAttribute( 'data-original', el.value );
			}
		} );
		card.classList.remove( 'asdevs-qpm-card--dirty' );
	}

	function adjustPrice( current, mode, direction, value ) {
		const cur = parseFloat( current ) || 0;
		const val = parseFloat( value ) || 0;
		let next;
		if ( mode === 'percent' ) {
			const delta = cur * ( val / 100 );
			next = direction === 'increase' ? cur + delta : cur - delta;
		} else {
			next = direction === 'increase' ? cur + val : cur - val;
		}
		next = Math.max( 0, next );
		const dec = ( String( next ).split( '.' )[ 1 ] || '' ).length;
		return dec > 2 ? next.toFixed( 2 ) : String( next );
	}

	function adjustStock( current, mode, direction, value ) {
		const cur = parseInt( current, 10 ) || 0;
		const val = parseInt( value, 10 ) || 0;
		if ( mode === 'set' ) {
			return String( val );
		}
		const next = direction === 'increase' ? cur + val : Math.max( 0, cur - val );
		return String( next );
	}

	function setFieldValue( card, selector, value ) {
		const el = card.querySelector( selector );
		if ( el ) {
			el.value = value;
			if ( el.type === 'checkbox' ) {
				el.checked = value === '1' || value === true;
			}
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}
	}

	function applyBulkToItemData( item, rules ) {
		const updated = Object.assign( {}, item );

		if ( rules.stock && rules.stock.enabled ) {
			updated.stock_quantity = adjustStock(
				String( item.stock_quantity != null ? item.stock_quantity : '' ),
				rules.stock.mode,
				rules.stock.direction,
				rules.stock.value
			);
		}
		if ( rules.manage_stock && rules.manage_stock.enabled ) {
			updated.manage_stock = rules.manage_stock.value === '1';
		}
		if ( rules.regular_price && rules.regular_price.enabled ) {
			updated.regular_price = adjustPrice(
				String( item.regular_price != null ? item.regular_price : '' ),
				rules.regular_price.mode,
				rules.regular_price.direction,
				rules.regular_price.value
			);
		}
		if ( rules.sale_price && rules.sale_price.enabled ) {
			updated.sale_price = adjustPrice(
				String( item.sale_price != null ? item.sale_price : '' ),
				rules.sale_price.mode,
				rules.sale_price.direction,
				rules.sale_price.value
			);
		}
		if ( rules.sale_from && rules.sale_from.enabled ) {
			updated.date_on_sale_from = rules.sale_from.value;
		}
		if ( rules.sale_to && rules.sale_to.enabled ) {
			updated.date_on_sale_to = rules.sale_to.value;
		}

		return updated;
	}

	function collectChangesFromItemData( original, updated ) {
		const change = { id: original.id };

		if ( updated.sku !== original.sku ) {
			change.sku = updated.sku;
		}
		const origManage = original.manage_stock ? '1' : '0';
		const newManage = updated.manage_stock ? '1' : '0';
		if ( newManage !== origManage ) {
			change.manage_stock = updated.manage_stock;
		}
		if ( String( updated.stock_quantity ) !== String( original.stock_quantity ) ) {
			change.stock_quantity = updated.stock_quantity;
		}
		if ( String( updated.regular_price ) !== String( original.regular_price ) ) {
			change.regular_price = updated.regular_price;
		}
		if ( String( updated.sale_price ) !== String( original.sale_price ) ) {
			change.sale_price = updated.sale_price;
		}
		if ( updated.date_on_sale_from !== original.date_on_sale_from ) {
			change.date_on_sale_from = updated.date_on_sale_from;
		}
		if ( updated.date_on_sale_to !== original.date_on_sale_to ) {
			change.date_on_sale_to = updated.date_on_sale_to;
		}

		return Object.keys( change ).length > 1 ? change : null;
	}

	function applyBulkToCard( card, rules ) {
		if ( rules.stock && rules.stock.enabled ) {
			const el = card.querySelector( '.asdevs-qpm-field-stock' );
			if ( el ) {
				const next = adjustStock( el.value, rules.stock.mode, rules.stock.direction, rules.stock.value );
				setFieldValue( card, '.asdevs-qpm-field-stock', next );
			}
		}
		if ( rules.manage_stock && rules.manage_stock.enabled ) {
			setFieldValue( card, '.asdevs-qpm-field-manage-stock', rules.manage_stock.value );
			const qty = card.querySelector( '.asdevs-qpm-field-stock' );
			if ( qty ) {
				qty.disabled = rules.manage_stock.value !== '1';
			}
		}
		if ( rules.regular_price && rules.regular_price.enabled ) {
			const el = card.querySelector( '.asdevs-qpm-field-regular' );
			if ( el ) {
				const next = adjustPrice( el.value, rules.regular_price.mode, rules.regular_price.direction, rules.regular_price.value );
				setFieldValue( card, '.asdevs-qpm-field-regular', next );
			}
		}
		if ( rules.sale_price && rules.sale_price.enabled ) {
			const el = card.querySelector( '.asdevs-qpm-field-sale' );
			if ( el ) {
				const next = adjustPrice( el.value, rules.sale_price.mode, rules.sale_price.direction, rules.sale_price.value );
				setFieldValue( card, '.asdevs-qpm-field-sale', next );
				toggleSchedule( card );
			}
		}
		if ( rules.sale_from && rules.sale_from.enabled ) {
			setFieldValue( card, '.asdevs-qpm-field-sale-from', rules.sale_from.value );
			const sched = card.querySelector( '.asdevs-qpm-schedule' );
			if ( sched ) {
				sched.classList.remove( 'asdevs-qpm-schedule--hidden' );
			}
		}
		if ( rules.sale_to && rules.sale_to.enabled ) {
			setFieldValue( card, '.asdevs-qpm-field-sale-to', rules.sale_to.value );
		}
		checkCardDirty( card );
	}

	function getBulkRules() {
		const rules = {};
		let any = false;

		document.querySelectorAll( '.asdevs-qpm-bulk-enable' ).forEach( function ( sw ) {
			const field = sw.dataset.field;
			if ( ! sw.checked ) {
				return;
			}
			any = true;
			const row = sw.closest( '.asdevs-qpm-bulk-row' );

			if ( field === 'regular_price' || field === 'sale_price' ) {
				rules[ field ] = {
					enabled: true,
					mode: row.querySelector( '.asdevs-qpm-bulk-mode' ).value,
					direction: row.querySelector( '.asdevs-qpm-bulk-direction' ).value,
					value: row.querySelector( '.asdevs-qpm-bulk-value' ).value,
				};
			} else if ( field === 'stock' ) {
				rules.stock = {
					enabled: true,
					mode: 'set',
					value: row.querySelector( '.asdevs-qpm-bulk-value' ).value,
				};
			} else if ( field === 'manage_stock' ) {
				rules.manage_stock = {
					enabled: true,
					value: row.querySelector( '.asdevs-qpm-bulk-manage-value' ).value,
				};
			} else if ( field === 'sale_from' || field === 'sale_to' ) {
				rules[ field ] = {
					enabled: true,
					value: row.querySelector( '.asdevs-qpm-bulk-date' ).value,
				};
			}
		} );

		return any ? rules : null;
	}

	function openBulkModal() {
		if ( els.bulkOverlay ) {
			els.bulkOverlay.hidden = false;
		}
	}

	function closeBulkModal() {
		if ( els.bulkOverlay ) {
			els.bulkOverlay.hidden = true;
		}
	}

	function chunkArray( arr, size ) {
		const chunks = [];
		for ( let i = 0; i < arr.length; i += size ) {
			chunks.push( arr.slice( i, i + size ) );
		}
		return chunks;
	}

	function saveBatch( batch ) {
		return wp.apiFetch( {
			path: 'asdevs-qpm/v1/products/batch',
			method: 'POST',
			data: { changes: batch },
		} );
	}

	function runBatchSave( changes, onDone ) {
		if ( ! changes.length ) {
			showNotice( i18n.noChanges, 'error' );
			return;
		}

		state.saving = true;
		setSaveEnabled( false );
		if ( els.bulkEditBtn ) {
			els.bulkEditBtn.disabled = true;
		}
		hideNotice();
		lockPage( true );
		showProgressModal( 0 );
		if ( els.saveBtn ) {
			els.saveBtn.textContent = i18n.saving;
		}

		const batches = chunkArray( changes, batchSize );
		const total = changes.length;
		let processed = 0;
		const allUpdated = [];
		const allFailed = [];

		function runNext( index ) {
			if ( index >= batches.length ) {
				let msg = i18n.saved;
				if ( allUpdated.length ) {
					msg = i18n.savedCount.replace( '%d', String( allUpdated.length ) );
				}
				if ( allFailed.length ) {
					msg += ' ' + i18n.failedCount.replace( '%d', String( allFailed.length ) );
					showNotice( msg, allFailed.length && ! allUpdated.length ? 'error' : 'success' );
				} else {
					showNotice( msg, 'success' );
				}
				allUpdated.forEach( function ( id ) {
					const card = els.list.querySelector( '.asdevs-qpm-card[data-id="' + id + '"]' );
					if ( card ) {
						syncCardOriginals( card );
					}
				} );
				allFailed.forEach( function ( f ) {
					const card = els.list.querySelector( '.asdevs-qpm-card[data-id="' + f.id + '"]' );
					if ( card ) {
						markDirty( card );
					}
				} );
				updateProgress( 100 );
				setTimeout( function () {
					hideProgressModal();
					lockPage( false );
					state.saving = false;
					if ( els.saveBtn ) {
						els.saveBtn.textContent = i18n.saveChanges;
					}
					updateSaveState();
					updateSelectionUI();
					if ( onDone ) {
						onDone();
					}
				}, 400 );
				return;
			}

			saveBatch( batches[ index ] )
				.then( function ( result ) {
					( result.updated || [] ).forEach( function ( id ) {
						allUpdated.push( id );
					} );
					( result.failed || [] ).forEach( function ( f ) {
						allFailed.push( f );
					} );
				} )
				.catch( function () {
					batches[ index ].forEach( function ( c ) {
						allFailed.push( { id: c.id, message: i18n.saveError } );
					} );
				} )
				.finally( function () {
					processed += batches[ index ].length;
					updateProgress( Math.round( ( processed / total ) * 100 ) );
					runNext( index + 1 );
				} );
		}

		runNext( 0 );
	}

	function saveChanges() {
		runBatchSave( collectAllChanges() );
	}

	function confirmBulkEdit() {
		const targets = getBulkTargets();
		if ( ! targets.length ) {
			showNotice( i18n.noSelection, 'error' );
			return;
		}
		const rules = getBulkRules();
		if ( ! rules ) {
			showNotice( i18n.noBulkFields, 'error' );
			return;
		}

		const changes = [];
		targets.forEach( function ( target ) {
			if ( target.card ) {
				applyBulkToCard( target.card, rules );
				if ( target.card.classList.contains( 'asdevs-qpm-card--dirty' ) ) {
					const c = collectChangesFromCard( target.card );
					if ( c ) {
						changes.push( c );
					}
				}
			} else if ( target.item ) {
				const updated = applyBulkToItemData( target.item, rules );
				const c = collectChangesFromItemData( target.item, updated );
				if ( c ) {
					changes.push( c );
				}
			}
		} );

		closeBulkModal();
		runBatchSave( changes, function () {
			clearSelection();
			syncSelectionToDom();
			updateSelectionUI();
		} );
	}

	function initBulkForm() {
		document.querySelectorAll( '.asdevs-qpm-bulk-enable' ).forEach( function ( sw ) {
			sw.addEventListener( 'change', function () {
				const row = sw.closest( '.asdevs-qpm-bulk-row' );
				const controls = row.querySelector( '.asdevs-qpm-bulk-row__controls' );
				if ( controls ) {
					controls.hidden = ! sw.checked;
				}
			} );
		} );
	}

	function onPageScroll() {
		if ( state.saving || state.loading || state.page >= state.totalPages ) {
			return;
		}
		const root = getAdminScrollRoot();
		if ( root ) {
			if ( root.scrollTop + root.clientHeight >= root.scrollHeight - 200 ) {
				loadNextPage();
			}
			return;
		}
		const scrollBottom = window.innerHeight + window.scrollY;
		if ( scrollBottom >= document.documentElement.scrollHeight - 200 ) {
			loadNextPage();
		}
	}

	function initScrollObserver() {
		if ( ! els.sentinel ) {
			return;
		}

		const scrollRoot = getAdminScrollRoot();

		if ( window.IntersectionObserver ) {
			observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting && ! state.saving && ! state.loading ) {
							loadNextPage();
						}
					} );
				},
				{ root: scrollRoot, rootMargin: '200px', threshold: 0 }
			);
			observer.observe( els.sentinel );
		}

		( scrollRoot || window ).addEventListener( 'scroll', onPageScroll, { passive: true } );
	}

	function resetAndLoad() {
		if ( state.saving ) {
			return;
		}
		hideNotice();
		loadProducts( true );
	}

	function clearFilters() {
		if ( state.saving ) {
			return;
		}
		if ( els.search ) {
			els.search.value = '';
		}
		[ els.filterType, els.filterCategory, els.filterStock, els.filterPostStatus, els.filterBrand ].forEach(
			function ( el ) {
				if ( el ) {
					el.value = '';
				}
			}
		);
		resetAndLoad();
	}

	function init() {
		initBulkForm();

		if ( els.search ) {
			els.search.addEventListener( 'input', function () {
				clearTimeout( searchDebounce );
				searchDebounce = setTimeout( resetAndLoad, 300 );
			} );
		}
		[ els.filterType, els.filterCategory, els.filterStock, els.filterPostStatus, els.filterBrand ].forEach(
			function ( el ) {
				if ( el ) {
					el.addEventListener( 'change', resetAndLoad );
				}
			}
		);
		if ( els.selectAll && i18n.selectAllTooltip ) {
			els.selectAll.title = i18n.selectAllTooltip;
		}
		if ( els.clearFilters ) {
			els.clearFilters.addEventListener( 'click', clearFilters );
		}
		if ( els.saveBtn ) {
			els.saveBtn.addEventListener( 'click', saveChanges );
		}
		if ( els.selectAll ) {
			els.selectAll.addEventListener( 'change', function () {
				if ( els.selectAll.checked ) {
					fetchAndSelectAllFiltered();
				} else {
					clearSelection();
					syncSelectionToDom();
					updateSelectionUI();
				}
			} );
		}
		if ( els.bulkEditBtn ) {
			els.bulkEditBtn.addEventListener( 'click', openBulkModal );
		}
		if ( els.bulkCancel ) {
			els.bulkCancel.addEventListener( 'click', closeBulkModal );
		}
		if ( els.bulkApply ) {
			els.bulkApply.addEventListener( 'click', confirmBulkEdit );
		}
		if ( els.bulkOverlay ) {
			els.bulkOverlay.addEventListener( 'click', function ( e ) {
				if ( e.target === els.bulkOverlay ) {
					closeBulkModal();
				}
			} );
		}

		initScrollObserver();
		loadProducts( true );
		window.addEventListener( 'resize', maybeAutoLoadMore );
		if ( window.ResizeObserver && els.notice ) {
			new ResizeObserver( maybeAutoLoadMore ).observe( els.notice );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
