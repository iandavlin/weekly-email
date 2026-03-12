/* LG Weekly Digest — Compose Page JS */
jQuery( function ( $ ) {

    const { nonce, ajaxUrl } = lgWDCompose;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function showResponse( msg, type ) {
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

    /**
     * Collect the current state of all sections from the DOM.
     * Returns a JSON-serializable array.
     */
    function collectSections() {
        const sections = [];
        $( '#lg-wd-sections-container .lg-wd-compose-section' ).each( function () {
            const $sec = $( this );
            const postIds = [];

            // Only include checked posts
            $sec.find( '.lg-wd-post-item input[type="checkbox"]:checked' ).each( function () {
                postIds.push( parseInt( $( this ).data( 'post-id' ) ) );
            });

            sections.push({
                key:      $sec.data( 'section-key' ),
                label:    $sec.find( '.lg-wd-compose-section-header strong' ).text(),
                template: $sec.data( 'section-template' ),
                slug:     $sec.data( 'section-slug' ),
                post_ids: postIds,
            });
        });
        return sections;
    }

    // ── Section sortable ─────────────────────────────────────────────────────

    $( '#lg-wd-sections-container' ).sortable({
        handle: '.lg-wd-drag-handle',
        axis: 'y',
        tolerance: 'pointer',
        items: '.lg-wd-compose-section',
        placeholder: 'lg-wd-sortable-placeholder',
    });

    // ── New Issue ─────────────────────────────────────────────────────────────

    $( document ).on( 'click', '#lg-wd-new-issue-btn, #lg-wd-new-issue-btn-empty', function () {
        const $btn = $( this );
        setLoading( $btn, true );

        $.post( ajaxUrl, { action: 'lg_wd_compose_new_issue', nonce }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                window.location.href = res.data.redirect;
            } else {
                showResponse( '✗ ' + ( res.data || 'Failed to create issue.' ), 'error' );
            }
        });
    });

    // ── Auto-Populate ────────────────────────────────────────────────────────

    $( '#lg-wd-populate-btn' ).on( 'click', function () {
        const $btn     = $( this );
        const dateFrom = $( '#lg-wd-date-from' ).val();
        const dateTo   = $( '#lg-wd-date-to' ).val();

        if ( ! dateFrom || ! dateTo ) {
            showResponse( 'Set both date fields first.', 'error' );
            return;
        }

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:    'lg_wd_compose_populate',
            nonce,
            date_from: dateFrom,
            date_to:   dateTo,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                $( '#lg-wd-sections-container' ).html( res.data.html );
                initPostSortable();
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Populate failed.' ), 'error' );
            }
        });
    });

    // ── Save Draft ───────────────────────────────────────────────────────────

    $( '#lg-wd-save-draft-btn' ).on( 'click', function () {
        const $btn    = $( this );
        const issueId = $( '#lg-wd-issue-id' ).val();
        if ( ! issueId ) return;

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:    'lg_wd_compose_save',
            nonce,
            issue_id:  issueId,
            title:     $( '#lg-wd-issue-title' ).val(),
            date_from: $( '#lg-wd-date-from' ).val(),
            date_to:   $( '#lg-wd-date-to' ).val(),
            sections:  JSON.stringify( collectSections() ),
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Save failed.' ), 'error' );
            }
        });
    });

    // ── Preview ──────────────────────────────────────────────────────────────

    $( '#lg-wd-preview-btn' ).on( 'click', function () {
        const $btn    = $( this );
        const issueId = $( '#lg-wd-issue-id' ).val();
        if ( ! issueId ) return;

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:   'lg_wd_compose_preview',
            nonce,
            issue_id: issueId,
            sections: JSON.stringify( collectSections() ),
            date_from: $( '#lg-wd-date-from' ).val(),
            date_to:   $( '#lg-wd-date-to' ).val(),
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

    // Close modal
    $( document ).on( 'click', '.lg-wd-modal-overlay, .lg-wd-modal-close', function () {
        $( '#lg-wd-preview-modal, #lg-wd-custom-section-modal' ).hide();
        $( 'body' ).css( 'overflow', '' );
    });

    // ── Test Send ────────────────────────────────────────────────────────────

    $( '#lg-wd-test-btn' ).on( 'click', function () {
        const $btn    = $( this );
        const issueId = $( '#lg-wd-issue-id' ).val();
        const to      = $( '#lg-wd-test-email' ).val().trim();

        if ( ! issueId || ! to ) {
            showResponse( 'Enter a test email address.', 'error' );
            return;
        }

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:   'lg_wd_compose_test_send',
            nonce,
            issue_id: issueId,
            to,
            sections: JSON.stringify( collectSections() ),
            date_from: $( '#lg-wd-date-from' ).val(),
            date_to:   $( '#lg-wd-date-to' ).val(),
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
            } else {
                showResponse( '✗ ' + ( res.data || 'Test send failed.' ), 'error' );
            }
        });
    });

    // ── Send Now ─────────────────────────────────────────────────────────────

    $( '#lg-wd-send-btn' ).on( 'click', function () {
        if ( ! confirm( 'Send this issue to all subscribers? This cannot be undone.' ) ) return;

        const $btn    = $( this );
        const issueId = $( '#lg-wd-issue-id' ).val();
        if ( ! issueId ) return;

        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action:   'lg_wd_compose_send',
            nonce,
            issue_id: issueId,
            sections: JSON.stringify( collectSections() ),
            date_from: $( '#lg-wd-date-from' ).val(),
            date_to:   $( '#lg-wd-date-to' ).val(),
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                showResponse( '✓ ' + res.data.message, 'success' );
                $( '#lg-wd-send-btn' ).remove(); // Remove send button after successful send
            } else {
                showResponse( '✗ ' + ( res.data || 'Send failed.' ), 'error' );
            }
        });
    });

    // ── Remove section ───────────────────────────────────────────────────────

    $( document ).on( 'click', '.lg-wd-remove-section-btn', function () {
        if ( confirm( 'Remove this section from the issue?' ) ) {
            $( this ).closest( '.lg-wd-compose-section' ).fadeOut( 200, function () {
                $( this ).remove();
            });
        }
    });

    // ── Remove individual post ───────────────────────────────────────────────

    $( document ).on( 'click', '.lg-wd-post-remove', function () {
        $( this ).closest( '.lg-wd-post-item' ).fadeOut( 200, function () {
            const $section = $( this ).closest( '.lg-wd-compose-section' );
            $( this ).remove();
            updateSectionCount( $section );
        });
    });

    // ── Checkbox toggle updates count ────────────────────────────────────────

    $( document ).on( 'change', '.lg-wd-post-item input[type="checkbox"]', function () {
        const $section = $( this ).closest( '.lg-wd-compose-section' );
        updateSectionCount( $section );
        $( this ).closest( '.lg-wd-post-item' ).toggleClass( 'lg-wd-post-excluded', ! this.checked );
    });

    function updateSectionCount( $section ) {
        const count = $section.find( '.lg-wd-post-item input[type="checkbox"]:checked' ).length;
        $section.find( '.lg-wd-section-count' ).text( count + ' items' );
    }

    // ── Add Section ──────────────────────────────────────────────────────────

    $( '#lg-wd-add-section-btn' ).on( 'click', function () {
        const $select = $( '#lg-wd-add-section-select' );
        const val     = $select.val();

        if ( ! val ) {
            showResponse( 'Select a section type first.', 'error' );
            return;
        }

        if ( val === '__custom__' ) {
            $( '#lg-wd-custom-section-modal' ).show();
            return;
        }

        const $opt     = $select.find( ':selected' );
        const label    = $opt.data( 'label' );
        const template = $opt.data( 'template' ) || 'card';
        const key      = val.replace( /[^a-z0-9_]/g, '_' );

        addEmptySection( key, label, template, val );
        $select.val( '' );
    });

    $( '#lg-wd-custom-section-add' ).on( 'click', function () {
        const label = $( '#lg-wd-custom-label' ).val().trim();
        const key   = $( '#lg-wd-custom-key' ).val().trim().replace( /[^a-z0-9_]/g, '_' );

        if ( ! label || ! key ) {
            showResponse( 'Both label and key are required.', 'error' );
            return;
        }

        addEmptySection( key, label, 'card', '' );
        $( '#lg-wd-custom-label, #lg-wd-custom-key' ).val( '' );
        $( '#lg-wd-custom-section-modal' ).hide();
    });

    function addEmptySection( key, label, template, slug ) {
        const html = `
            <div class="lg-wd-compose-section" data-section-key="${key}" data-section-template="${template}" data-section-slug="${slug}">
              <div class="lg-wd-compose-section-header">
                <span class="lg-wd-drag-handle" title="Drag to reorder">⠿</span>
                <strong>${label}</strong>
                <span class="lg-wd-section-type-badge">${template.toUpperCase()}</span>
                <span class="lg-wd-section-count">0 items</span>
                <button type="button" class="button button-small lg-wd-remove-section-btn" title="Remove section">✕</button>
              </div>
              <div class="lg-wd-compose-section-body">
                <p class="lg-wd-empty-section">No posts in this section. Use search to add posts.</p>
              </div>
            </div>
        `;
        $( '#lg-wd-sections-container' ).append( html );
    }

    // ── Archive Search ───────────────────────────────────────────────────────

    $( '#lg-wd-archive-search-btn' ).on( 'click', doSearch );
    $( '#lg-wd-archive-search' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { e.preventDefault(); doSearch(); }
    });

    function doSearch() {
        const term = $( '#lg-wd-archive-search' ).val().trim();
        if ( term.length < 2 ) {
            showResponse( 'Type at least 2 characters.', 'error' );
            return;
        }

        const $btn = $( '#lg-wd-archive-search-btn' );
        setLoading( $btn, true );

        $.post( ajaxUrl, {
            action: 'lg_wd_compose_search',
            nonce,
            term,
        }, function ( res ) {
            setLoading( $btn, false );
            if ( res.success ) {
                renderSearchResults( res.data.results );
            } else {
                showResponse( '✗ ' + ( res.data || 'Search failed.' ), 'error' );
            }
        });
    }

    function renderSearchResults( results ) {
        const $container = $( '#lg-wd-search-results' );
        const $tbody     = $( '#lg-wd-search-results-table tbody' );
        $tbody.empty();

        if ( ! results.length ) {
            $tbody.append( '<tr><td colspan="4" style="color:#aaa;">No results found.</td></tr>' );
        } else {
            results.forEach( function ( r ) {
                // Build a dropdown of current sections to add to
                let sectionOpts = '';
                $( '#lg-wd-sections-container .lg-wd-compose-section' ).each( function () {
                    const key   = $( this ).data( 'section-key' );
                    const label = $( this ).find( '.lg-wd-compose-section-header strong' ).text();
                    sectionOpts += `<option value="${key}">${label}</option>`;
                });

                $tbody.append( `
                    <tr>
                      <td>${r.title}</td>
                      <td>${r.type_label}</td>
                      <td>${r.date}</td>
                      <td>
                        <select class="lg-wd-add-to-section" data-post-id="${r.id}" data-title="${r.title}" data-type="${r.type_label}" data-date="${r.date}">
                          ${sectionOpts}
                        </select>
                        <button class="button button-small lg-wd-add-post-btn" data-post-id="${r.id}">+ Add</button>
                      </td>
                    </tr>
                ` );
            });
        }

        $container.show();
    }

    // Add post from search results to a section
    $( document ).on( 'click', '.lg-wd-add-post-btn', function () {
        const $row      = $( this ).closest( 'tr' );
        const $select   = $row.find( '.lg-wd-add-to-section' );
        const sectionKey = $select.val();
        const postId    = parseInt( $( this ).data( 'post-id' ) );
        const title     = $select.data( 'title' );
        const typeLabel = $select.data( 'type' );
        const date      = $select.data( 'date' );

        if ( ! sectionKey ) return;

        // Find the section and add the post
        const $section = $( `.lg-wd-compose-section[data-section-key="${sectionKey}"]` );
        if ( ! $section.length ) return;

        // Check if already present
        if ( $section.find( `.lg-wd-post-item[data-post-id="${postId}"]` ).length ) {
            showResponse( 'Post already in this section.', 'error' );
            return;
        }

        // Remove empty message if present
        $section.find( '.lg-wd-empty-section' ).remove();

        // Add or create the list
        let $list = $section.find( '.lg-wd-post-list' );
        if ( ! $list.length ) {
            $section.find( '.lg-wd-compose-section-body' ).append(
                `<ul class="lg-wd-post-list" data-section-key="${sectionKey}"></ul>`
            );
            $list = $section.find( '.lg-wd-post-list' );
        }

        $list.append( `
            <li class="lg-wd-post-item" data-post-id="${postId}">
              <label class="lg-wd-post-check">
                <input type="checkbox" checked data-post-id="${postId}">
                <span class="lg-wd-post-title">${title}</span>
              </label>
              <span class="lg-wd-post-meta">
                <span class="lg-wd-post-type">${typeLabel}</span>
                <span class="lg-wd-post-date">${date}</span>
              </span>
              <button type="button" class="lg-wd-post-remove" title="Remove" data-post-id="${postId}">✕</button>
            </li>
        ` );

        updateSectionCount( $section );
        $row.fadeOut( 200 );
        showResponse( '✓ Added to ' + $section.find( 'strong' ).first().text(), 'success' );
    });

    // ── Post sortable within sections ────────────────────────────────────────

    function initPostSortable() {
        $( '.lg-wd-post-list' ).sortable({
            connectWith: '.lg-wd-post-list',
            tolerance: 'pointer',
            placeholder: 'lg-wd-post-placeholder',
        });
    }

    initPostSortable();

});
