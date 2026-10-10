<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FangCoin_DB {

    /**
     * Create or update plugin tables.
     */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wallets_table      = $wpdb->prefix . 'fangcoin_wallets';
        $transactions_table = $wpdb->prefix . 'fangcoin_transactions';
        $codes_table        = $wpdb->prefix . 'fangcoin_codes';

        $sql = "CREATE TABLE {$wallets_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            character_id BIGINT UNSIGNED NOT NULL,
            address VARCHAR(16) NOT NULL,
            balance INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY character_id (character_id),
            UNIQUE KEY address (address)
        ) {$charset};

        CREATE TABLE {$transactions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            from_wallet_id BIGINT UNSIGNED DEFAULT NULL,
            to_wallet_id BIGINT UNSIGNED DEFAULT NULL,
            amount INT UNSIGNED NOT NULL DEFAULT 1,
            note TEXT DEFAULT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY from_wallet_id (from_wallet_id),
            KEY to_wallet_id (to_wallet_id)
        ) {$charset};

        CREATE TABLE {$codes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(15) NOT NULL,
            source_wallet_id BIGINT UNSIGNED DEFAULT NULL,
            chronicle_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            redeemed_by_wallet_id BIGINT UNSIGNED DEFAULT NULL,
            redeemed_at DATETIME DEFAULT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status),
            KEY source_wallet_id (source_wallet_id),
            KEY chronicle_id (chronicle_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Backfill addresses for any wallets that don't have one (upgrade path).
        self::backfill_addresses();
    }

    /**
     * Generate addresses for wallets that are missing one.
     */
    private static function backfill_addresses() {
        global $wpdb;
        $table   = $wpdb->prefix . 'fangcoin_wallets';
        $wallets = $wpdb->get_results( "SELECT id FROM {$table} WHERE address = '' OR address IS NULL" );
        foreach ( $wallets as $w ) {
            $wpdb->update( $table, [ 'address' => self::generate_address() ], [ 'id' => $w->id ] );
        }
    }

    /* ---------------------------------------------------------------
     * Address generation
     * ------------------------------------------------------------- */

    /**
     * Generate a unique wallet address in the format fc1XXXXXXXX.
     */
    public static function generate_address(): string {
        $chars = '23456789abcdefghjkmnpqrstuvwxyz';
        $len   = strlen( $chars );

        do {
            $body = '';
            for ( $i = 0; $i < 8; $i++ ) {
                $body .= $chars[ random_int( 0, $len - 1 ) ];
            }
            $address = 'fc1' . $body;
        } while ( self::address_exists( $address ) );

        return $address;
    }

    /**
     * Check if an address already exists.
     */
    public static function address_exists( string $address ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';
        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE address = %s", $address )
        );
    }

    /* ---------------------------------------------------------------
     * Wallet operations
     * ------------------------------------------------------------- */

    public static function get_or_create_wallet( int $character_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';

        $wallet = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE character_id = %d", $character_id )
        );

        if ( $wallet ) {
            return $wallet;
        }

        $wpdb->insert( $table, [
            'character_id' => $character_id,
            'address'      => self::generate_address(),
            'balance'      => 0,
        ] );

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id )
        );
    }

    public static function get_wallet( int $wallet_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wallet_id )
        );
    }

    public static function get_wallet_by_character( int $character_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE character_id = %d", $character_id )
        );
    }

    /**
     * Get a wallet by its address.
     */
    public static function get_wallet_by_address( string $address ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE address = %s", strtolower( trim( $address ) ) )
        );
    }

    public static function update_balance( int $wallet_id, int $new_balance ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';
        return (bool) $wpdb->update( $table, [ 'balance' => $new_balance ], [ 'id' => $wallet_id ] );
    }

    public static function get_all_wallets( ?int $chronicle_id = null ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_wallets';

        $sql = "SELECT w.*, p.post_title AS character_name, p.post_type
                FROM {$table} w
                JOIN {$wpdb->posts} p ON p.ID = w.character_id
                WHERE p.post_status = 'publish'";

        if ( $chronicle_id ) {
            $sql .= $wpdb->prepare(
                " AND (
                    (p.post_type = 'lotn_sheet' AND EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta}
                        WHERE post_id = p.ID AND meta_key = '_lotn_home_chronicle_id' AND meta_value = %d
                    ))
                    OR
                    (p.post_type = 'lotn_spc' AND EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta}
                        WHERE post_id = p.ID AND meta_key = '_lotn_spc_chronicle_id' AND meta_value = %d
                    ))
                )",
                $chronicle_id,
                $chronicle_id
            );
        }

        $sql .= " ORDER BY p.post_title ASC";
        return $wpdb->get_results( $sql );
    }

    /* ---------------------------------------------------------------
     * Transfer operations
     * ------------------------------------------------------------- */

    public static function transfer( int $from_wallet_id, int $to_wallet_id, int $amount, int $user_id, string $note = '' ): bool {
        global $wpdb;

        $from = self::get_wallet( $from_wallet_id );
        $to   = self::get_wallet( $to_wallet_id );

        if ( ! $from || ! $to || $from->balance < $amount || $amount < 1 ) {
            return false;
        }

        $wpdb->query( 'START TRANSACTION' );

        $ok = self::update_balance( $from_wallet_id, $from->balance - $amount )
           && self::update_balance( $to_wallet_id, $to->balance + $amount );

        if ( $ok ) {
            $ok = self::log_transaction( 'transfer', $from_wallet_id, $to_wallet_id, $amount, $user_id, $note );
        }

        if ( $ok ) {
            $wpdb->query( 'COMMIT' );
            return true;
        }

        $wpdb->query( 'ROLLBACK' );
        return false;
    }

    public static function mint_to_wallet( int $wallet_id, int $amount, int $user_id, string $note = '' ): bool {
        global $wpdb;

        $wallet = self::get_wallet( $wallet_id );
        if ( ! $wallet || $amount < 1 ) {
            return false;
        }

        $wpdb->query( 'START TRANSACTION' );

        $ok = self::update_balance( $wallet_id, $wallet->balance + $amount )
           && self::log_transaction( 'mint', null, $wallet_id, $amount, $user_id, $note );

        if ( $ok ) {
            $wpdb->query( 'COMMIT' );
            return true;
        }

        $wpdb->query( 'ROLLBACK' );
        return false;
    }

    /* ---------------------------------------------------------------
     * Code operations
     * ------------------------------------------------------------- */

    public static function generate_claim_code(): string {
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $len   = strlen( $chars );

        do {
            $part1 = '';
            $part2 = '';
            for ( $i = 0; $i < 4; $i++ ) {
                $part1 .= $chars[ random_int( 0, $len - 1 ) ];
                $part2 .= $chars[ random_int( 0, $len - 1 ) ];
            }
            $code = "FC-{$part1}-{$part2}";
        } while ( self::code_exists( $code ) );

        return $code;
    }

    public static function code_exists( string $code ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_codes';
        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE code = %s", $code )
        );
    }

    public static function generate_codes_from_wallet( int $wallet_id, int $count, int $user_id, int $chronicle_id = 0 ): array|false {
        global $wpdb;

        $wallet = self::get_wallet( $wallet_id );
        if ( ! $wallet || $wallet->balance < $count || $count < 1 ) {
            return false;
        }

        $wpdb->query( 'START TRANSACTION' );

        $ok = self::update_balance( $wallet_id, $wallet->balance - $count );
        if ( ! $ok ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $codes_table = $wpdb->prefix . 'fangcoin_codes';
        $codes       = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $code = self::generate_claim_code();
            $row  = [
                'code'             => $code,
                'source_wallet_id' => $wallet_id,
                'status'           => 'active',
                'created_by'       => $user_id,
            ];
            if ( $chronicle_id ) {
                $row['chronicle_id'] = $chronicle_id;
            }
            $ok = $wpdb->insert( $codes_table, $row );

            if ( ! $ok ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            $codes[] = $code;
        }

        $ok = self::log_transaction( 'generate_code', $wallet_id, null, $count, $user_id, '' );
        if ( ! $ok ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $wpdb->query( 'COMMIT' );
        return $codes;
    }

    public static function mint_codes( int $count, int $user_id, int $chronicle_id = 0 ): array|false {
        global $wpdb;

        if ( $count < 1 ) {
            return false;
        }

        $codes_table = $wpdb->prefix . 'fangcoin_codes';
        $codes       = [];

        $wpdb->query( 'START TRANSACTION' );

        for ( $i = 0; $i < $count; $i++ ) {
            $code = self::generate_claim_code();
            $row  = [
                'code'             => $code,
                'source_wallet_id' => null,
                'status'           => 'active',
                'created_by'       => $user_id,
            ];
            if ( $chronicle_id ) {
                $row['chronicle_id'] = $chronicle_id;
            }
            $ok = $wpdb->insert( $codes_table, $row );

            if ( ! $ok ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            $codes[] = $code;
        }

        $ok = self::log_transaction( 'mint_code', null, null, $count, $user_id, '' );
        if ( ! $ok ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $wpdb->query( 'COMMIT' );
        return $codes;
    }

    public static function verify_code( string $code ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_codes';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", strtoupper( trim( $code ) ) )
        );
    }

    public static function redeem_code( string $code, int $wallet_id, int $user_id ): bool {
        global $wpdb;

        $code_row = self::verify_code( $code );
        if ( ! $code_row || $code_row->status !== 'active' ) {
            return false;
        }

        $wallet = self::get_wallet( $wallet_id );
        if ( ! $wallet ) {
            return false;
        }

        $wpdb->query( 'START TRANSACTION' );

        $codes_table = $wpdb->prefix . 'fangcoin_codes';
        $ok = (bool) $wpdb->update(
            $codes_table,
            [
                'status'                => 'redeemed',
                'redeemed_by_wallet_id' => $wallet_id,
                'redeemed_at'           => current_time( 'mysql' ),
            ],
            [ 'id' => $code_row->id, 'status' => 'active' ]
        );

        if ( $ok ) {
            $ok = self::update_balance( $wallet_id, $wallet->balance + 1 );
        }

        if ( $ok ) {
            $ok = self::log_transaction( 'redeem_code', null, $wallet_id, 1, $user_id, $code );
        }

        if ( $ok ) {
            $wpdb->query( 'COMMIT' );
            return true;
        }

        $wpdb->query( 'ROLLBACK' );
        return false;
    }

    /* ---------------------------------------------------------------
     * Transaction log
     * ------------------------------------------------------------- */

    public static function log_transaction( string $type, ?int $from_wallet_id, ?int $to_wallet_id, int $amount, int $user_id, string $note = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fangcoin_transactions';
        return (bool) $wpdb->insert( $table, [
            'type'           => $type,
            'from_wallet_id' => $from_wallet_id,
            'to_wallet_id'   => $to_wallet_id,
            'amount'         => $amount,
            'note'           => $note,
            'created_by'     => $user_id,
        ] );
    }

    public static function get_transactions( int $wallet_id, int $limit = 50 ): array {
        global $wpdb;
        $table   = $wpdb->prefix . 'fangcoin_transactions';
        $wallets = $wpdb->prefix . 'fangcoin_wallets';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*,
                    fw.character_id AS from_character_id,
                    fw.address AS from_address,
                    tw.character_id AS to_character_id,
                    tw.address AS to_address
             FROM {$table} t
             LEFT JOIN {$wallets} fw ON fw.id = t.from_wallet_id
             LEFT JOIN {$wallets} tw ON tw.id = t.to_wallet_id
             WHERE t.from_wallet_id = %d OR t.to_wallet_id = %d
             ORDER BY t.created_at DESC
             LIMIT %d",
            $wallet_id,
            $wallet_id,
            $limit
        ) );
    }

    /* ---------------------------------------------------------------
     * Economy stats (ST)
     * ------------------------------------------------------------- */

    /* ---------------------------------------------------------------
     * Chronicle settings
     * ------------------------------------------------------------- */

    /**
     * Get the chronicle_id for a character (PC or SPC).
     */
    public static function get_character_chronicle_id( int $post_id ): int {
        $post = get_post( $post_id );
        if ( ! $post ) return 0;

        if ( $post->post_type === 'lotn_spc' ) {
            return (int) get_post_meta( $post_id, '_lotn_spc_chronicle_id', true );
        }

        return (int) get_post_meta( $post_id, '_lotn_home_chronicle_id', true );
    }

    /**
     * Get chronicle settings for FangCoin scoping.
     * Returns [ 'allow_incoming' => bool, 'allow_outgoing' => bool ].
     *
     * If the ST has explicitly saved settings for this chronicle, those are
     * returned as-is.  Otherwise, the default depends on whether Chronicle
     * Tools marks the chronicle as an Event or One-Shot: event/one-shot
     * chronicles default to a sealed economy (both toggles off); regular
     * chronicles default to an open economy (both toggles on).
     */
    public static function get_chronicle_settings( int $chronicle_id ): array {
        if ( ! $chronicle_id ) {
            return [ 'allow_incoming' => true, 'allow_outgoing' => true ];
        }

        $settings     = get_option( 'fangcoin_chronicle_settings', [] );
        $chr_settings = $settings[ $chronicle_id ] ?? [];

        // Determine the correct default: sealed for event/one-shot, open otherwise.
        $is_event     = (bool) get_post_meta( $chronicle_id, '_lotn_chr_is_event_or_one_shot', true );
        $default_open = ! $is_event;

        return [
            'allow_incoming' => $chr_settings['allow_incoming'] ?? $default_open,
            'allow_outgoing' => $chr_settings['allow_outgoing'] ?? $default_open,
        ];
    }

    /**
     * Save chronicle settings for FangCoin scoping.
     */
    public static function save_chronicle_settings( int $chronicle_id, bool $allow_incoming, bool $allow_outgoing ): void {
        $settings = get_option( 'fangcoin_chronicle_settings', [] );
        $settings[ $chronicle_id ] = [
            'allow_incoming' => $allow_incoming,
            'allow_outgoing' => $allow_outgoing,
        ];
        update_option( 'fangcoin_chronicle_settings', $settings );
    }

    /* ---------------------------------------------------------------
     * Economy stats (ST)
     * ------------------------------------------------------------- */

    public static function get_stats( int $chronicle_id = 0 ): array {
        global $wpdb;
        $wallets = $wpdb->prefix . 'fangcoin_wallets';
        $codes   = $wpdb->prefix . 'fangcoin_codes';

        if ( $chronicle_id ) {
            // Scoped stats: only wallets whose character belongs to this chronicle.
            $chronicle_wallet_filter = $wpdb->prepare(
                "AND (
                    EXISTS (
                        SELECT 1 FROM {$wpdb->posts} p
                        JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                        WHERE p.ID = w.character_id
                          AND p.post_type = 'lotn_sheet'
                          AND pm.meta_key = '_lotn_home_chronicle_id'
                          AND pm.meta_value = %d
                    )
                    OR EXISTS (
                        SELECT 1 FROM {$wpdb->posts} p
                        JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                        WHERE p.ID = w.character_id
                          AND p.post_type = 'lotn_spc'
                          AND pm.meta_key = '_lotn_spc_chronicle_id'
                          AND pm.meta_value = %d
                    )
                )",
                $chronicle_id,
                $chronicle_id
            );

            $total_in_wallets = (int) $wpdb->get_var( "SELECT COALESCE(SUM(w.balance), 0) FROM {$wallets} w WHERE 1=1 {$chronicle_wallet_filter}" );
            $wallet_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wallets} w WHERE w.balance > 0 {$chronicle_wallet_filter}" );
            $total_wallets    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wallets} w WHERE 1=1 {$chronicle_wallet_filter}" );

            // Active codes scoped to this chronicle.
            $active_codes = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$codes} WHERE status = 'active' AND chronicle_id = %d",
                $chronicle_id
            ) );
        } else {
            $total_in_wallets = (int) $wpdb->get_var( "SELECT COALESCE(SUM(balance), 0) FROM {$wallets}" );
            $active_codes     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$codes} WHERE status = 'active'" );
            $wallet_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wallets} WHERE balance > 0" );
            $total_wallets    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wallets}" );
        }

        return [
            'total_in_wallets'     => $total_in_wallets,
            'total_circulation'    => $total_in_wallets + $active_codes,
            'active_codes'         => $active_codes,
            'wallets_with_balance' => $wallet_count,
            'total_wallets'        => $total_wallets,
        ];
    }
}
