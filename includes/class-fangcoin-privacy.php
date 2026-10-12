<?php
/**
 * FangCoin_Privacy -- GDPR / legal-compliance data tools
 *
 * Registers with WordPress's built-in privacy framework
 * (Tools > Export/Erase Personal Data).
 *
 * Data covered:
 *  - {prefix}fangcoin_wallets        (tied to user's characters)
 *  - {prefix}fangcoin_transactions   (involving user's wallets)
 *  - {prefix}fangcoin_codes          (sourced from or redeemed by user's wallets)
 *
 * @package Cities_of_Darkness_FangCoin
 */

defined( 'ABSPATH' ) || exit;

class FangCoin_Privacy {

    // -- Bootstrap -------------------------------------------------------

    public static function init(): void {
        // WordPress built-in privacy framework (Tools > Export/Erase Personal Data).
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
     */
    private static function in_placeholders( array $ids ): string {
        return implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
    }

    // -- Data collection --------------------------------------------------

    /**
     * Collect all FangCoin data for a user into a structured array.
     *
     * @param int $user_id WordPress user ID.
     * @return array { wallets: array, transactions: array, codes: array }
     */
    public static function collect_user_data( int $user_id ): array {
        global $wpdb;

        $wallet_ids = self::get_user_wallet_ids( $user_id );

        if ( empty( $wallet_ids ) ) {
            return [ 'wallets' => [], 'transactions' => [], 'codes' => [] ];
        }

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';
        $placeholders       = self::in_placeholders( $wallet_ids );

        // Wallets
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wallets = $wpdb->get_results( $wpdb->prepare(
            "SELECT w.*, p.post_title AS character_name
             FROM {$wallets_table} w
             JOIN {$wpdb->posts} p ON p.ID = w.character_id
             WHERE w.id IN ({$placeholders})",
            ...$wallet_ids
        ), ARRAY_A );

        // Transactions involving the user's wallets
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
        ), ARRAY_A );

        // Codes sourced from or redeemed by the user's wallets
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $codes = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$codes_table}
             WHERE source_wallet_id IN ({$placeholders})
                OR redeemed_by_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ), ARRAY_A );

        return [
            'wallets'      => $wallets ?: [],
            'transactions' => $transactions ?: [],
            'codes'        => $codes ?: [],
        ];
    }

    // -- Data deletion ----------------------------------------------------

    /**
     * Hard-delete all FangCoin data for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array { wallets: int, transactions: int, codes: int }
     */
    public static function delete_user_data( int $user_id ): array {
        global $wpdb;

        $wallet_ids = self::get_user_wallet_ids( $user_id );

        if ( empty( $wallet_ids ) ) {
            return [ 'wallets' => 0, 'transactions' => 0, 'codes' => 0 ];
        }

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';
        $placeholders       = self::in_placeholders( $wallet_ids );

        // Delete in dependency order: transactions, codes, then wallets.

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $tx_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$transactions_table}
             WHERE from_wallet_id IN ({$placeholders})
                OR to_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $codes_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$codes_table}
             WHERE source_wallet_id IN ({$placeholders})
                OR redeemed_by_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wallets_deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wallets_table} WHERE id IN ({$placeholders})",
            ...$wallet_ids
        ) );

        return [
            'wallets'      => $wallets_deleted,
            'transactions' => $tx_deleted,
            'codes'        => $codes_deleted,
        ];
    }

    // -- WP Privacy callbacks (Tools > Export/Erase Personal Data) --------

    /**
     * WP Personal Data Exporter callback.
     */
    public static function wp_export_callback( string $email_address, int $page = 1 ): array {
        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return [ 'data' => [], 'done' => true ];
        }

        $data         = self::collect_user_data( $user->ID );
        $export_items = [];

        foreach ( $data['wallets'] as $w ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_wallets',
                'group_label' => __( 'FangCoin - Wallets', 'fangcoin' ),
                'item_id'     => 'fangcoin_wallet_' . $w['id'],
                'data'        => [
                    [ 'name' => __( 'Character',  'fangcoin' ), 'value' => esc_html( $w['character_name'] ) ],
                    [ 'name' => __( 'Address',    'fangcoin' ), 'value' => esc_html( $w['address'] ) ],
                    [ 'name' => __( 'Balance',    'fangcoin' ), 'value' => (string) $w['balance'] ],
                    [ 'name' => __( 'Created',    'fangcoin' ), 'value' => esc_html( $w['created_at'] ) ],
                ],
            ];
        }

        foreach ( $data['transactions'] as $tx ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_transactions',
                'group_label' => __( 'FangCoin - Transactions', 'fangcoin' ),
                'item_id'     => 'fangcoin_tx_' . $tx['id'],
                'data'        => [
                    [ 'name' => __( 'Type',         'fangcoin' ), 'value' => esc_html( $tx['type'] ) ],
                    [ 'name' => __( 'From Address', 'fangcoin' ), 'value' => esc_html( $tx['from_address'] ?? 'N/A' ) ],
                    [ 'name' => __( 'To Address',   'fangcoin' ), 'value' => esc_html( $tx['to_address'] ?? 'N/A' ) ],
                    [ 'name' => __( 'Amount',       'fangcoin' ), 'value' => (string) $tx['amount'] ],
                    [ 'name' => __( 'Note',         'fangcoin' ), 'value' => esc_html( $tx['note'] ?: '' ) ],
                    [ 'name' => __( 'Date',         'fangcoin' ), 'value' => esc_html( $tx['created_at'] ) ],
                ],
            ];
        }

        foreach ( $data['codes'] as $c ) {
            $export_items[] = [
                'group_id'    => 'fangcoin_codes',
                'group_label' => __( 'FangCoin - QR Codes', 'fangcoin' ),
                'item_id'     => 'fangcoin_code_' . $c['id'],
                'data'        => [
                    [ 'name' => __( 'Code',        'fangcoin' ), 'value' => esc_html( $c['code'] ) ],
                    [ 'name' => __( 'Status',      'fangcoin' ), 'value' => esc_html( $c['status'] ) ],
                    [ 'name' => __( 'Created',     'fangcoin' ), 'value' => esc_html( $c['created_at'] ) ],
                    [ 'name' => __( 'Redeemed At', 'fangcoin' ), 'value' => esc_html( $c['redeemed_at'] ?: '' ) ],
                ],
            ];
        }

        return [ 'data' => $export_items, 'done' => true ];
    }

    /**
     * WP Personal Data Eraser callback.
     */
    public static function wp_erase_callback( string $email_address, int $page = 1 ): array {
        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
        }

        $result = self::delete_user_data( $user->ID );
        $total  = $result['wallets'] + $result['transactions'] + $result['codes'];

        return [
            'items_removed'  => $total > 0,
            'items_retained' => false,
            'messages'       => [
                sprintf(
                    __( 'Deleted %1$d wallet(s), %2$d transaction(s), and %3$d QR code(s).', 'fangcoin' ),
                    $result['wallets'],
                    $result['transactions'],
                    $result['codes']
                ),
            ],
            'done' => true,
        ];
    }

}
