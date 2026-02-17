/**
 * ArchivioID — Frontend Signature Download
 *
 * Intercepts clicks on .archivio-id-lock-link anchors and triggers a
 * browser download of the post's verified detached .asc signature.
 *
 * The download is initiated by constructing a signed AJAX URL and
 * assigning it to a temporary anchor element — no page navigation occurs.
 * If the server returns an error, the link silently reverts to its
 * default cursor and the user sees no broken UI.
 *
 * @package ArchivioID
 * @since   1.2.0
 */
/* global jQuery, archivioIdFrontend */
( function ( $ ) {
    'use strict';

    const cfg = archivioIdFrontend;

    // ── Download trigger ──────────────────────────────────────────────────────

    $( document ).on( 'click', '.archivio-id-lock-link', function ( e ) {
        e.preventDefault();

        const $link  = $( this );
        const postId = parseInt( $link.data( 'post-id' ), 10 ) || cfg.postId;

        // Prevent double-clicks while a download is already in-flight.
        if ( $link.hasClass( 'archivio-id-downloading' ) ) {
            return;
        }

        $link.addClass( 'archivio-id-downloading' ).attr( 'aria-busy', 'true' );

        // Build the AJAX download URL. Using GET so the browser triggers the
        // Content-Disposition: attachment response natively.
        const params = $.param( {
            action:  'archivio_id_download_sig',
            post_id: postId,
            nonce:   cfg.nonce,
        } );

        const downloadUrl = cfg.ajaxUrl + '?' + params;

        // Trigger the download by temporarily creating a hidden anchor.
        // This avoids any page navigation and works across all browsers.
        const tempLink = document.createElement( 'a' );
        tempLink.href     = downloadUrl;
        tempLink.download = '';        // Let Content-Disposition set the filename.
        tempLink.style.display = 'none';

        document.body.appendChild( tempLink );
        tempLink.click();
        document.body.removeChild( tempLink );

        // Re-enable the lock icon after a short delay to allow the browser
        // download dialog to appear.
        setTimeout( function () {
            $link.removeClass( 'archivio-id-downloading' ).removeAttr( 'aria-busy' );
        }, 1500 );
    } );

} )( jQuery );
