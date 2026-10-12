<?php
/**
 * FangCoin_Privacy -- GDPR / legal-compliance data tools
 *
 * Registers with WordPress's built-in privacy framework
 * (Tools > Export Personal Data / Erase Personal Data) so that
 * FangCoin data is included alongside Chronicle Tools data.
 *
 * Data covered:
 *  - {prefix}fangcoin_wallets        (tied to user's characters)
 *  - {prefix}fangcoin_transactions   (created_by = user ID, or involving user's wallets)
 *  - {prefix}fangcoin_codes          (created_by = user ID, or sourced/redeemed by user's wallets)
 *
 * @package Cities_of_Darkness_FangCoin
 */

defined( 'ABSPATH' ) || exit;

class FangCoin_Privacy {

    // -- Bootstrap -------------------------------------------------------

    public static function init(): void {
        add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers',   [ __CLASS__, 'register_eraser'   ] );
    }

    // -- WP Privacy framework registration -------------------------------

    public static function register_exporter( array $exporters ): array {
        $exporters['fangcoin'] = [
            'exporter_friendly_name' => __( 'Cities of Darkness: FangCoin', 'fangcoin' ),
            'callback'               => [ __CLASS__, 'wp_export_callback' ],
        ];
        return $exporters;
    }

    public static function register_eraser( array $erasers ): array {
        $erasers['fangcoin'] = [
            'eraser_friendly_name' => __( 'Cities of Darkness: FangCoin', 'fangcoin' ),
            'callback'             => [ __CLASS__, 'wp_erase_callback' ],
        ];
        return $erasers;
    }

    // -- Helpers ----------------------------------------------------------

    /**
     * Get all wallet IDs belonging to characters authored by a user.
     *
     * @return int[] Wallet IDs.
     */
    private static function get_user_wallet_ids( int $user_id ): array {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'fangcoin_wallets';

        // Characters (lotn_sheet and lotn_spc) are WP posts authored by the user.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT w.id
             FROM {$wallets_table} w
             JOIN {$wpdb->posts} p ON p.ID = w.character_id
             WHERE p.post_author = %d",
            $user_id
        ) ) );
    }

    /**
     * Build a SQL IN-clause placeholder string for an array of ints.
     * Returns something like "%d, %d, %d".
     */
    private static function in_placeholders( array $ids ): string {
        return implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
    }

    // -- Exporter ---------------------------------------------------------

    /**
     * WP Personal Data Exporter callback.
     */
    public static function wp_export_callback( string $email_address, int $page = 1 ): array {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return [ 'data' => [], 'done' => true ];
        }

        $export_items = [];
        $wallet_ids   = self::get_user_wallet_ids( $user->ID );

        if ( empty( $wallet_ids ) ) {
            return [ 'data' => [], 'done' => true ];
        }

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';

        // -- Wallets
        $placeholders = self::in_placeholders( $wallet_ids );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wallets = $wpdb->get_results( $wpdb->prepare(
            "SELECT w.*, p.post_title AS character_name
             FROM {$wallets_table} w
             JOIN {$wpdb->posts} p ON p.ID = w.character_id
             WHERE w.id IN ({$placeholders})",
            ...$wallet_ids
        ) );

        foreach ( $wallets as $w ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_wallets',
                'group_label' => __( 'FangCoin - Wallets', 'fangcoin' ),
                'item_id'     => 'fangcoin_wallet_' . $w->id,
                'data'        => [
                    [ 'name' => __( 'Character',  'fangcoin' ), 'value' => esc_html( $w->character_name ) ],
                    [ 'name' => __( 'Address',    'fangcoin' ), 'value' => esc_html( $w->address ) ],
                    [ 'name' => __( 'Balance',    'fangcoin' ), 'value' => (string) $w->balance ],
                    [ 'name' => __( 'Created',    'fangcoin' ), 'value' => esc_html( $w->created_at ) ],
                ],
            ];
        }

        // -- Transactions (where user's wallet is sender or recipient)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*,
                    fw.address AS from_address,
                    tw.address AS to_address
             FROM {$transactions_table} t
             LEFT JOIN {$wallets_table} fw ON fw.id = t.from_wallet_id
             LEFT JOIN {$wallets_table} tw ON tw.id = t.to_wallet_id
             WHERE t.from_wallet_id IN ({$placeholders})
                OR t.to_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        foreach ( $transactions as $tx ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_transactions',
                'group_label' => __( 'FangCoin - Transactions', 'fangcoin' ),
                'item_id'     => 'fangcoin_tx_' . $tx->id,
                'data'        => [
                    [ 'name' => __( 'Type',         'fangcoin' ), 'value' => esc_html( $tx->type ) ],
                    [ 'name' => __( 'From Address', 'fangcoin' ), 'value' => esc_html( $tx->from_address ?? 'N/A' ) ],
                    [ 'name' => __( 'To Address',   'fangcoin' ), 'value' => esc_html( $tx->to_address ?? 'N/A' ) ],
                    [ 'name' => __( 'Amount',       'fangcoin' ), 'value' => (string) $tx->amount ],
                    [ 'name' => __( 'Note',         'fangcoin' ), 'value' => esc_html( $tx->note ?: '' ) ],
                    [ 'name' => __( 'Date',         'fangcoin' ), 'value' => esc_html( $tx->created_at ) ],
                ],
            ];
        }

        // -- Codes (sourced from or redeemed by user's wallets)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $codes = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$codes_table}
             WHERE source_wallet_id IN ({$placeholders})
                OR redeemed_by_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        foreach ( $codes as $c ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_codes',
                'group_label' => __( 'FangCoin - QR Codes', 'fangcoin' ),
                'item_id'     => 'fangcoin_code_' . $c->id,
                'data'        => [
                    [ 'name' => __( 'Code',        'fangcoin' ), 'value' => esc_html( $c->code ) ],
                    [ 'name' => __( 'Status',      'fangcoin' ), 'value' => esc_html( $c->status ) ],
                    [ 'name' => __( 'Created',     'fangcoin' ), 'value' => esc_html( $c->created_at ) ],
                    [ 'name' => __( 'Redeemed At', 'fangcoin' ), 'value' => esc_html( $c->redeemed_at ?: '' ) ],
                ],
            ];
        }

        return [ 'data' => $export_items, 'done' => true ];
    }

    // -- Eraser -----------------------------------------------------------

    /**
     * WP Personal Data Eraser callback.
     *
     * Deletes all FangCoin data tied to the user's characters: wallets,
     * transactions involving those wallets, and codes sourced from or
     * redeemed by those wallets.
     */
    public static function wp_erase_callback( string $email_address, int $page = 1 ): array {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
        }

        $wallet_ids = self::get_user_wallet_ids( $user->ID );

        if ( empty( $wallet_ids ) ) {
            return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
        }

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';

        $placeholders = self::in_placeholders( $wallet_ids );

        // Delete transactions involving the user's wallets.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $tx_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$transactions_table}
             WHERE from_wallet_id IN ({$placeholders})
                OR to_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // Delete codes sourced from or redeemed by the user's wallets.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $codes_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$codes_table}
             WHERE source_wallet_id IN ({$placeholders})
                OR redeemed_by_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // Delete the wallets themselves.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wallets_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wallets_table} WHERE id IN ({$placeholders})",
            ...$wallet_ids
        ) );

        $total = $wallets_deleted + $tx_deleted + $codes_deleted;

        return [
            'items_removed'  => $total > 0,
            'items_retained' => false,
            'messages'       => [
                sprintf(
                    /* translators: 1: wallets, 2: transactions, 3: codes */
                    __( 'Deleted %1$d wallet(s), %2$d transaction(s), and %3$d QR code(s).', 'fangcoin' ),
                    $wallets_deleted,
                    $tx_deleted,
                    $codes_deleted
                ),
            ],
            'done' => true,
        ];
    }
}
