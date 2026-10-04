<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FangCoin {

    public static function init() {
        // REST API.
        add_action( 'rest_api_init', [ 'FangCoin_REST', 'register_routes' ] );

        // Shortcode.
        FangCoin_Shortcode::register();

        // ST Dashboard tab.
        FangCoin_ST::register();

        // Print page route.
        add_action( 'template_redirect', [ __CLASS__, 'handle_print_page' ] );

        // Verify page route (for QR code scans from external QR apps).
        add_action( 'template_redirect', [ __CLASS__, 'handle_verify_redirect' ] );

        // Wallet address QR route (for scans from external QR apps).
        add_action( 'template_redirect', [ __CLASS__, 'handle_send_redirect' ] );
    }

    /**
     * Serve the print page for QR codes.
     * URL: ?fangcoin_print=1&codes=FC-XXXX,FC-YYYY&format=sticker|card
     */
    public static function handle_print_page(): void {
        if ( ! isset( $_GET['fangcoin_print'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to print FangCoin codes.' );
        }

        $codes_param = sanitize_text_field( $_GET['codes'] ?? '' );
        $format      = sanitize_text_field( $_GET['format'] ?? 'card' );

        if ( ! $codes_param ) {
            wp_die( 'No codes provided.' );
        }

        $codes    = array_map( 'trim', explode( ',', $codes_param ) );
        $site_url = home_url();

        // Validate each code exists and is active.
        $valid_codes = [];
        foreach ( $codes as $code ) {
            $row = FangCoin_DB::verify_code( $code );
            if ( $row && $row->status === 'active' ) {
                $valid_codes[] = [
                    'code'    => $row->code,
                    'qr_data' => $site_url . '?fangcoin_verify=' . urlencode( $row->code ),
                ];
            }
        }

        if ( empty( $valid_codes ) ) {
            wp_die( 'No valid codes to print.' );
        }

        // Serve the print template.
        if ( $format === 'sticker' ) {
            include FANGCOIN_PLUGIN_DIR . 'templates/print-stickers.php';
        } else {
            include FANGCOIN_PLUGIN_DIR . 'templates/print-cards.php';
        }
        exit;
    }

    /**
     * Handle QR code scans that hit ?fangcoin_verify=FC-XXXX-XXXX.
     * Redirects to the FangCoin page with the code pre-filled, or shows a
     * simple standalone verification result for logged-out users.
     */
    public static function handle_verify_redirect(): void {
        if ( ! isset( $_GET['fangcoin_verify'] ) ) {
            return;
        }

        $code     = sanitize_text_field( $_GET['fangcoin_verify'] );
        $code_row = FangCoin_DB::verify_code( $code );

        // Simple standalone page showing validity.
        $status = 'NOT FOUND';
        $class  = 'fc-verify-invalid';
        if ( $code_row ) {
            if ( $code_row->status === 'active' ) {
                $status = 'VALID';
                $class  = 'fc-verify-valid';
            } else {
                $status = 'ALREADY REDEEMED';
                $class  = 'fc-verify-redeemed';
            }
        }

        include FANGCOIN_PLUGIN_DIR . 'templates/verify.php';
        exit;
    }

    /**
     * Handle wallet address QR scans from external apps.
     * Shows the address and prompts the user to open FangCoin to send.
     */
    public static function handle_send_redirect(): void {
        if ( ! isset( $_GET['fangcoin_send'] ) ) {
            return;
        }

        $address = sanitize_text_field( $_GET['fangcoin_send'] );
        $wallet  = FangCoin_DB::get_wallet_by_address( $address );
        $valid   = (bool) $wallet;

        include FANGCOIN_PLUGIN_DIR . 'templates/send-redirect.php';
        exit;
    }
}
