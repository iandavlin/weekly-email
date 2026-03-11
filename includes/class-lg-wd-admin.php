<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Admin
 * Admin menu registration + Settings/Registry/Design/History pages.
 * The compose workflow lives in LG_WD_Compose.
 */
class LG_WD_Admin {

    const PAGE_SLUG = 'lg-weekly-digest';
    const CAP       = 'manage_options';

    public static function init(): void {
        add_action( 'admin_menu',             [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_lg_wd_save',     [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_lg_wd_registry_add',   [ __CLASS__, 'ajax_registry_add' ] );
        add_action( 'wp_ajax_lg_wd_registry_remove', [ __CLASS__, 'ajax_registry_remove' ] );
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

        // First submenu replaces the top-level duplicate
        add_submenu_page(
            self::PAGE_SLUG,
            'Settings',
            'Settings',
            self::CAP,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );

        add_submenu_page(
            self::PAGE_SLUG,
            'Compose',
            'Compose',
            self::CAP,
            LG_WD_Compose::PAGE_SLUG,
            [ 'LG_WD_Compose', 'render_page' ]
        );

        add_submenu_page(
            self::PAGE_SLUG,
            'All Issues',
            'All Issues',
            self::CAP,
            'edit.php?post_type=' . LG_WD_Issue::POST_TYPE
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) return;

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
            echo '<div class="notice notice-warning"><p><strong>LG Weekly Digest:</strong> FluentCRM not detected. Will fall back to wp_mail for sends.</p></div>';
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
            'registry' => 'CPT Registry',
            'design'   => 'Email Design',
            'history'  => 'Send History',
        ];
        ?>
        <div class="wrap lg-wd-wrap">

          <div class="lg-wd-page-header">
            <div>
              <h1 class="lg-wd-title">Weekly Digest Settings</h1>
              <p class="lg-wd-subtitle">
                Sender: <strong><?php echo esc_html( LG_WD_Sender::get_sender()->get_label() ); ?></strong>
              </p>
            </div>
            <div class="lg-wd-header-actions">
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LG_WD_Compose::PAGE_SLUG ) ); ?>"
                 class="button button-primary lg-wd-btn-primary">Go to Compose</a>
              <button class="button lg-wd-btn-secondary" id="lg-wd-save-btn">Save Changes</button>
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
                case 'registry': self::render_tab_registry(); break;
                case 'design':   self::render_tab_design( $settings ); break;
                case 'history':  self::render_tab_history( $history ); break;
                default:         self::render_tab_settings( $settings ); break;
            }
            ?>
          </form>

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
                <label class="lg-wd-label">Subject Line Template</label>
                <input type="text" name="subject_template"
                       value="<?php echo esc_attr( $s['subject_template'] ); ?>"
                       class="lg-wd-input">
                <p class="lg-wd-hint">Tokens: <code>{{week_date}}</code> <code>{{site_name}}</code> <code>{{item_count}}</code></p>
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

    // ── Tab: CPT Registry ────────────────────────────────────────────────────

    private static function render_tab_registry(): void {
        $registry = LG_WD_CPT_Registry::get_all_with_overrides();
        $wp_cpts  = LG_WD_CPT_Registry::get_available_post_types();
        ?>
        <div class="lg-wd-card">
          <div class="lg-wd-card-header">
            <h3>Registered Content Types</h3>
            <span class="lg-wd-hint">These appear as available sections when composing an email.</span>
          </div>
          <div class="lg-wd-card-body">
            <table class="widefat striped" id="lg-wd-registry-table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Slug</th>
                  <th>Type</th>
                  <th>Max Items</th>
                  <th>Enabled</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $registry as $entry ) :
                    $is_builtin = ! empty( $entry['builtin'] );
                    ?>
                    <tr data-slug="<?php echo esc_attr( $entry['slug'] ); ?>">
                      <td><?php echo esc_html( $entry['label'] ); ?></td>
                      <td><code><?php echo esc_html( $entry['slug'] ); ?></code></td>
                      <td><?php echo esc_html( ucfirst( $entry['type'] ) ); ?></td>
                      <td><?php echo (int) $entry['max_items']; ?></td>
                      <td><?php echo $entry['enabled'] ? 'Yes' : 'No'; ?></td>
                      <td>
                        <?php if ( $is_builtin ) : ?>
                          <span class="lg-wd-section-type-badge">Built-in</span>
                        <?php else : ?>
                          <button class="button button-small lg-wd-registry-remove" data-slug="<?php echo esc_attr( $entry['slug'] ); ?>">Remove</button>
                        <?php endif; ?>
                      </td>
                    </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #eee;">
              <h4 style="margin:0 0 10px;">Add New Content Type</h4>
              <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                <div class="lg-wd-form-group" style="margin-bottom:0;">
                  <label class="lg-wd-label">Post Type</label>
                  <select id="lg-wd-reg-slug" class="lg-wd-select">
                    <option value="">— Select —</option>
                    <?php foreach ( $wp_cpts as $slug => $label ) : ?>
                      <option value="<?php echo esc_attr( $slug ); ?>">
                        <?php echo esc_html( $label ); ?> (<?php echo esc_html( $slug ); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="lg-wd-form-group" style="margin-bottom:0;">
                  <label class="lg-wd-label">Display Label</label>
                  <input type="text" id="lg-wd-reg-label" class="lg-wd-input" placeholder="e.g. Videos">
                </div>
                <div class="lg-wd-form-group" style="margin-bottom:0;">
                  <label class="lg-wd-label">Max Items</label>
                  <input type="number" id="lg-wd-reg-max" class="lg-wd-input" value="5" min="1" max="20" style="width:60px;">
                </div>
                <button class="button button-primary" id="lg-wd-reg-add-btn">+ Add</button>
              </div>
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
                    <th>Title</th>
                    <th>Campaign ID</th>
                    <th>Result</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ( $history as $row ) : ?>
                    <tr>
                      <td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $row['sent_at'] ) ) ); ?></td>
                      <td><?php echo esc_html( $row['title'] ); ?></td>
                      <td>
                        <?php if ( ! empty( $row['campaign_id'] ) ) : ?>
                          <a href="<?php echo esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/campaigns/' . $row['campaign_id'] ) ); ?>">
                            #<?php echo (int) $row['campaign_id']; ?>
                          </a>
                        <?php else : ?>—<?php endif; ?>
                      </td>
                      <td><?php echo esc_html( $row['message'] ?? '' ); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
    <?php }

    // ── AJAX: Save settings ──────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $raw = $_POST;

        $data = [
            'enabled'          => ! empty( $raw['enabled'] ),
            'send_day'         => sanitize_key( $raw['send_day'] ?? 'monday' ),
            'send_time'        => sanitize_text_field( $raw['send_time'] ?? '09:00' ),
            'header_image_url' => esc_url_raw( $raw['header_image_url'] ?? '' ),
            'from_name'        => sanitize_text_field( $raw['from_name'] ?? '' ),
            'from_email'       => sanitize_email( $raw['from_email'] ?? '' ),
            'subject_template' => sanitize_text_field( $raw['subject_template'] ?? '' ),
            'signoff'          => sanitize_textarea_field( $raw['signoff'] ?? '' ),
            'show_excerpts'    => ! empty( $raw['show_excerpts'] ),
            'show_thumbnails'  => ! empty( $raw['show_thumbnails'] ),
            'skip_empty'       => ! empty( $raw['skip_empty'] ),
        ];

        LG_WD_Settings::save( $data );
        do_action( 'lg_wd_settings_saved' );

        wp_send_json_success( [ 'message' => 'Settings saved. Cron rescheduled.' ] );
    }

    // ── AJAX: Registry add ──────────────────────────────────────────────────

    public static function ajax_registry_add(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $entry = [
            'slug'      => sanitize_key( $_POST['slug'] ?? '' ),
            'label'     => sanitize_text_field( $_POST['label'] ?? '' ),
            'type'      => 'cpt',
            'max_items' => absint( $_POST['max_items'] ?? 5 ),
            'enabled'   => true,
        ];

        if ( LG_WD_CPT_Registry::add( $entry ) ) {
            wp_send_json_success( [ 'message' => 'Content type registered.' ] );
        } else {
            wp_send_json_error( 'Failed to add. Slug may already exist or be empty.' );
        }
    }

    // ── AJAX: Registry remove ───────────────────────────────────────────────

    public static function ajax_registry_remove(): void {
        check_ajax_referer( 'lg_wd_admin', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );

        $slug = sanitize_key( $_POST['slug'] ?? '' );

        if ( LG_WD_CPT_Registry::remove( $slug ) ) {
            wp_send_json_success( [ 'message' => 'Content type removed.' ] );
        } else {
            wp_send_json_error( 'Cannot remove built-in type or slug not found.' );
        }
    }
}
