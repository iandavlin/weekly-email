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

        const formData = $( '#lg-wd-form' ).serializeArray();
        formData.push( { name: 'action', value: 'lg_wd_save' } );
        formData.push( { name: 'nonce', value: nonce } );

        // Collect checkbox states explicitly (serializeArray skips unchecked)
        const checkboxNames = [
            'enabled', 'show_excerpts', 'show_thumbnails', 'skip_empty'
        ];
        checkboxNames.forEach( name => {
            const $cb = $( `[name="${name}"]` );
            if ( $cb.length && ! $cb.is( ':checked' ) ) {
                // Remove any existing entry and add 0
                const idx = formData.findIndex( f => f.name === name );
                if ( idx !== -1 ) formData.splice( idx, 1 );
                // Don't push — absence = false, handled server side
            }
        });

        // Section checkboxes
        $( '.lg-wd-sections-list .lg-wd-section-item' ).each( function ( i ) {
            const $item = $( this );
            const $cb = $item.find( 'input[type="checkbox"]' );
            if ( $cb.length && ! $cb.is( ':checked' ) ) {
                // Remove the enabled entry if present
                const name = `sections[${i}][enabled]`;
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

    // ── Test Send ─────────────────────────────────────────────────────────────

    $( '#lg-wd-test-btn' ).on( 'click', function () {
        const $btn = $( this );
        const to   = $( '#lg-wd-test-email' ).val().trim();
        if ( ! to ) { showResponse( 'Enter a test email address.', 'error' ); return; }

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action: 'lg_wd_test_send',
            nonce,
            to,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Test send failed.' ), 'error' );
            }
        });
    });

    // ── Send Now ──────────────────────────────────────────────────────────────

    $( '#lg-wd-send-now-btn' ).on( 'click', function () {
        if ( ! confirm( 'Send the digest to all subscribers now? This cannot be undone.' ) ) return;

        const $btn = $( this );
        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action: 'lg_wd_send_now',
            nonce,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Send failed.' ), 'error' );
            }
        });
    });

    // ── Preview ───────────────────────────────────────────────────────────────

    $( '#lg-wd-preview-btn' ).on( 'click', function () {
        const $btn = $( this );
        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action: 'lg_wd_preview',
            nonce,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                const iframe = document.getElementById( 'lg-wd-preview-frame' );
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.open();
                doc.write( res.data.html );
                doc.close();
                $( '#lg-wd-preview-modal' ).show();
                $( 'body' ).css( 'overflow', 'hidden' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Preview failed.' ), 'error' );
            }
        });
    });

    $( '.lg-wd-modal-overlay, .lg-wd-modal-close' ).on( 'click', function () {
        $( '#lg-wd-preview-modal' ).hide();
        $( 'body' ).css( 'overflow', '' );
    });

    // ── Section sortable ──────────────────────────────────────────────────────

    if ( $( '#lg-wd-sections-list' ).length ) {
        $( '#lg-wd-sections-list' ).sortable({
            handle: '.lg-wd-drag-handle',
            axis: 'y',
            tolerance: 'pointer',
            stop: function () {
                // Re-index section names after drag
                $( '#lg-wd-sections-list .lg-wd-section-item' ).each( function ( i ) {
                    $( this ).attr( 'data-index', i );
                    $( this ).find( 'input, select' ).each( function () {
                        const name = $( this ).attr( 'name' );
                        if ( name ) {
                            $( this ).attr( 'name', name.replace( /sections\[\d+\]/, `sections[${i}]` ) );
                        }
                    });
                });
            }
        });
    }

    // ── Add custom CPT section ────────────────────────────────────────────────

    $( '#lg-wd-add-section-btn' ).on( 'click', function () {
        const label = $( '#lg-wd-new-label' ).val().trim();
        const slug  = $( '#lg-wd-new-slug' ).val().trim();
        const max   = parseInt( $( '#lg-wd-new-max' ).val() ) || 3;

        if ( ! label || ! slug ) {
            showResponse( 'Both label and CPT slug are required.', 'error' );
            return;
        }

        const $list  = $( '#lg-wd-sections-list' );
        const i      = $list.children().length;
        const key    = slug.replace( /[^a-z0-9_]/g, '_' );
        const type   = slug.indexOf( ',' ) !== -1 ? 'multi_cpt' : 'cpt';

        const $li = $( `
            <li class="lg-wd-section-item" data-index="${i}">
              <span class="lg-wd-drag-handle" title="Drag to reorder">⠿</span>
              <input type="hidden" name="sections[${i}][key]"  value="${key}">
              <input type="hidden" name="sections[${i}][type]" value="${type}">
              <label class="lg-wd-toggle">
                <input type="checkbox" name="sections[${i}][enabled]" value="1" checked>
                <span class="lg-wd-toggle-track"></span>
              </label>
              <div class="lg-wd-section-info">
                <input type="text" name="sections[${i}][label]" value="${label}"
                       class="lg-wd-section-label-input" placeholder="Section label">
                <input type="text" name="sections[${i}][slug]" value="${slug}"
                       class="lg-wd-section-slug-input" placeholder="CPT slug(s)">
              </div>
              <div class="lg-wd-section-max">
                <label>Max</label>
                <input type="number" name="sections[${i}][max_items]" value="${max}"
                       min="1" max="20" style="width:50px;">
              </div>
              <button type="button" class="button button-small lg-wd-remove-section">✕</button>
            </li>
        ` );

        $list.append( $li );
        $( '#lg-wd-new-label, #lg-wd-new-slug' ).val( '' );
        $( '#lg-wd-new-max' ).val( '3' );
    });

    // Remove section
    $( '#lg-wd-sections-list' ).on( 'click', '.lg-wd-remove-section', function () {
        if ( confirm( 'Remove this section?' ) ) {
            $( this ).closest( 'li' ).fadeOut( 200, function () {
                $( this ).remove();
            });
        }
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

            // Show preview
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

});
