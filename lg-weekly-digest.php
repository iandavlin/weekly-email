<?php
/**
 * Plugin Name: LG Weekly Digest
 * Plugin URI:  https://loothgroup.com
 * Description: Curated weekly digest email with pluggable sender support. Compose issues from any registered CPT, preview inline, and send via FluentCRM or wp_mail.
 * Version:     2.0.0
 * Author:      The Looth Group
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────
define( 'LG_WD_VERSION',      '2.0.0' );
define( 'LG_WD_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'LG_WD_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );
define( 'LG_WD_OPTION_KEY',   'lg_wd_settings' );

// FluentCRM targets
define( 'LG_WD_FCRM_LIST_ID', 3 );   // Weekly News Letter
define( 'LG_WD_FCRM_TAG',     'all' );
define( 'LG_WD_TIMEZONE',     'America/New_York' );

// ─────────────────────────────────────────────
// Includes
// ─────────────────────────────────────────────
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-settings.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-cpt-registry.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-issue.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-query.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-email-builder.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-sender.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-admin.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-compose.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-cron.php';

// ─────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    LG_WD_Issue::init();
    LG_WD_Admin::init();
    LG_WD_Compose::init();
    LG_WD_Cron::init();
} );

// Activation / Deactivation
register_activation_hook( __FILE__,   [ 'LG_WD_Cron', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'LG_WD_Cron', 'deactivate' ] );
