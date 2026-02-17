/**
 * ArchivioID — Post Editor Meta Box JavaScript
 *
 * Handles signature verification and deletion AJAX actions with dynamic UI updates.
 *
 * UPDATED v1.1.1:
 * - Enhanced badge update to refresh meta box status
 * - Auto page reload after successful verification (2-second delay)
 * - Improved error handling and user feedback
 * - Cache-busting for fresh data display
 *
 * @package ArchivioID
 * @since   1.0.0
 * @version 1.1.1
 */
/* global jQuery, archivioIdPost */
(function ( $ ) {
    'use strict';

    const ajax    = archivioIdPost.ajaxUrl;
    const nonce   = archivioIdPost.nonce;
    const post_id = archivioIdPost.postId;
    const str     = archivioIdPost.strings;

    // ══════════════════════════════════════════════════════════════════════════
    // Verify Signature Handler
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Handle "Verify Signature" button click.
     *
     * Flow:
     * 1. Disable button and show spinner
     * 2. Send AJAX verification request
     * 3. On success:
     *    - Update button text and badge immediately
     *    - Show success message with verification details
     *    - Auto-reload page after 2 seconds to refresh all UI elements
     * 4. On failure:
     *    - Update badge to show error state
     *    - Display failure reason
     *    - Re-enable button for retry
     *
     * The 2-second auto-reload ensures:
     * - Meta box badge reflects database state
     * - Frontend badges (lock emoji) appear
     * - All persistent UI elements are synchronized
     */
    $( document ).on( 'click', '#archivio-id-verify-btn', function () {
        const $btn     = $( this );
        const $spinner = $( '#archivio-id-action-spinner' );
        const $result  = $( '#archivio-id-verify-result' );

        // ──────────────────────────────────────────────────────────────────────
        // STEP 1: Update UI to "verifying" state
        // ──────────────────────────────────────────────────────────────────────
        $btn.prop( 'disabled', true ).text( str.verifying );
        $spinner.css( 'visibility', 'visible' );
        $result.hide().attr( 'class', '' );

        // ──────────────────────────────────────────────────────────────────────
        // STEP 2: Send AJAX verification request
        // ──────────────────────────────────────────────────────────────────────
        $.post( ajax, {
            action:  'archivio_id_verify',
            nonce:   nonce,
            post_id: post_id,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                // ──────────────────────────────────────────────────────────────
                // SUCCESS: Signature verified
                // ──────────────────────────────────────────────────────────────
                const d = res.data;

                // Update button to verified state (keep disabled)
                $btn.text( str.verified ).prop( 'disabled', true );

                // ══════════════════════════════════════════════════════════════
                // FIX #1: Update meta box badge immediately
                // ══════════════════════════════════════════════════════════════
                // This provides instant visual feedback before page reload.
                // Uses the badge_label and badge_class from enhanced AJAX response.
                // ══════════════════════════════════════════════════════════════
                updateBadge( d.badge_class || 'verified', d.badge_label || str.verified );

                // Build success message with verification details
                let message = '<strong>' + ( d.message || str.verified ) + '</strong>';
                
                if ( d.key_label ) {
                    message += '<br><small style="color:#0a7537;">Key: ' + escapeHtml( d.key_label ) + '</small>';
                }
                
                if ( d.sig_data && d.sig_data.verified_at ) {
                    message += '<br><small style="color:#666;">Verified: ' + escapeHtml( d.sig_data.verified_at ) + '</small>';
                }

                // Show success message
                $result
                    .addClass( 'verified' )
                    .html( message )
                    .show();

                // ══════════════════════════════════════════════════════════════
                // FIX #2: Auto-reload page after 2 seconds
                // ══════════════════════════════════════════════════════════════
                // Why reload is necessary:
                // 1. Meta box HTML is rendered server-side at page load
                // 2. Frontend badges (lock emoji) require fresh page render
                // 3. Ensures all UI elements reflect verified state
                // 4. Clears any remaining stale cache
                //
                // The 2-second delay allows user to see success message before
                // reload, providing better UX than immediate refresh.
                // ══════════════════════════════════════════════════════════════
                if ( d.should_reload !== false ) { // Default to true if not specified
                    // Update message to indicate reload
                    setTimeout( function() {
                        $result.html( 
                            '<strong>' + str.reloadingPage + '</strong>' 
                        );
                    }, 1000 );

                    // Reload page after 2 seconds
                    setTimeout( function() {
                        window.location.reload();
                    }, 2000 );
                }

            } else {
                // ──────────────────────────────────────────────────────────────
                // FAILURE: Verification failed or error occurred
                // ──────────────────────────────────────────────────────────────
                const d = res.data || {};
                const status = d.status || 'invalid';
                const isError = status === 'error';

                // Update button (re-enable for retry)
                $btn.prop( 'disabled', false ).text( isError ? str.error : str.invalid );

                // ══════════════════════════════════════════════════════════════
                // FIX #1: Update meta box badge to show failure state
                // ══════════════════════════════════════════════════════════════
                updateBadge( 
                    d.badge_class || status, 
                    d.badge_label || ( isError ? str.error : str.invalid ) 
                );

                // Build error message
                let errorMsg = d.message || str.requestFailed;
                
                if ( d.sig_data && d.sig_data.failure_reason ) {
                    errorMsg += '<br><small>' + escapeHtml( d.sig_data.failure_reason ) + '</small>';
                }

                // Show error message
                $result
                    .addClass( isError ? 'error' : 'invalid' )
                    .html( errorMsg )
                    .show();
            }
        } )
        .fail( function ( jqXHR, textStatus, errorThrown ) {
            // ──────────────────────────────────────────────────────────────────
            // AJAX REQUEST FAILED (network error, timeout, etc.)
            // ──────────────────────────────────────────────────────────────────
            console.error( 'ArchivioID AJAX Error:', textStatus, errorThrown );
            
            $btn.prop( 'disabled', false ).text( str.error );
            updateBadge( 'error', str.error );
            
            $result
                .addClass( 'error' )
                .html( 
                    '<strong>' + str.requestFailed + '</strong>' +
                    '<br><small>Network error. Check console for details.</small>'
                )
                .show();
        } )
        .always( function () {
            // Always hide spinner when request completes
            $spinner.css( 'visibility', 'hidden' );
        } );
    } );

    // ══════════════════════════════════════════════════════════════════════════
    // Remove Signature Handler
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Handle "Remove" button click.
     *
     * Shows confirmation dialog, then sends AJAX deletion request.
     * On success, reloads page to show upload form.
     */
    $( document ).on( 'click', '#archivio-id-delete-sig-btn', function () {
        // Confirm deletion
        if ( ! window.confirm( str.confirmDelete ) ) {
            return;
        }

        const $btn     = $( this );
        const $spinner = $( '#archivio-id-action-spinner' );

        $btn.prop( 'disabled', true );
        $spinner.css( 'visibility', 'visible' );

        $.post( ajax, {
            action:  'archivio_id_delete_signature',
            nonce:   nonce,
            post_id: post_id,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                // Signature deleted successfully - reload to show upload form
                // Cache has been cleared on server side, reload will show fresh state
                window.location.reload();
            } else {
                alert( res.data.message || str.requestFailed );
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            alert( str.requestFailed );
            $btn.prop( 'disabled', false );
        } )
        .always( function () {
            $spinner.css( 'visibility', 'hidden' );
        } );
    } );

    // ══════════════════════════════════════════════════════════════════════════
    // Helper Functions
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Update the meta box status badge.
     *
     * Updates both the CSS class (for styling) and text content.
     *
     * @param {string} statusClass Status class (verified, invalid, uploaded, error, not-signed)
     * @param {string} label       Human-readable label to display
     */
    function updateBadge( statusClass, label ) {
        const $badge = $( '.archivio-id-status-badge' );
        
        if ( $badge.length === 0 ) {
            console.warn( 'ArchivioID: Status badge element not found' );
            return;
        }

        // Update CSS class for styling (remove all status classes first)
        $badge
            .removeClass( function( index, className ) {
                // Remove all archivio-id-status-* classes
                return ( className.match( /\barchivio-id-status-\S+/g ) || [] ).join( ' ' );
            } )
            .addClass( 'archivio-id-status-badge archivio-id-status-' + statusClass )
            .text( label );

    }

    /**
     * Escape HTML to prevent XSS in user-generated content.
     *
     * @param {string} text Text to escape
     * @return {string} HTML-safe text
     */
    function escapeHtml( text ) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String( text ).replace( /[&<>"']/g, function( m ) { return map[m]; } );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Optional: Periodic Status Check (Commented Out)
    // ══════════════════════════════════════════════════════════════════════════
    // 
    // For future enhancement: automatically check signature status every N seconds
    // to detect changes made in other browser tabs or by other users.
    //
    // Uncomment and configure if needed:
    //
    // setInterval( function() {
    //     $.post( ajax, {
    //         action:  'archivio_id_get_status',
    //         nonce:   nonce,
    //         post_id: post_id,
    //     } )
    //     .done( function( res ) {
    //         if ( res.success && res.data.status ) {
    //             updateBadge( res.data.badge_class, res.data.badge_label );
    //         }
    //     } );
    // }, 30000 ); // Check every 30 seconds
    //
    // ══════════════════════════════════════════════════════════════════════════

} )( jQuery );
