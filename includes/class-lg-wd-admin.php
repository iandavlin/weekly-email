<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Admin
 * Registers the admin menu page and handles all admin UI + form saves.
 */
class LG_WD_Admin {

    const PAGE_SLUG = 'lg-weekly-digest';
    const CAP       = 'manage_options';

    public static function init(): void {
        add_action( 'admin_menu',             [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_lg_wd_save',     [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_lg_wd_test_send',[ __CLASS__, 'ajax_test_send' ] );
        add_action( 'wp_ajax_lg_wd_send_now', [ __CLASS__, 'ajax_send_now' ] );
        add_action( 'wp_ajax_lg_wd_preview',  [ __CLASS__, 'ajax_preview' ] );
        add_action( 'admin_notices',          [ __CLASS__, 'admin_notices' ] );
    }

    // ── Menu ─────────────────────────────────────────────────────────────────

    public static function register_menu(): void {
        add_menu_page(
            'Weekly Digest',
            'Weekly Digest',
            self::CAP,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ],
            'dashicons-email-alt',
            30
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) return;

        // Media uploader for header image
        wp_enqueue_media();

        wp_enqueue_style(
            'lg-wd-admin',
            LG_WD_PLUGIN_URL . 'assets/admin.css',
            [],
            LG_WD_VERSION
        );

        wp_enqueue_script(
            'lg-wd-admin',
            LG_WD_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            LG_WD_VERSION,
            true
        );

        wp_localize_script( 'lg-wd-admin', 'lgWD', [
            'nonce'     => wp_create_nonce( 'lg_wd_admin' ),
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'adminEmail'=> get_option( 'admin_email' ),
        ] );
    }

    // ── Notices ───────────────────────────────────────────────────────────────

    public static function admin_notices(): void {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, self::PAGE_SLUG ) === false ) return;

        if ( ! class_exists( 'FluentCrm\App\Models\Campaign' ) ) {
            echo '<div class="notice notice-error"><p><strong>LG Weekly Digest:</strong> FluentCRM is not active. The digest cannot send without it.</p></div>';
        }
    }

    // ── Main page render ─────────────────────────────────────────────────────

    public static function render_page(): void {
        if ( ! current_user_can( self::CAP ) ) wp_die( 'Unauthorized' );

        $settings    = LG_WD_Settings::get_all();
        $next_send   = LG_WD_Cron::next_send_label();
        $history     = array_reverse( get_option( 'lg_wd_send_history', [] ) );
        $active_tab  = sanitize_key( $_GET['tab'] ?? 'settings' );

        $tabs = [
            'settings' => 'Settings',
            'sections' => 'Content Sections',
            'design'   => 'Email Design',
            'history'  => 'Send History',
        ];
        ?>
        <div class="wrap lg-wd-wrap">

          <div class="lg-wd-page-header">
            <div>
              <h1 class="lg-wd-title">Weekly Digest</h1>
              <p class="lg-wd-subtitle">Automated email · List ID <?php echo LG_WD_FCRM_LIST_ID; ?></p>
            </div>
            <div class="lg-wd-header-actions">
              <button class="button lg-wd-btn-secondary" id="lg-wd-preview-btn">Preview Email</button>
              <button class="button button-primary lg-wd-btn-primary" id="lg-wd-save-btn">Save Changes</button>
            </div>
          </div>

          <!-- Status Bar -->
          <div class="lg-wd-status-bar">
            <div class="lg-wd-status-item">
              <span class="lg-wd-dot <?php echo $settings['enabled'] ? 'green' : 'off'; ?>"></span>
              Digest is <strong><?php echo $settings['enabled'] ? 'Active' : 'Paused'; ?></strong>
            </div>
            <div class="lg-wd-status-divider"></div>
            <div class="lg-wd-status-item">
              <span class="lg-wd-dot gold"></span>
              Next send: <strong><?php echo esc_html( $next_send ); ?></strong>
            </div>
            <div class="lg-wd-status-divider"></div>
            <div class="lg-wd-status-item">
              <?php
              $last = ! empty( $history ) ? $history[0] : null;
              echo $last
                  ? 'Last sent: <strong>' . esc_html( date( 'M j, Y', strtotime( $last['sent_at'] ) ) ) . '</strong>'
                  : '<span style="color:#aaa;">No sends yet</span>';
              ?>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;">
              <input type="email" id="lg-wd-test-email" placeholder="test@email.com"
                     value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
                     class="lg-wd-input-sm">
              <button class="button lg-wd-btn-secondary" id="lg-wd-test-btn">Send Test</button>
              <button class="button lg-wd-btn-danger" id="lg-wd-send-now-btn">Send Now</button>
            </div>
          </div>

          <!-- Response area -->
          <div id="lg-wd-response" style="display:none;" class="lg-wd-response"></div>

          <!-- Tabs -->
          <nav class="nav-tab-wrapper lg-wd-tabs">
            <?php foreach ( $tabs as $slug => $label ) : ?>
              <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=<?php echo $slug; ?>"
                 class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
              </a>
            <?php endforeach; ?>
          </nav>

          <form id="lg-wd-form">
            <?php wp_nonce_field( 'lg_wd_admin', 'lg_wd_nonce' ); ?>

            <?php
            switch ( $active_tab ) {
                case 'sections': self::render_tab_sections( $settings ); break;
                case 'design':   self::render_tab_design( $settings );   break;
                case 'history':  self::render_tab_history( $history );   break;
                default:         self::render_tab_settings( $settings ); break;
            }
            ?>
          </form>

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
        <?php
    }

    // ── Tab: Settings ─────────────────────────────────────────────────────────

    private static function render_tab_settings( array $s ): void { ?>
        <div class="lg-wd-grid lg-wd-grid-2">

          <div class="lg-wd-card">
            <div class="lg-wd-card-header"><h3>Schedule</h3></div>
            <div class="lg-wd-card-body">

              <div class="lg-wd-form-row">
                <label class="lg-wd-label">Digest Enabled</label>
                <label class="lg-wd-toggle">
                  <input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>>
                  <span class="lg-wd-toggle-track"></span>
                </label>
              </div>

              <div class="lg-wd-grid lg-wd-grid-2">
                <div class="lg-wd-form-group">
                  <label class="lg-wd-label">Send Day</label>
                  <select name="send_day" class="lg-wd-select">
                    <?php foreach ( [ 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' ] as $d ) : ?>
                      <option value="<?php echo $d; ?>" <?php selected( $s['send_day'], $d ); ?>>
                        <?php echo ucfirst( $d ); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="lg-wd-form-group">
                  <label class="lg-wd-label">Send Time <span class="lg-wd-hint">(ET)</span></label>
                  <input type="time" name="send_time" value="<?php echo esc_attr( $s['send_time'] ); ?>"
                         class="lg-wd-input">
                </div>
              </div>

              <div class="lg-wd-form-group">
                <label class="lg-wd-label">Content Lookback Window</label>
                <select name="lookback_days" class="lg-wd-select">
                  <?php foreach ( [ 7 => '7 days (standard)', 14 => '14 days', 30 => '30 days' ] as $val => $lbl ) : ?>
                    <option value="<?php echo $val; ?>" <?php selected( $s['lookback_days'], $val ); ?>>
                      <?php echo $lbl; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="lg-wd-hint">If no new content found within this window, falls back to archive.</p>
              </div>

              <div class="lg-wd-form-group">
                <label class="lg-wd-label">Subject Line Template</label>
                <input type="text" name="subject_template"
                       value="<?php echo esc_attr( $s['subject_template'] ); ?>"
                       class="lg-wd-input">
                <p class="lg-wd-hint">Available tokens: <code>{{week_date}}</code> <code>{{site_name}}</code> <code>{{item_count}}</code></p>
              </div>

            </div>
          </div>

          <div class="lg-wd-card">
            <div class="lg-wd-card-header"><h3>Display Options</h3></div>
            <div class="lg-wd-card-body">

              <?php
              $toggles = [
                  'show_excerpts'   => [ 'Show Excerpts',         'Append post excerpt below each title' ],
                  'show_thumbnails' => [ 'Show Featured Images',  'Thumbnail alongside each post item' ],
                  'skip_empty'      => [ 'Skip Empty Sections',   'Hide sections that have 0 content items' ],
              ];
              foreach ( $toggles as $key => [ $label, $hint ] ) : ?>
                <div class="lg-wd-toggle-row">
                  <div>
                    <div class="lg-wd-toggle-label"><?php echo esc_html( $label ); ?></div>
                    <div class="lg-wd-hint"><?php echo esc_html( $hint ); ?></div>
                  </div>
                  <label class="lg-wd-toggle">
                    <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked( $s[ $key ] ); ?>>
                    <span class="lg-wd-toggle-track"></span>
                  </label>
                </div>
              <?php endforeach; ?>

            </div>
          </div>

          <div class="lg-wd-card">
            <div class="lg-wd-card-header"><h3>From Details</h3></div>
            <div class="lg-wd-card-body">
              <div class="lg-wd-form-group">
                <label class="lg-wd-label">From Name</label>
                <input type="text" name="from_name"
                       value="<?php echo esc_attr( $s['from_name'] ); ?>"
                       class="lg-wd-input">
              </div>
              <div class="lg-wd-form-group">
                <label class="lg-wd-label">From Email</label>
                <input type="email" name="from_email"
                       value="<?php echo esc_attr( $s['from_email'] ); ?>"
                       class="lg-wd-input">
              </div>
            </div>
          </div>

        </div>
    <?php }

    // ── Tab: Content Sections ─────────────────────────────────────────────────

    private static function render_tab_sections( array $s ): void {
        $sections = $s['sections'] ?? [];
        ?>
        <div class="lg-wd-card">
          <div class="lg-wd-card-header">
            <h3>Content Sections</h3>
            <span class="lg-wd-hint">Drag to reorder · toggle to include/exclude</span>
          </div>
          <div class="lg-wd-card-body">
            <ul id="lg-wd-sections-list" class="lg-wd-sections-list">
              <?php foreach ( $sections as $i => $sec ) :
                $key    = esc_attr( $sec['key'] );
                $label  = esc_attr( $sec['label'] );
                $type   = esc_attr( $sec['type'] );
                $slug   = esc_attr( $sec['slug'] );
                $max    = (int) $sec['max_items'];
                $on     = ! empty( $sec['enabled'] );
                ?>
                <li class="lg-wd-section-item" data-index="<?php echo $i; ?>">
                  <span class="lg-wd-drag-handle" title="Drag to reorder">⠿</span>

                  <input type="hidden" name="sections[<?php echo $i; ?>][key]"   value="<?php echo $key; ?>">
                  <input type="hidden" name="sections[<?php echo $i; ?>][type]"  value="<?php echo $type; ?>">

                  <label class="lg-wd-toggle">
                    <input type="checkbox" name="sections[<?php echo $i; ?>][enabled]"
                           value="1" <?php checked( $on ); ?>>
                    <span class="lg-wd-toggle-track"></span>
                  </label>

                  <div class="lg-wd-section-info">
                    <input type="text" name="sections[<?php echo $i; ?>][label]"
                           value="<?php echo $label; ?>"
                           class="lg-wd-section-label-input"
                           placeholder="Section label">
                    <input type="text" name="sections[<?php echo $i; ?>][slug]"
                           value="<?php echo $slug; ?>"
                           class="lg-wd-section-slug-input"
                           placeholder="CPT slug(s) comma-separated">
                  </div>

                  <div class="lg-wd-section-max">
                    <label>Max</label>
                    <input type="number" name="sections[<?php echo $i; ?>][max_items]"
                           value="<?php echo $max; ?>" min="1" max="20" style="width:50px;">
                  </div>

                  <?php if ( ! in_array( $type, [ 'events', 'forum', 'spotlight', 'sponsor' ], true ) ) : ?>
                    <button type="button" class="button button-small lg-wd-remove-section"
                            title="Remove section">✕</button>
                  <?php else : ?>
                    <span class="lg-wd-section-type-badge"><?php echo ucfirst( $type ); ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="lg-wd-add-section">
              <input type="text" id="lg-wd-new-label" placeholder="Section label (e.g. Loothprints)">
              <input type="text" id="lg-wd-new-slug"  placeholder="CPT slug (e.g. loothprints)">
              <input type="number" id="lg-wd-new-max" placeholder="Max" value="3" min="1" max="20" style="width:60px;">
              <button type="button" class="button" id="lg-wd-add-section-btn">+ Add Section</button>
            </div>
          </div>
        </div>
    <?php }

    // ── Tab: Email Design ─────────────────────────────────────────────────────

    private static function render_tab_design( array $s ): void { ?>
        <div class="lg-wd-grid lg-wd-grid-2">

          <div class="lg-wd-card">
            <div class="lg-wd-card-header"><h3>Header Image</h3></div>
            <div class="lg-wd-card-body">
              <?php if ( ! empty( $s['header_image_url'] ) ) : ?>
                <div class="lg-wd-current-img">
                  <img src="<?php echo esc_url( $s['header_image_url'] ); ?>"
                       style="max-height:60px;max-width:100%;display:block;">
                  <button type="button" class="button button-small" id="lg-wd-remove-img">Remove</button>
                </div>
              <?php endif; ?>
              <input type="hidden" name="header_image_url" id="lg-wd-header-img-url"
                     value="<?php echo esc_attr( $s['header_image_url'] ); ?>">
              <button type="button" class="button" id="lg-wd-choose-img">
                <?php echo empty( $s['header_image_url'] ) ? 'Choose Header Image' : 'Change Image'; ?>
              </button>
              <p class="lg-wd-hint">Recommended width: 600px. Leave empty to use text logo.</p>
            </div>
          </div>

          <div class="lg-wd-card">
            <div class="lg-wd-card-header"><h3>Sign-off Message</h3></div>
            <div class="lg-wd-card-body">
              <textarea name="signoff" rows="4" class="lg-wd-textarea"><?php
                echo esc_textarea( $s['signoff'] );
              ?></textarea>
              <p class="lg-wd-hint">Appears at the bottom of every digest above the unsubscribe link.</p>
            </div>
          </div>

        </div>
    <?php }

    // ── Tab: Send History ─────────────────────────────────────────────────────

    private static function render_tab_history( array $history ): void { ?>
        <div class="lg-wd-card">
          <div class="lg-wd-card-header"><h3>Send History</h3></div>
          <div class="lg-wd-card-body">
            <?php if ( empty( $history ) ) : ?>
              <p style="color:#aaa;">No sends recorded yet.</p>
            <?php else : ?>
              <table class="widefat striped">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Campaign</th>
                    <th>Campaign ID</th>
                    <th>Recipients</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ( $history as $row ) : ?>
                    <tr>
                      <td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $row['sent_at'] ) ) ); ?></td>
                      <td><?php echo esc_html( $row['title'] ); ?></td>
                      <td>
                        <?php if ( $row['campaign_id'] ) : ?>
                          <a href="<?php echo esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/campaigns/' . $row['campaign_id'] ) ); ?>">
                            #<?php echo (int) $row['campaign_id']; ?>
                          </a>
                        <?php else : ?>—<?php endif; ?>
                      </td>
                      <td><?php echo number_format( (int) $row['recipients'] ); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
    <?php }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $raw = $_POST;

        $data = [
            'enabled'          => ! empty( $raw['enabled'] ),
            'send_day'         => sanitize_key( $raw['send_day'] ?? 'monday' ),
            'send_time'        => sanitize_text_field( $raw['send_time'] ?? '09:00' ),
            'lookback_days'    => absint( $raw['lookback_days'] ?? 7 ),
            'header_image_url' => esc_url_raw( $raw['header_image_url'] ?? '' ),
            'from_name'        => sanitize_text_field( $raw['from_name'] ?? '' ),
            'from_email'       => sanitize_email( $raw['from_email'] ?? '' ),
            'subject_template' => sanitize_text_field( $raw['subject_template'] ?? '' ),
            'signoff'          => sanitize_textarea_field( $raw['signoff'] ?? '' ),
            'show_excerpts'    => ! empty( $raw['show_excerpts'] ),
            'show_thumbnails'  => ! empty( $raw['show_thumbnails'] ),
            'skip_empty'       => ! empty( $raw['skip_empty'] ),
        ];

        if ( isset( $raw['sections'] ) ) {
            $data['sections'] = $raw['sections'];
        }

        LG_WD_Settings::save( $data );
        do_action( 'lg_wd_settings_saved' ); // triggers reschedule

        wp_send_json_success( [ 'message' => 'Settings saved. Cron rescheduled.' ] );
    }

    public static function ajax_test_send(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $to = sanitize_email( $_POST['to'] ?? get_option( 'admin_email' ) );
        if ( ! is_email( $to ) ) {
            wp_send_json_error( 'Invalid email address.' );
        }

        $result = LG_WD_Sender::send( false, $to );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public static function ajax_send_now(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $result = LG_WD_Sender::send();
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public static function ajax_preview(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $result = LG_WD_Sender::send( true ); // dry run
        if ( $result['success'] ) {
            wp_send_json_success( [ 'html' => $result['html'], 'subject' => $result['subject'] ] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }
}
