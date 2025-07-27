/**
 * Simple Admin JavaScript for Mohamed Khaled API Plugin
 * @param {jQuery} $ jQuery library instance
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Initialize tab functionality
		initializeTabs();
		
		// Refresh button handler
		$( '#mkap-refresh-btn' ).on( 'click', handleRefreshClick );
		
		// Clear cache button handler
		$( '#mkap-clear-cache-btn' ).on( 'click', handleClearCacheClick );
	} );

	/**
	 * Handle refresh button click
	 *
	 * @param {Event} e Click event
	 */
	function handleRefreshClick( e ) {
		e.preventDefault();

		const $button = $( this );

		// Set loading state
		$button.prop( 'disabled', true );
		$button.addClass( 'loading' );
		$button.find( 'span:not(.dashicons)' ).text( window.mkap_admin.i18n.refreshing );

		// Make AJAX request
		$.ajax( {
			url: window.mkap_admin.ajax_url,
			type: 'POST',
			data: {
				action: 'mkap_refresh_data',
				nonce: window.mkap_admin.nonce,
			},
			success( response ) {
				if ( response.success ) {
					showNotice( 'success', response.data.message );
					// Reload the page to show fresh data
					setTimeout( function () {
						window.location.reload();
					}, 1000 );
				} else {
					showNotice( 'error', response.data.message || window.mkap_admin.i18n.error );
				}
			},
			error() {
				showNotice( 'error', window.mkap_admin.i18n.error );
			},
			complete() {
				// Reset button state
				$button.prop( 'disabled', false );
				$button.removeClass( 'loading' );
				$button.find( 'span:not(.dashicons)' ).text( 'Refresh Data' );
			},
		} );
	}

	/**
	 * Show admin notice
	 *
	 * @param {string} type    Notice type (success, error, warning)
	 * @param {string} message Notice message
	 */
	function showNotice( type, message ) {
		// Remove existing notices
		$( '.notice' ).remove();

		// Create notice element
		const $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml( message ) + '</p></div>' );

		// Insert notice after nav tabs or at the top of content
		if ( $( '.mkap-nav-tabs' ).length ) {
			$( '.mkap-nav-tabs' ).after( $notice );
		} else if ( $( '.mkap-plugin-title' ).length ) {
			$( '.mkap-plugin-title' ).after( $notice );
		} else {
			$( '.mkap-content-container' ).prepend( $notice );
		}

		// Auto-hide after 5 seconds
		setTimeout( function () {
			$notice.fadeOut();
		}, 5000 );
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
			"'": '&#039;',
		};

		return text.replace( /[&<>"']/g, function ( m ) {
			return map[ m ];
		} );
	}

	/**
	 * Handle clear cache button click
	 *
	 * @param {Event} e Click event
	 */
	function handleClearCacheClick( e ) {
		e.preventDefault();

		if ( ! confirm( 'Are you sure you want to clear the cache?' ) ) {
			return;
		}

		const $button = $( this );
		const originalText = $button.find( 'span:not(.dashicons)' ).text();

		// Set loading state
		$button.prop( 'disabled', true );
		$button.addClass( 'loading' );
		$button.find( 'span:not(.dashicons)' ).text( 'Clearing...' );

		// Make AJAX request
		$.ajax( {
			url: window.mkap_admin.ajax_url,
			type: 'POST',
			data: {
				action: 'mkap_clear_cache',
				nonce: window.mkap_admin.nonce,
			},
			success( response ) {
				if ( response.success ) {
					showNotice( 'success', response.data.message );
					// Reload the page to show updated cache status
					setTimeout( function () {
						window.location.reload();
					}, 1000 );
				} else {
					showNotice( 'error', response.data.message || 'Failed to clear cache' );
				}
			},
			error() {
				showNotice( 'error', 'Network error occurred while clearing cache' );
			},
			complete() {
				// Reset button state
				$button.prop( 'disabled', false );
				$button.removeClass( 'loading' );
				$button.find( 'span:not(.dashicons)' ).text( originalText );
			},
		} );
	}

	/**
	 * Initialize tab functionality
	 */
	function initializeTabs() {
		// Tab click handler
		$( '.mkap-tab-link' ).on( 'click', function( e ) {
			e.preventDefault();
			
			const $clickedTab = $( this ).closest( '.mkap-tab' );
			const targetId = $( this ).attr( 'href' );
			
			// Remove active class from all tabs
			$( '.mkap-tab' ).removeClass( 'active' );
			
			// Add active class to clicked tab
			$clickedTab.addClass( 'active' );
			
			// Hide all tab content
			$( '.mkap-tab-content' ).removeClass( 'active' ).hide();
			
			// Show target tab content
			$( targetId ).addClass( 'active' ).show();
			
			// Update URL hash without triggering page scroll
			if ( history.replaceState ) {
				history.replaceState( null, null, targetId );
			}
		} );
		
		// Initialize based on URL hash or show first tab
		const hash = window.location.hash;
		if ( hash && $( '.mkap-tab-link[href="' + hash + '"]' ).length ) {
			$( '.mkap-tab-link[href="' + hash + '"]' ).trigger( 'click' );
		} else {
			// Show first tab by default
			$( '.mkap-tab:first-child .mkap-tab-link' ).trigger( 'click' );
		}
	}
} )( jQuery );
