<?php
/**
 * Plugin Name: Cities of Darkness - FangCoin
 * Description: A fictional cryptocurrency system for the Cities of Darkness LARP chronicle. Provides digital wallets, peer-to-peer transfers, and QR-coded physical coins.
 * Version: 1.4.4
 * Author: Cities of Darkness
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: cities-of-darkness-chronicle-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FANGCOIN_VERSION', '1.4.4' );
define( 'FANGCOIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FANGCOIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check that Chronicle Tools is active before loading.
 */
function fangcoin_check_dependencies() {
    if ( ! class_exists( 'LOTN_Chronicle' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>FangCoin</strong> requires the <strong>Cities of Darkness Chronicle Tools</strong> plugin to be installed and active.';
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}

/**
 * Boot the plugin.
 */
function fangcoin_init() {
    if ( ! fangcoin_check_dependencies() ) {
        return;
    }

    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin-db.php';
    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin-rest.php';
    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin-shortcode.php';
    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin-st.php';
    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin.php';

    FangCoin::init();

    // Run DB upgrades if needed.
    fangcoin_maybe_upgrade();
}
add_action( 'plugins_loaded', 'fangcoin_init' );

/**
 * Create database tables on activation.
 */
function fangcoin_activate() {
    require_once FANGCOIN_PLUGIN_DIR . 'includes/class-fangcoin-db.php';
    FangCoin_DB::create_tables();
    update_option( 'fangcoin_db_version', FANGCOIN_VERSION );
}
register_activation_hook( __FILE__, 'fangcoin_activate' );

/**
 * Run DB schema upgrades when the plugin version changes.
 */
function fangcoin_maybe_upgrade() {
    $db_version = get_option( 'fangcoin_db_version', '0' );
    if ( version_compare( $db_version, FANGCOIN_VERSION, '<' ) ) {
        FangCoin_DB::create_tables();
        update_option( 'fangcoin_db_version', FANGCOIN_VERSION );
    }
}
