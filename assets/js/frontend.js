/**
 * Frontend JavaScript for Mohamed Khaled API Plugin
 * @param {jQuery} $ jQuery library instance
 */
( function ( $ ) {
	'use strict';

	/**
	 * Initialize data tables on page load
	 */
	$( document ).ready( function () {
		$( '.mkap-data-table-container' ).each( function () {
			initDataTable( $( this ) );
		} );
	} );

	/**
	 * Initialize a single data table
	 *
	 * @param {jQuery} $container Table container element
	 */
	function initDataTable( $container ) {
		const columnVisibility = $container.data( 'column-visibility' ) || {};

		// Fetch and display data
		fetchTableData( $container, columnVisibility );
	}

	/**
	 * Fetch table data via AJAX
	 *
	 * @param {jQuery} $container       Table container element
	 * @param {Object} columnVisibility Column visibility settings
	 */
	function fetchTableData( $container, columnVisibility ) {
		const $loading = $container.find( '.mkap-loading' );
		const $error = $container.find( '.mkap-error' );
		const $tableWrapper = $container.find( '.mkap-table-wrapper' );

		// Show loading state
		$loading.show();
		$error.hide();
		$tableWrapper.hide();

		// Prepare AJAX data
		const ajaxData = {
			action: window.mkap_ajax.action,
			nonce: window.mkap_ajax.nonce,
		};

		// Make AJAX request
		$.ajax( {
			url: window.mkap_ajax.ajax_url,
			type: 'POST',
			data: ajaxData,
			success( response ) {
				if ( response.success && response.data ) {
					renderTable(
						$container,
						response.data,
						columnVisibility,
					);
					$loading.hide();
					$tableWrapper.show();
				} else {
					showError( $container, response.data?.message );
				}
			},
			error() {
				showError( $container );
			},
		} );
	}

	/**
	 * Render the data table
	 *
	 * @param {jQuery} $container       Table container element
	 * @param {Object} data             API data
	 * @param {Object} columnVisibility Column visibility settings
	 */
	function renderTable( $container, data, columnVisibility ) {
		const $tableWrapper = $container.find( '.mkap-table-wrapper' );

		if ( ! data.rows || data.rows.length === 0 ) {
			const noDataP = document.createElement( 'p' );
			noDataP.textContent = window.mkap_ajax.i18n.no_data;
			$tableWrapper.empty().append( noDataP );
			return;
		}

		// Get columns from first row
		const columns = Object.keys( data.rows[ 0 ] );

		// Create table using DOM methods for enhanced security
		const table = document.createElement( 'table' );
		table.className = 'mkap-data-table';

		// Build header
		const thead = document.createElement( 'thead' );
		const headerRow = document.createElement( 'tr' );
		
		columns.forEach( function ( column ) {
			if ( columnVisibility[ column ] !== false ) {
				const th = document.createElement( 'th' );
				th.textContent = escapeHtml( column );
				headerRow.appendChild( th );
			}
		} );
		
		thead.appendChild( headerRow );
		table.appendChild( thead );

		// Build body
		const tbody = document.createElement( 'tbody' );
		
		data.rows.forEach( function ( row ) {
			const tr = document.createElement( 'tr' );
			
			columns.forEach( function ( column ) {
				if ( columnVisibility[ column ] !== false ) {
					const value = row[ column ] || '';
					const td = document.createElement( 'td' );
					td.textContent = escapeHtml( String( value ) );
					tr.appendChild( td );
				}
			} );
			
			tbody.appendChild( tr );
		} );
		
		table.appendChild( tbody );

		// Clear wrapper and insert new table
		$tableWrapper.empty().append( table );
	}

	/**
	 * Show error message
	 *
	 * @param {jQuery} $container Table container element
	 * @param {string} message    Error message
	 */
	function showError( $container, message ) {
		const $loading = $container.find( '.mkap-loading' );
		const $error = $container.find( '.mkap-error' );
		const $tableWrapper = $container.find( '.mkap-table-wrapper' );

		$loading.hide();
		$tableWrapper.hide();

		if ( message ) {
			$error.find( 'p' ).text( message );
		}
		$error.show();
	}

	/**
	 * Escape HTML entities
	 *
	 * @param {string} text Text to escape
	 * @return {string} Escaped text
	 */
	function escapeHtml( text ) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			'\'': '&#039;',
		};

		return text.replace( /[&<>"']/g, function ( m ) {
			return map[ m ];
		} );
	}
} )( jQuery );
