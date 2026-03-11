<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Compose
 * Dedicated admin page for composing, previewing, and sending a weekly email issue.
 */
class LG_WD_Compose {

    const PAGE_SLUG = 'lg-wd-compose';
    const CAP       = 'manage_options';

    public static function init(): void {
        add_action( 'wp_ajax_lg_wd_compose_save',        [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_lg_wd_compose_populate',    [ __CLASS__, 'ajax_populate' ] );
        add_action( 'wp_ajax_lg_wd_compose_search',      [ __CLASS__, 'ajax_search' ] );
        add_action( 'wp_ajax_lg_wd_compose_preview',     [ __CLASS__, 'ajax_preview' ] );
        add_action( 'wp_ajax_lg_wd_compose_test_send',   [ __CLASS__, 'ajax_test_send' ] );
        add_action( 'wp_ajax_lg_wd_compose_send',        [ __CLASS__, 'ajax_send' ] );
        add_action( 'wp_ajax_lg_wd_compose_new_issue',   [ __CLASS__, 'ajax_new_issue' ] );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets(): void {
        wp_enqueue_media();

        wp_enqueue_style(
            'lg-wd-admin',
            LG_WD_PLUGIN_URL . 'assets/admin.css',
            [],
            LG_WD_VERSION
        );

        wp_enqueue_script(
            'lg-wd-compose',
            LG_WD_PLUGIN_URL . 'assets/compose.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            LG_WD_VERSION,
            true
        );

        wp_localize_script( 'lg-wd-compose', 'lgWDCompose', [
            'nonce'   => wp_create_nonce( 'lg_wd_compose' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    // ── Page render ──────────────────────────────────────────────────────────

    public static function render_page(): void {
        if ( ! current_user_can( self::CAP ) ) wp_die( 'Unauthorized' );

        self::enqueue_assets();

        // Determine which issue to load
        $issue_id = absint( $_GET['issue_id'] ?? 0 );

        if ( ! $issue_id ) {
            $issue_id = LG_WD_Issue::get_latest_draft();
        }

        $issue_data = $issue_id ? LG_WD_Issue::get_data( $issue_id ) : null;
        $issue_title = $issue_id ? get_the_title( $issue_id ) : '';
        $is_sent    = $issue_data && $issue_data['status'] === 'sent';

        // Available sections from registry for "Add Section" dropdown
        $registry = LG_WD_CPT_Registry::get_all_with_overrides();
        ?>
        <div class="wrap lg-wd-wrap">

          <div class="lg-wd-page-header">
            <div>
              <h1 class="lg-wd-title">Compose Weekly Email</h1>
              <p class="lg-wd-subtitle">
                <?php if ( $issue_id ) : ?>
                  Issue #<?php echo $issue_id; ?>
                  <?php if ( $is_sent ) : ?>
                    <span class="lg-wd-badge lg-wd-badge-sent">Sent</span>
                  <?php else : ?>
                    <span class="lg-wd-badge lg-wd-badge-draft">Draft</span>
                  <?php endif; ?>
                <?php else : ?>
                  No issue loaded
                <?php endif; ?>
              </p>
            </div>
            <div class="lg-wd-header-actions">
              <button class="button lg-wd-btn-secondary" id="lg-wd-new-issue-btn">+ New Issue</button>
            </div>
          </div>

          <!-- Response area -->
          <div id="lg-wd-response" style="display:none;" class="lg-wd-response"></div>

          <?php if ( ! $issue_id ) : ?>
            <div class="lg-wd-card">
              <div class="lg-wd-card-body" style="text-align:center;padding:40px;">
                <p style="font-size:16px;color:#5C4E3A;">No draft issue found. Create a new one to get started.</p>
                <button class="button button-primary lg-wd-btn-primary" id="lg-wd-new-issue-btn-empty">+ New Issue</button>
              </div>
            </div>
          <?php else : ?>

          <input type="hidden" id="lg-wd-issue-id" value="<?php echo $issue_id; ?>">

          <!-- Issue title + date range -->
          <div class="lg-wd-card">
            <div class="lg-wd-card-body">
              <div class="lg-wd-grid lg-wd-grid-3">
                <div class="lg-wd-form-group">
                  <label class="lg-wd-label">Issue Title</label>
                  <input type="text" id="lg-wd-issue-title"
                         value="<?php echo esc_attr( $issue_title ); ?>"
                         class="lg-wd-input" placeholder="Weekly Digest — March 11, 2026">
                </div>
                <div class="lg-wd-form-group">
                  <label class="lg-wd-label">Date From</label>
                  <input type="date" id="lg-wd-date-from"
                         value="<?php echo esc_attr( $issue_data['date_from'] ?? '' ); ?>"
                         class="lg-wd-input">
                </div>
                <div class="lg-wd-form-group">
                  <label class="lg-wd-label">Date To</label>
                  <input type="date" id="lg-wd-date-to"
                         value="<?php echo esc_attr( $issue_data['date_to'] ?? '' ); ?>"
                         class="lg-wd-input">
                </div>
              </div>
              <div style="margin-top:12px;display:flex;gap:8px;">
                <button class="button lg-wd-btn-secondary" id="lg-wd-populate-btn">
                  Auto-Populate from Date Range
                </button>
                <button class="button lg-wd-btn-secondary" id="lg-wd-save-draft-btn">
                  Save Draft
                </button>
              </div>
            </div>
          </div>

          <!-- Sections container (sortable) -->
          <div id="lg-wd-sections-container">
            <?php
            $sections = $issue_data['sections'] ?? [];
            foreach ( $sections as $idx => $section ) :
                self::render_section_card( $section, $idx );
            endforeach;
            ?>
          </div>

          <!-- Add section / Add archive post -->
          <div class="lg-wd-card" style="margin-top:16px;">
            <div class="lg-wd-card-body" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
              <div class="lg-wd-form-group" style="margin-bottom:0;">
                <label class="lg-wd-label">Add Section</label>
                <select id="lg-wd-add-section-select" class="lg-wd-select">
                  <option value="">— Choose —</option>
                  <?php foreach ( $registry as $entry ) : ?>
                    <option value="<?php echo esc_attr( $entry['slug'] ); ?>"
                            data-label="<?php echo esc_attr( $entry['label'] ); ?>"
                            data-template="<?php echo esc_attr( $entry['template'] ?? 'card' ); ?>">
                      <?php echo esc_html( $entry['label'] ); ?> (<?php echo esc_html( $entry['slug'] ); ?>)
                    </option>
                  <?php endforeach; ?>
                  <option value="__custom__">+ Custom Section…</option>
                </select>
              </div>
              <button class="button" id="lg-wd-add-section-btn">Add Section</button>

              <div style="margin-left:auto;display:flex;gap:8px;align-items:flex-end;">
                <div class="lg-wd-form-group" style="margin-bottom:0;">
                  <label class="lg-wd-label">Search Posts</label>
                  <input type="text" id="lg-wd-archive-search" class="lg-wd-input"
                         placeholder="Type to search all CPTs…" style="width:250px;">
                </div>
                <button class="button" id="lg-wd-archive-search-btn">Search</button>
              </div>
            </div>

            <!-- Search results -->
            <div id="lg-wd-search-results" style="display:none;padding:0 16px 16px;">
              <table class="widefat striped" id="lg-wd-search-results-table">
                <thead>
                  <tr><th>Title</th><th>Type</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <!-- Action bar -->
          <div class="lg-wd-card" style="margin-top:16px;">
            <div class="lg-wd-card-body" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <button class="button lg-wd-btn-secondary" id="lg-wd-preview-btn">Preview Email</button>

              <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
                <input type="email" id="lg-wd-test-email" placeholder="test@email.com"
                       value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
                       class="lg-wd-input-sm">
                <button class="button lg-wd-btn-secondary" id="lg-wd-test-btn">Send Test</button>
                <?php if ( ! $is_sent ) : ?>
                  <button class="button lg-wd-btn-danger" id="lg-wd-send-btn">Send Now</button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php endif; ?>

        </div><!-- /.wrap -->

        <!-- Preview modal -->
        <div id="lg-wd-preview-modal" style="display:none;">
          <div class="lg-wd-modal-overlay"></div>
          <div class="lg-wd-modal-inner">
            <div class="lg-wd-modal-header">
              <strong>Email Preview</strong>
              <button class="lg-wd-modal-close">&times;</button>
            </div>
            <div class="lg-wd-modal-body">
              <iframe id="lg-wd-preview-frame" src="" style="width:100%;height:100%;border:none;"></iframe>
            </div>
          </div>
        </div>

        <!-- Custom section modal -->
        <div id="lg-wd-custom-section-modal" style="display:none;">
          <div class="lg-wd-modal-overlay"></div>
          <div class="lg-wd-modal-inner" style="max-width:400px;">
            <div class="lg-wd-modal-header">
              <strong>Add Custom Section</strong>
              <button class="lg-wd-modal-close">&times;</button>
            </div>
            <div class="lg-wd-modal-body" style="padding:20px;">
              <div class="lg-wd-form-group">
                <label class="lg-wd-label">Section Label</label>
                <input type="text" id="lg-wd-custom-label" class="lg-wd-input" placeholder="e.g. From the Archive">
              </div>
              <div class="lg-wd-form-group">
                <label class="lg-wd-label">Key (slug)</label>
                <input type="text" id="lg-wd-custom-key" class="lg-wd-input" placeholder="e.g. from_archive">
              </div>
              <button class="button button-primary" id="lg-wd-custom-section-add">Add</button>
            </div>
          </div>
        </div>
        <?php
    }

    // ── Section card renderer (used in initial render + AJAX) ────────────────

    public static function render_section_card( array $section, int $idx ): void {
        $key      = esc_attr( $section['key'] ?? '' );
        $label    = esc_html( $section['label'] ?? '' );
        $template = esc_attr( $section['template'] ?? 'card' );
        $slug     = esc_attr( $section['slug'] ?? '' );
        $post_ids = $section['post_ids'] ?? [];
        ?>
        <div class="lg-wd-compose-section" data-section-key="<?php echo $key; ?>" data-section-template="<?php echo $template; ?>" data-section-slug="<?php echo $slug; ?>">
          <div class="lg-wd-compose-section-header">
            <span class="lg-wd-drag-handle" title="Drag to reorder">⠿</span>
            <strong><?php echo $label; ?></strong>
            <span class="lg-wd-section-type-badge"><?php echo esc_html( $template ); ?></span>
            <span class="lg-wd-section-count"><?php echo count( $post_ids ); ?> items</span>
            <button type="button" class="button button-small lg-wd-remove-section-btn" title="Remove section">✕</button>
          </div>
          <div class="lg-wd-compose-section-body">
            <?php if ( empty( $post_ids ) ) : ?>
              <p class="lg-wd-empty-section">No posts in this section.</p>
            <?php else : ?>
              <ul class="lg-wd-post-list" data-section-key="<?php echo $key; ?>">
                <?php foreach ( $post_ids as $pid ) :
                    $post = get_post( $pid );
                    if ( ! $post ) continue;
                    $title     = esc_html( get_the_title( $post ) );
                    $date      = get_the_date( 'M j', $post );
                    $cpt_label = esc_html( self::cpt_label( $post->post_type ) );
                    ?>
                    <li class="lg-wd-post-item" data-post-id="<?php echo $pid; ?>">
                      <label class="lg-wd-post-check">
                        <input type="checkbox" checked data-post-id="<?php echo $pid; ?>">
                        <span class="lg-wd-post-title"><?php echo $title; ?></span>
                      </label>
                      <span class="lg-wd-post-meta">
                        <span class="lg-wd-post-type"><?php echo $cpt_label; ?></span>
                        <span class="lg-wd-post-date"><?php echo esc_html( $date ); ?></span>
                      </span>
                      <button type="button" class="lg-wd-post-remove" title="Remove" data-post-id="<?php echo $pid; ?>">✕</button>
                    </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }

    // ── AJAX: Save draft ────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $issue_id = absint( $_POST['issue_id'] ?? 0 );
        if ( ! $issue_id ) wp_send_json_error( 'No issue ID.' );

        // Update title
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        if ( $title ) {
            wp_update_post( [ 'ID' => $issue_id, 'post_title' => $title ] );
        }

        // Build sections from POST data
        $raw_sections = json_decode( stripslashes( $_POST['sections'] ?? '[]' ), true );
        if ( ! is_array( $raw_sections ) ) $raw_sections = [];

        $sections = [];
        foreach ( $raw_sections as $s ) {
            $sections[] = [
                'key'      => sanitize_key( $s['key'] ?? '' ),
                'label'    => sanitize_text_field( $s['label'] ?? '' ),
                'slug'     => sanitize_text_field( $s['slug'] ?? '' ),
                'template' => sanitize_key( $s['template'] ?? 'card' ),
                'post_ids' => array_map( 'absint', $s['post_ids'] ?? [] ),
            ];
        }

        $data = LG_WD_Issue::get_data( $issue_id );
        $data['date_from'] = sanitize_text_field( $_POST['date_from'] ?? $data['date_from'] );
        $data['date_to']   = sanitize_text_field( $_POST['date_to'] ?? $data['date_to'] );
        $data['sections']  = $sections;

        LG_WD_Issue::save_data( $issue_id, $data );

        wp_send_json_success( [ 'message' => 'Draft saved.' ] );
    }

    // ── AJAX: Auto-populate ─────────────────────────────────────────────────

    public static function ajax_populate(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_POST['date_to'] ?? '' );

        if ( ! $date_from || ! $date_to ) {
            wp_send_json_error( 'Please set both dates.' );
        }

        $sections = LG_WD_Issue::auto_populate( $date_from, $date_to );

        // Render HTML for each section card
        ob_start();
        foreach ( $sections as $idx => $section ) {
            self::render_section_card( $section, $idx );
        }
        $html = ob_get_clean();

        wp_send_json_success( [
            'html'     => $html,
            'sections' => $sections,
            'message'  => count( $sections ) . ' sections populated.',
        ] );
    }

    // ── AJAX: Search posts ──────────────────────────────────────────────────

    public static function ajax_search(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $term = sanitize_text_field( $_POST['term'] ?? '' );
        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( 'Search term too short.' );
        }

        $results = LG_WD_Query::search_posts( $term );
        wp_send_json_success( [ 'results' => $results ] );
    }

    // ── AJAX: Preview ───────────────────────────────────────────────────────

    public static function ajax_preview(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $issue_id = absint( $_POST['issue_id'] ?? 0 );
        if ( ! $issue_id ) wp_send_json_error( 'No issue ID.' );

        // Save current state first
        self::save_from_post( $issue_id );

        $result = LG_WD_Sender::send_issue( $issue_id, true );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'html' => $result['html'], 'subject' => $result['subject'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    // ── AJAX: Test send ─────────────────────────────────────────────────────

    public static function ajax_test_send(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $issue_id = absint( $_POST['issue_id'] ?? 0 );
        $to       = sanitize_email( $_POST['to'] ?? '' );

        if ( ! $issue_id ) wp_send_json_error( 'No issue ID.' );
        if ( ! is_email( $to ) ) wp_send_json_error( 'Invalid email.' );

        self::save_from_post( $issue_id );

        $result = LG_WD_Sender::send_issue( $issue_id, false, $to );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    // ── AJAX: Send now ──────────────────────────────────────────────────────

    public static function ajax_send(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $issue_id = absint( $_POST['issue_id'] ?? 0 );
        if ( ! $issue_id ) wp_send_json_error( 'No issue ID.' );

        self::save_from_post( $issue_id );

        $result = LG_WD_Sender::send_issue( $issue_id );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    // ── AJAX: New issue ─────────────────────────────────────────────────────

    public static function ajax_new_issue(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $issue_id = LG_WD_Issue::create();
        if ( ! $issue_id ) {
            wp_send_json_error( 'Failed to create issue.' );
        }

        wp_send_json_success( [
            'issue_id' => $issue_id,
            'redirect' => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&issue_id=' . $issue_id ),
        ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Save the current form state from $_POST into the issue.
     */
    private static function save_from_post( int $issue_id ): void {
        if ( isset( $_POST['sections'] ) ) {
            $raw_sections = json_decode( stripslashes( $_POST['sections'] ), true );
            if ( is_array( $raw_sections ) ) {
                $data = LG_WD_Issue::get_data( $issue_id );
                $data['sections'] = [];
                foreach ( $raw_sections as $s ) {
                    $data['sections'][] = [
                        'key'      => sanitize_key( $s['key'] ?? '' ),
                        'label'    => sanitize_text_field( $s['label'] ?? '' ),
                        'slug'     => sanitize_text_field( $s['slug'] ?? '' ),
                        'template' => sanitize_key( $s['template'] ?? 'card' ),
                        'post_ids' => array_map( 'absint', $s['post_ids'] ?? [] ),
                    ];
                }
                if ( ! empty( $_POST['date_from'] ) ) {
                    $data['date_from'] = sanitize_text_field( $_POST['date_from'] );
                }
                if ( ! empty( $_POST['date_to'] ) ) {
                    $data['date_to'] = sanitize_text_field( $_POST['date_to'] );
                }
                LG_WD_Issue::save_data( $issue_id, $data );
            }
        }
    }

    private static function cpt_label( string $slug ): string {
        $obj = get_post_type_object( $slug );
        return $obj ? ( $obj->labels->singular_name ?? ucfirst( $slug ) ) : ucfirst( $slug );
    }
}
