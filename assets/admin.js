/* LG Weekly Digest — Admin JS */
jQuery( function ( $ ) {

    const { nonce, ajaxUrl, adminEmail } = lgWD;

    // ── Response helper ───────────────────────────────────────────────────────

    function showResponse( msg, type = 'success' ) {
        const $r = $( '#lg-wd-response' );
        $r.removeClass( 'success error' )
          .addClass( type )
          .html( msg )
          .show();
        setTimeout( () => $r.fadeOut(), 5000 );
    }

    function setLoading( $btn, loading ) {
        if ( loading ) {
            $btn.data( 'orig', $btn.text() ).text( 'Working…' ).prop( 'disabled', true );
        } else {
            $btn.text( $btn.data( 'orig' ) || $btn.text() ).prop( 'disabled', false );
        }
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    $( '#lg-wd-save-btn' ).on( 'click', function () {
        const $btn = $( this );
        setLoading( $btn, true );

        // Serialize footer links into hidden field before form serialize
        serializeFooterLinks();

        const formData = $( '#lg-wd-form' ).serializeArray();
        formData.push( { name: 'action', value: 'lg_wd_save' } );
        formData.push( { name: 'nonce', value: nonce } );

        // Collect checkbox states explicitly (serializeArray skips unchecked)
        const checkboxNames = [
            'enabled', 'show_excerpts', 'show_thumbnails', 'skip_empty',
            'utm_enabled', 'fallback_enabled'
        ];
        checkboxNames.forEach( name => {
            const $cb = $( `[name="${name}"]` );
            if ( $cb.length && ! $cb.is( ':checked' ) ) {
                const idx = formData.findIndex( f => f.name === name );
                if ( idx !== -1 ) formData.splice( idx, 1 );
            }
        });

        $.post( ajaxUrl, formData, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Save failed.' ), 'error' );
            }
        } ).fail( function () {
            setLoading( $btn, false );
            showResponse( '✗ Request failed.', 'error' );
        });
    });

    // ── WP Media uploader for header image ───────────────────────────────────

    let mediaUploader;

    $( '#lg-wd-choose-img' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( mediaUploader ) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title:    'Select Header Image',
            button:   { text: 'Use this image' },
            multiple: false,
            library:  { type: 'image' },
        });

        mediaUploader.on( 'select', function () {
            const attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
            $( '#lg-wd-header-img-url' ).val( attachment.url );
            $( '#lg-wd-choose-img' ).text( 'Change Image' );

            const $prev = $( '.lg-wd-current-img' );
            if ( $prev.length ) {
                $prev.find( 'img' ).attr( 'src', attachment.url );
            } else {
                $( '#lg-wd-choose-img' ).before(
                    `<div class="lg-wd-current-img">
                       <img src="${attachment.url}" style="max-height:60px;max-width:100%;display:block;">
                       <button type="button" class="button button-small" id="lg-wd-remove-img">Remove</button>
                     </div>`
                );
            }
        });

        mediaUploader.open();
    });

    $( document ).on( 'click', '#lg-wd-remove-img', function () {
        $( '#lg-wd-header-img-url' ).val( '' );
        $( '.lg-wd-current-img' ).remove();
        $( '#lg-wd-choose-img' ).text( 'Choose Header Image' );
    });

    // ── CPT Registry: Add ────────────────────────────────────────────────────

    $( '#lg-wd-reg-add-btn' ).on( 'click', function () {
        const $btn  = $( this );
        const slug  = $( '#lg-wd-reg-slug' ).val();
        const label = $( '#lg-wd-reg-label' ).val().trim();
        const max   = parseInt( $( '#lg-wd-reg-max' ).val() ) || 5;

        if ( ! slug || ! label ) {
            showResponse( 'Select a post type and enter a label.', 'error' );
            return;
        }

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:    'lg_wd_registry_add',
            nonce,
            slug,
            label,
            max_items: max,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
                location.reload();
            } else {
                showResponse( '✗ ' + ( res.data || 'Failed to add.' ), 'error' );
            }
        });
    });

    // ── CPT Registry: Remove ─────────────────────────────────────────────────

    $( document ).on( 'click', '.lg-wd-registry-remove', function () {
        if ( ! confirm( 'Remove this content type from the registry?' ) ) return;

        const $btn = $( this );
        const slug = $btn.data( 'slug' );

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action: 'lg_wd_registry_remove',
            nonce,
            slug,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                $btn.closest( 'tr' ).fadeOut( 200, function () { $( this ).remove(); });
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Failed to remove.' ), 'error' );
            }
        });
    });

    // ── Footer Links Repeater ────────────────────────────────────────────────

    const $footerList = $( '#lg-wd-footer-links-list' );
    const $footerJson = $( '#lg-wd-footer-links-json' );

    function renderFooterLinks() {
        if ( ! $footerList.length ) return;

        let links = [];
        try { links = JSON.parse( $footerJson.val() || '[]' ); } catch ( e ) {}
        if ( ! Array.isArray( links ) ) links = [];

        $footerList.empty();
        links.forEach( function ( link, i ) {
            $footerList.append( footerLinkRow( link.label || '', link.url || '' ) );
        });
    }

    function footerLinkRow( label, url ) {
        return `<div class="lg-wd-footer-link-row">
            <input type="text" class="lg-wd-input lg-wd-fl-label" value="${escAttr( label )}" placeholder="Label" style="flex:1;">
            <input type="text" class="lg-wd-input lg-wd-fl-url" value="${escAttr( url )}" placeholder="https://..." style="flex:2;">
            <button type="button" class="button button-small lg-wd-fl-remove">✕</button>
        </div>`;
    }

    function escAttr( str ) {
        return String( str ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' );
    }

    function serializeFooterLinks() {
        if ( ! $footerList.length ) return;
        const links = [];
        $footerList.find( '.lg-wd-footer-link-row' ).each( function () {
            const label = $( this ).find( '.lg-wd-fl-label' ).val().trim();
            const url   = $( this ).find( '.lg-wd-fl-url' ).val().trim();
            if ( label && url ) {
                links.push( { label, url } );
            }
        });
        $footerJson.val( JSON.stringify( links ) );
    }

    $( '#lg-wd-footer-link-add' ).on( 'click', function () {
        $footerList.append( footerLinkRow( '', '' ) );
    });

    $( document ).on( 'click', '.lg-wd-fl-remove', function () {
        $( this ).closest( '.lg-wd-footer-link-row' ).remove();
    });

    renderFooterLinks();

    // ── UTM Toggle ───────────────────────────────────────────────────────────

    $( '[name="utm_enabled"]' ).on( 'change', function () {
        $( '#lg-wd-utm-fields' ).toggle( this.checked );
    });

});
