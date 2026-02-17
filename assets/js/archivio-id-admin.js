/**
 * ArchivioID — Admin Key Management JS
 * Handles add / deactivate / activate / delete key AJAX actions.
 */
/* global jQuery, archivioIdAdmin */
(function ( $ ) {
    'use strict';

    const ajax = archivioIdAdmin.ajaxUrl;
    const nonce = archivioIdAdmin.nonce;
    const str = archivioIdAdmin.strings;

    // ── Add Key ──────────────────────────────────────────────────────────────

    $( '#archivio-id-add-key-btn' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#archivio-id-add-key-spinner' );
        const $notice  = $( '#archivio-id-add-key-notice' );
        const label    = $.trim( $( '#archivio-id-key-label' ).val() );
        const armored  = $.trim( $( '#archivio-id-armored-key' ).val() );

        if ( ! label || ! armored ) {
            showNotice( $notice, str.error || 'Error', 'error' );
            return;
        }

        $btn.prop( 'disabled', true );
        $spinner.css( 'visibility', 'visible' );
        $notice.hide();

        $.post( ajax, {
            action:      'archivio_id_add_key',
            nonce:       nonce,
            label:       label,
            armored_key: armored,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                showNotice( $notice, res.data.message, 'success' );
                $( '#archivio-id-key-label' ).val( '' );
                $( '#archivio-id-armored-key' ).val( '' );
                // Reload table section after a short pause.
                setTimeout( function () { window.location.reload(); }, 1200 );
            } else {
                showNotice( $notice, res.data.message || str.error, 'error' );
            }
        } )
        .fail( function () {
            showNotice( $notice, str.error, 'error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.css( 'visibility', 'hidden' );
        } );
    } );

    // ── Deactivate Key ────────────────────────────────────────────────────────

    $( document ).on( 'click', '.archivio-id-deactivate-key', function () {
        const $btn   = $( this );
        const key_id = $btn.data( 'key-id' );
        $btn.prop( 'disabled', true ).text( str.deleting || '…' );

        $.post( ajax, { action: 'archivio_id_deactivate_key', nonce, key_id } )
            .done( function ( res ) {
                if ( res.success ) { window.location.reload(); }
                else { alert( res.data.message || str.error ); $btn.prop( 'disabled', false ); }
            } )
            .fail( function () { alert( str.error ); $btn.prop( 'disabled', false ); } );
    } );

    // ── Activate Key ──────────────────────────────────────────────────────────

    $( document ).on( 'click', '.archivio-id-activate-key', function () {
        const $btn   = $( this );
        const key_id = $btn.data( 'key-id' );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajax, { action: 'archivio_id_activate_key', nonce, key_id } )
            .done( function ( res ) {
                if ( res.success ) { window.location.reload(); }
                else { alert( res.data.message || str.error ); $btn.prop( 'disabled', false ); }
            } )
            .fail( function () { alert( str.error ); $btn.prop( 'disabled', false ); } );
    } );

    // ── Delete Key ────────────────────────────────────────────────────────────

    $( document ).on( 'click', '.archivio-id-delete-key', function () {
        const $btn   = $( this );
        const key_id = $btn.data( 'key-id' );

        if ( ! window.confirm( str.confirmDeleteKey ) ) {
            return;
        }
        $btn.prop( 'disabled', true ).text( str.deleting || '…' );

        $.post( ajax, { action: 'archivio_id_delete_key', nonce, key_id } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '#archivio-id-key-row-' + key_id ).fadeOut( 300, function () {
                        $( this ).remove();
                    } );
                } else {
                    alert( res.data.message || str.error );
                    $btn.prop( 'disabled', false );
                }
            } )
            .fail( function () { alert( str.error ); $btn.prop( 'disabled', false ); } );
    } );

    // ── Helpers ───────────────────────────────────────────────────────────────

    function showNotice( $el, msg, type ) {
        $el.attr( 'class', 'notice-' + type ).text( msg ).show();
    }

} )( jQuery );
