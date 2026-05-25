( function () {
	'use strict';

	if ( typeof qpmAdmin === 'undefined' || typeof wp === 'undefined' || ! wp.apiFetch ) {
		return;
	}

	const i18n = qpmAdmin.i18n;
	const perPage = qpmAdmin.perPage || 30;

	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( qpmAdmin.restNonce ) );

	const els = {
		search: document.getElementById( 'qpm-search' ),
		filterType: document.getElementById( 'qpm-filter-type' ),
		filterCategory: document.getElementById( 'qpm-filter-category' ),
		filterTag: document.getElementById( 'qpm-filter-tag' ),
		filterStock: document.getElementById( 'qpm-filter-stock' ),
		filterVisibility: document.getElementById( 'qpm-filter-visibility' ),
		clearFilters: document.getElementById( 'qpm-clear-filters' ),
		tbody: document.getElementById( 'qpm-tbody' ),
		scrollWrap: document.getElementById( 'qpm-table-scroll' ),
		sentinel: document.getElementById( 'qpm-scroll-sentinel' ),
		saveTop: document.getElementById( 'qpm-save-top' ),
		saveBottom: document.getElementById( 'qpm-save-bottom' ),
		notice: document.getElementById( 'qpm-notice' ),
	};

	const state = {
		page: 1,
		totalPages: 1,
		loading: false,
		saving: false,
	};

	let searchDebounce = null;
	let observer = null;

	function apiPath( query ) {
		const params = new URLSearchParams( query );
		return 'qpm/v1/products?' + params.toString();
	}

	function getFilters() {
		return {
			search: els.search ? els.search.value.trim() : '',
			type: els.filterType ? els.filterType.value : '',
			category: els.filterCategory ? els.filterCategory.value : '',
			tag: els.filterTag ? els.filterTag.value : '',
			stock_status: els.filterStock ? els.filterStock.value : '',
			catalog_visibility: els.filterVisibility ? els.filterVisibility.value : '',
		};
	}

	function showNotice( message, type ) {
		if ( ! els.notice ) {
			return;
		}
		els.notice.textContent = message;
		els.notice.hidden = false;
		els.notice.className = 'qpm-notice qpm-notice--' + ( type || 'info' );
		if ( window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message );
		}
	}

	function hideNotice() {
		if ( els.notice ) {
			els.notice.hidden = true;
		}
	}

	function setSaveEnabled( enabled ) {
		[ els.saveTop, els.saveBottom ].forEach( function ( btn ) {
			if ( btn ) {
				btn.disabled = ! enabled || state.saving;
			}
		} );
	}

	function updateSaveState() {
		const dirty = els.tbody.querySelectorAll( '.qpm-row--dirty' );
		setSaveEnabled( dirty.length > 0 );
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

	function rowClass( item ) {
		let cls = 'qpm-row';
		if ( item.row_type === 'parent' ) {
			cls += ' qpm-row--parent qpm-row--grouped';
		} else if ( item.row_type === 'variation' ) {
			cls += ' qpm-row--variation qpm-row--grouped';
		}
		cls += ' qpm-row--group-' + item.group_id;
		return cls;
	}

	function buildReadonlyRow( item ) {
		const badge =
			item.row_type === 'parent'
				? '<span class="qpm-badge">' + escAttr( item.product_type ) + '</span>'
				: '';
		return (
			'<tr class="' +
			rowClass( item ) +
			'" data-id="' +
			item.id +
			'" data-readonly="1" data-group-id="' +
			item.group_id +
			'">' +
			'<td>' +
			item.id +
			'</td>' +
			'<td class="qpm-title-cell"><span class="qpm-title-text">' +
			escAttr( item.title ) +
			'</span>' +
			badge +
			'</td>' +
			'<td colspan="5" class="qpm-readonly-dash">—</td>' +
			'</tr>'
		);
	}

	function buildEditableRow( item ) {
		const saleFrom = item.date_on_sale_from || '';
		const saleTo = item.date_on_sale_to || '';
		const hasSale = item.sale_price !== '' && item.sale_price !== null;
		const scheduleHidden = hasSale ? '' : ' qpm-schedule--hidden';
		const stockQty = item.stock_quantity !== null ? item.stock_quantity : '';
		const manageChecked = item.manage_stock ? ' checked' : '';
		const qtyDisabled = item.manage_stock ? '' : ' disabled';

		return (
			'<tr class="' +
			rowClass( item ) +
			'" data-id="' +
			item.id +
			'" data-readonly="0" data-group-id="' +
			item.group_id +
			'">' +
			'<td>' +
			item.id +
			'</td>' +
			'<td class="qpm-title-cell"><span class="qpm-title-text">' +
			escAttr( item.title ) +
			'</span></td>' +
			'<td><input type="text" class="qpm-input qpm-cell-input qpm-field-sku" value="' +
			escAttr( item.sku ) +
			'" data-original="' +
			escAttr( item.sku ) +
			'" /></td>' +
			'<td class="qpm-stock-cell">' +
			'<input type="number" class="qpm-input qpm-cell-input qpm-field-stock" min="0" step="1" value="' +
			escAttr( stockQty ) +
			'" data-original="' +
			escAttr( stockQty ) +
			'"' +
			qtyDisabled +
			' />' +
			'<label><input type="checkbox" class="qpm-field-manage-stock"' +
			manageChecked +
			' data-original="' +
			( item.manage_stock ? '1' : '0' ) +
			'" /> ' +
			escAttr( i18n.manageStock ) +
			'</label></td>' +
			'<td><input type="text" class="qpm-input qpm-cell-input qpm-field-regular" inputmode="decimal" value="' +
			escAttr( formatPrice( item.regular_price ) ) +
			'" data-original="' +
			escAttr( formatPrice( item.regular_price ) ) +
			'" /></td>' +
			'<td><input type="text" class="qpm-input qpm-cell-input qpm-field-sale" inputmode="decimal" value="' +
			escAttr( formatPrice( item.sale_price ) ) +
			'" data-original="' +
			escAttr( formatPrice( item.sale_price ) ) +
			'" /></td>' +
			'<td><div class="qpm-schedule' +
			scheduleHidden +
			'">' +
			'<input type="date" class="qpm-input qpm-field-sale-from" value="' +
			escAttr( saleFrom ) +
			'" data-original="' +
			escAttr( saleFrom ) +
			'" title="' +
			escAttr( i18n.saleFrom ) +
			'" />' +
			'<input type="date" class="qpm-input qpm-field-sale-to" value="' +
			escAttr( saleTo ) +
			'" data-original="' +
			escAttr( saleTo ) +
			'" title="' +
			escAttr( i18n.saleTo ) +
			'" /></div></td>' +
			'</tr>'
		);
	}

	function renderRow( item ) {
		if ( item.readonly ) {
			return buildReadonlyRow( item );
		}
		return buildEditableRow( item );
	}

	function setLoadingRow() {
		els.tbody.innerHTML =
			'<tr class="qpm-row qpm-row--loading"><td colspan="7">' +
			escAttr( i18n.loading ) +
			'</td></tr>';
	}

	function setEmptyRow() {
		els.tbody.innerHTML =
			'<tr class="qpm-row qpm-row--empty"><td colspan="7">' +
			escAttr( i18n.noResults ) +
			'</td></tr>';
	}

	function loadProducts( reset ) {
		if ( state.loading ) {
			return;
		}

		if ( reset ) {
			state.page = 1;
			state.totalPages = 1;
			setLoadingRow();
		}

		state.loading = true;

		const filters = getFilters();
		const query = {
			page: state.page,
			per_page: perPage,
		};

		Object.keys( filters ).forEach( function ( key ) {
			if ( filters[ key ] ) {
				query[ key ] = filters[ key ];
			}
		} );

		wp.apiFetch( { path: apiPath( query ) } )
			.then( function ( data ) {
				state.totalPages = data.total_pages || 1;

				if ( reset ) {
					els.tbody.innerHTML = '';
				}

				if ( ! data.items || data.items.length === 0 ) {
					if ( reset ) {
						setEmptyRow();
					}
					return;
				}

				const html = data.items.map( renderRow ).join( '' );
				if ( reset ) {
					els.tbody.innerHTML = html;
				} else {
					els.tbody.insertAdjacentHTML( 'beforeend', html );
				}

				bindRowEvents();
			} )
			.catch( function () {
				if ( reset ) {
					setEmptyRow();
				}
				showNotice( i18n.loadError, 'error' );
			} )
			.finally( function () {
				state.loading = false;
			} );
	}

	function loadNextPage() {
		if ( state.loading || state.page >= state.totalPages ) {
			return;
		}
		state.page += 1;
		loadProducts( false );
	}

	function markDirty( row ) {
		row.classList.add( 'qpm-row--dirty' );
		updateSaveState();
	}

	function checkRowDirty( row ) {
		let dirty = false;
		row.querySelectorAll( '[data-original]' ).forEach( function ( el ) {
			let current;
			if ( el.type === 'checkbox' ) {
				current = el.checked ? '1' : '0';
			} else {
				current = el.value;
			}
			if ( current !== el.getAttribute( 'data-original' ) ) {
				dirty = true;
			}
		} );
		if ( dirty ) {
			markDirty( row );
		} else {
			row.classList.remove( 'qpm-row--dirty' );
			updateSaveState();
		}
	}

	function toggleSchedule( row ) {
		const saleInput = row.querySelector( '.qpm-field-sale' );
		const schedule = row.querySelector( '.qpm-schedule' );
		if ( ! saleInput || ! schedule ) {
			return;
		}
		const hasSale = saleInput.value.trim() !== '';
		schedule.classList.toggle( 'qpm-schedule--hidden', ! hasSale );
	}

	function bindRowEvents() {
		els.tbody.querySelectorAll( '.qpm-row[data-readonly="0"]' ).forEach( function ( row ) {
			if ( row.dataset.qpmBound === '1' ) {
				return;
			}
			row.dataset.qpmBound = '1';

			row.addEventListener( 'input', function ( e ) {
				const target = e.target;
				if ( target.classList.contains( 'qpm-field-manage-stock' ) ) {
					const qty = row.querySelector( '.qpm-field-stock' );
					if ( qty ) {
						qty.disabled = ! target.checked;
					}
				}
				if ( target.classList.contains( 'qpm-field-sale' ) ) {
					toggleSchedule( row );
				}
				checkRowDirty( row );
			} );

			row.addEventListener( 'change', function () {
				checkRowDirty( row );
			} );
		} );
	}

	function collectChanges() {
		const changes = [];

		els.tbody.querySelectorAll( '.qpm-row--dirty' ).forEach( function ( row ) {
			const id = parseInt( row.getAttribute( 'data-id' ), 10 );
			const change = { id: id };

			const sku = row.querySelector( '.qpm-field-sku' );
			if ( sku && sku.value !== sku.getAttribute( 'data-original' ) ) {
				change.sku = sku.value;
			}

			const manage = row.querySelector( '.qpm-field-manage-stock' );
			if ( manage ) {
				const orig = manage.getAttribute( 'data-original' );
				const cur = manage.checked ? '1' : '0';
				if ( cur !== orig ) {
					change.manage_stock = manage.checked;
				}
			}

			const stock = row.querySelector( '.qpm-field-stock' );
			if ( stock && stock.value !== stock.getAttribute( 'data-original' ) ) {
				change.stock_quantity = stock.value;
			}

			const regular = row.querySelector( '.qpm-field-regular' );
			if ( regular && regular.value !== regular.getAttribute( 'data-original' ) ) {
				change.regular_price = regular.value;
			}

			const sale = row.querySelector( '.qpm-field-sale' );
			if ( sale && sale.value !== sale.getAttribute( 'data-original' ) ) {
				change.sale_price = sale.value;
			}

			const saleFrom = row.querySelector( '.qpm-field-sale-from' );
			if ( saleFrom && saleFrom.value !== saleFrom.getAttribute( 'data-original' ) ) {
				change.date_on_sale_from = saleFrom.value;
			}

			const saleTo = row.querySelector( '.qpm-field-sale-to' );
			if ( saleTo && saleTo.value !== saleTo.getAttribute( 'data-original' ) ) {
				change.date_on_sale_to = saleTo.value;
			}

			if ( Object.keys( change ).length > 1 ) {
				changes.push( change );
			}
		} );

		return changes;
	}

	function syncOriginals( row ) {
		row.querySelectorAll( '[data-original]' ).forEach( function ( el ) {
			if ( el.type === 'checkbox' ) {
				el.setAttribute( 'data-original', el.checked ? '1' : '0' );
			} else {
				el.setAttribute( 'data-original', el.value );
			}
		} );
		row.classList.remove( 'qpm-row--dirty' );
	}

	function saveChanges() {
		const changes = collectChanges();
		if ( changes.length === 0 ) {
			showNotice( i18n.noChanges, 'error' );
			return;
		}

		state.saving = true;
		setSaveEnabled( false );
		hideNotice();

		const label = i18n.saving;
		if ( els.saveTop ) {
			els.saveTop.textContent = label;
		}
		if ( els.saveBottom ) {
			els.saveBottom.textContent = label;
		}

		wp.apiFetch( {
			path: 'qpm/v1/products/batch',
			method: 'POST',
			data: { changes: changes },
		} )
			.then( function ( result ) {
				const updated = result.updated || [];
				const failed = result.failed || [];

				updated.forEach( function ( id ) {
					const row = els.tbody.querySelector( '.qpm-row[data-id="' + id + '"]' );
					if ( row ) {
						syncOriginals( row );
					}
				} );

				failed.forEach( function ( f ) {
					const row = els.tbody.querySelector( '.qpm-row[data-id="' + f.id + '"]' );
					if ( row ) {
						markDirty( row );
					}
				} );

				let msg = i18n.saved;
				if ( updated.length ) {
					msg = i18n.savedCount.replace( '%d', String( updated.length ) );
				}
				if ( failed.length ) {
					msg += ' ' + i18n.failedCount.replace( '%d', String( failed.length ) );
					showNotice( msg, failed.length && ! updated.length ? 'error' : 'success' );
				} else {
					showNotice( msg, 'success' );
				}

				updateSaveState();
			} )
			.catch( function () {
				showNotice( i18n.saveError, 'error' );
				updateSaveState();
			} )
			.finally( function () {
				state.saving = false;
				if ( els.saveTop ) {
					els.saveTop.textContent = i18n.saveChanges;
				}
				if ( els.saveBottom ) {
					els.saveBottom.textContent = i18n.saveChanges;
				}
			} );
	}

	function resetAndLoad() {
		hideNotice();
		updateSaveState();
		loadProducts( true );
	}

	function clearFilters() {
		if ( els.search ) {
			els.search.value = '';
		}
		[ els.filterType, els.filterCategory, els.filterTag, els.filterStock, els.filterVisibility ].forEach(
			function ( el ) {
				if ( el ) {
					el.value = '';
				}
			}
		);
		resetAndLoad();
	}

	function initScrollObserver() {
		if ( ! els.sentinel || ! window.IntersectionObserver ) {
			els.scrollWrap.addEventListener( 'scroll', function () {
				const el = els.scrollWrap;
				if ( el.scrollTop + el.clientHeight >= el.scrollHeight - 80 ) {
					loadNextPage();
				}
			} );
			return;
		}

		observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						loadNextPage();
					}
				} );
			},
			{ root: els.scrollWrap, rootMargin: '120px', threshold: 0 }
		);

		observer.observe( els.sentinel );
	}

	function init() {
		if ( els.search ) {
			els.search.addEventListener( 'input', function () {
				clearTimeout( searchDebounce );
				searchDebounce = setTimeout( resetAndLoad, 300 );
			} );
		}

		[
			els.filterType,
			els.filterCategory,
			els.filterTag,
			els.filterStock,
			els.filterVisibility,
		].forEach( function ( el ) {
			if ( el ) {
				el.addEventListener( 'change', resetAndLoad );
			}
		} );

		if ( els.clearFilters ) {
			els.clearFilters.addEventListener( 'click', clearFilters );
		}

		[ els.saveTop, els.saveBottom ].forEach( function ( btn ) {
			if ( btn ) {
				btn.addEventListener( 'click', saveChanges );
			}
		} );

		initScrollObserver();
		loadProducts( true );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
