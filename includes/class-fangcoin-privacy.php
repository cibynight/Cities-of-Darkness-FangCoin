<?php
/**
 * FangCoin_Privacy -- GDPR / legal-compliance data tools
 *
 * Hooks into Chronicle Tools' privacy framework to cover FangCoin's
 * three custom tables in data exports and deletions.
 *
 * Chronicle Tools fires three hooks:
 *  - lotn_privacy_export_data      (filter) — JSON download + WP export
 *  - lotn_privacy_wp_export_items  (filter) — WP export formatted items
 *  - lotn_privacy_after_delete     (action) — admin delete + WP eraser
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
        // 1. Append raw FangCoin data to the export payload (JSON download + WP export source).
        add_filter( 'lotn_privacy_export_data', [ __CLASS__, 'merge_export_data' ], 10, 2 );

        // 2. Format FangCoin data as WP export items (Tools > Export Personal Data).
        add_filter( 'lotn_privacy_wp_export_items', [ __CLASS__, 'format_wp_export_items' ], 10, 3 );

        // 3. Delete FangCoin data on admin delete button + WP eraser.
        add_action( 'lotn_privacy_after_delete', [ __CLASS__, 'delete_user_data' ], 10, 1 );
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

    // -- Chronicle Tools hook callbacks -----------------------------------

    /**
     * Merge raw FangCoin data into the export payload.
     *
     * Hooked to: lotn_privacy_export_data (filter)
     * Fires from: JSON download + WP export.
     *
     * @param array $data    Accumulated export data from Chronicle Tools + other plugins.
     * @param int   $user_id WordPress user ID being exported.
     * @return array Data with FangCoin data merged under the 'fangcoin' key.
     */
    public static function merge_export_data( array $data, int $user_id ): array {
        $data['fangcoin'] = self::collect_user_data( $user_id );
        return $data;
    }

    /**
     * Format FangCoin data as WordPress personal-data export items.
     *
     * Hooked to: lotn_privacy_wp_export_items (filter)
     * Fires from: WP export only (Tools > Export Personal Data).
     *
     * @param array $items   Accumulated WP export items.
     * @param array $data    Full export data (including $data['fangcoin'] from our filter above).
     * @param int   $user_id WordPress user ID being exported.
     * @return array Items with FangCoin wallets, transactions, and codes appended.
     */
    public static function format_wp_export_items( array $items, array $data, int $user_id ): array {
        $fc = $data['fangcoin'] ?? [];

        foreach ( $fc['wallets'] ?? [] as $w ) {
            $items[] = [
                'group_id'    => 'fangcoin_wallets',
                'group_label' => __( 'FangCoin – Wallets', 'fangcoin' ),
                'item_id'     => 'fangcoin_wallet_' . $w['id'],
                'data'        => [
                    [ 'name' => __( 'Character',  'fangcoin' ), 'value' => esc_html( $w['character_name'] ) ],
                    [ 'name' => __( 'Address',    'fangcoin' ), 'value' => esc_html( $w['address'] ) ],
                    [ 'name' => __( 'Balance',    'fangcoin' ), 'value' => (string) $w['balance'] ],
                    [ 'name' => __( 'Created',    'fangcoin' ), 'value' => esc_html( $w['created_at'] ) ],
                ],
            ];
        }

        foreach ( $fc['transactions'] ?? [] as $tx ) {
            $items[] = [
                'group_id'    => 'fangcoin_transactions',
                'group_label' => __( 'FangCoin – Transactions', 'fangcoin' ),
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

        foreach ( $fc['codes'] ?? [] as $c ) {
            $items[] = [
                'group_id'    => 'fangcoin_codes',
                'group_label' => __( 'FangCoin – QR Codes', 'fangcoin' ),
                'item_id'     => 'fangcoin_code_' . $c['id'],
                'data'        => [
                    [ 'name' => __( 'Code',        'fangcoin' ), 'value' => esc_html( $c['code'] ) ],
                    [ 'name' => __( 'Status',      'fangcoin' ), 'value' => esc_html( $c['status'] ) ],
                    [ 'name' => __( 'Created',     'fangcoin' ), 'value' => esc_html( $c['created_at'] ) ],
                    [ 'name' => __( 'Redeemed At', 'fangcoin' ), 'value' => esc_html( $c['redeemed_at'] ?: '' ) ],
                ],
            ];
        }

        return $items;
    }

    // -- Data deletion ----------------------------------------------------

    /**
     * Hard-delete all FangCoin data for a user.
     *
     * Hooked to: lotn_privacy_after_delete (action)
     * Fires from: admin delete button + WP eraser.
     *
     * @param int $user_id WordPress user ID whose data is being deleted.
     */
    public static function delete_user_data( int $user_id ): void {
        global $wpdb;

        $wallet_ids = self::get_user_wallet_ids( $user_id );

        if ( empty( $wallet_ids ) ) {
            return;
        }

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';
        $placeholders       = self::in_placeholders( $wallet_ids );

        // Delete in dependency order: transactions, codes, then wallets.

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$transactions_table}
             WHERE from_wallet_id IN ({$placeholders})
                OR to_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$codes_table}
             WHERE source_wallet_id IN ({$placeholders})
                OR redeemed_by_wallet_id IN ({$placeholders})",
            ...array_merge( $wallet_ids, $wallet_ids )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wallets_table} WHERE id IN ({$placeholders})",
            ...$wallet_ids
        ) );
    }
}
