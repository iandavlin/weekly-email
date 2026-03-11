<?php
/**
 * Plugin Name: LG Weekly Digest
 * Plugin URI:  https://loothgroup.com
 * Description: Automated weekly digest email via FluentCRM. Queries recent content across registered CPTs, forum posts, and events, then sends to the Weekly News Letter list.
 * Version:     1.0.0
 * Author:      The Looth Group
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────
define( 'LG_WD_VERSION',      '1.0.0' );
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
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-query.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-email-builder.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-sender.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-admin.php';
require_once LG_WD_PLUGIN_DIR . 'includes/class-lg-wd-cron.php';

// ─────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    LG_WD_Admin::init();
    LG_WD_Cron::init();
} );

// Activation / Deactivation
register_activation_hook( __FILE__,   [ 'LG_WD_Cron', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'LG_WD_Cron', 'deactivate' ] );
