<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FangCoin_REST {

    public static function register_routes() {
        $ns = 'fangcoin/v1';

        // Player endpoints.
        register_rest_route( $ns, '/wallets', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_my_wallets' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        register_rest_route( $ns, '/transfer', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'transfer' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        register_rest_route( $ns, '/transactions/(?P<character_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_transactions' ],
            'permission_callback' => [ __CLASS__, 'can_access_character' ],
        ] );

        // Address lookup (returns nothing identifying, just confirms address exists).
        register_rest_route( $ns, '/address/(?P<address>[a-z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'lookup_address' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        // Code endpoints.
        register_rest_route( $ns, '/codes/generate', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'generate_codes' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        register_rest_route( $ns, '/codes/verify/(?P<code>[A-Za-z0-9\-]+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'verify_code' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/codes/redeem', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'redeem_code' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        // ST endpoints.
        $chronicle_id_arg = [
            'chronicle_id' => [
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route( $ns, '/st/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'st_stats' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
            'args'                => $chronicle_id_arg,
        ] );

        register_rest_route( $ns, '/st/wallets', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'st_wallets' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
            'args'                => $chronicle_id_arg,
        ] );

        register_rest_route( $ns, '/st/characters', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'st_characters' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
            'args'                => $chronicle_id_arg,
        ] );

        register_rest_route( $ns, '/st/mint-to-wallet', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'st_mint_to_wallet' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
        ] );

        register_rest_route( $ns, '/st/mint-codes', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'st_mint_codes' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
        ] );

        // Chronicle settings endpoints.
        register_rest_route( $ns, '/st/chronicle-settings/(?P<chronicle_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_chronicle_settings' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
        ] );

        register_rest_route( $ns, '/st/chronicle-settings/(?P<chronicle_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'save_chronicle_settings' ],
            'permission_callback' => [ __CLASS__, 'is_storyteller' ],
        ] );
    }

    /* ---------------------------------------------------------------
     * Permission helpers
     * ------------------------------------------------------------- */

    public static function is_storyteller(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'lotn_storyteller' );
    }

    public static function can_access_character( \WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) return false;
        if ( self::is_storyteller() ) return true;
        $character_id = (int) $request->get_param( 'character_id' );
        $post = get_post( $character_id );
        return $post && (int) $post->post_author === get_current_user_id();
    }

    private static function user_owns_character( int $character_id ): bool {
        if ( self::is_storyteller() ) return true;
        $post = get_post( $character_id );
        return $post && (int) $post->post_author === get_current_user_id();
    }

    /**
     * Check if a PC is approved. SPCs are always considered approved.
     */
    private static function is_approved( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        // SPCs are ST-created, no approval flow.
        if ( $post->post_type === 'lotn_spc' ) {
            return $post->post_status === 'publish';
        }

        // PCs use the approval meta keys.
        return get_post_meta( $post_id, '_lotn_validated', true ) === '1'
            && get_post_meta( $post_id, '_lotn_chr_sub_status', true ) === 'approved';
    }

    /* ---------------------------------------------------------------
     * Player endpoints
     * ------------------------------------------------------------- */

    /**
     * Get wallets for the current user's approved PCs.
     * Storytellers also get all published SPCs.
     */
    public static function get_my_wallets( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();

        // Approved PCs owned by this user.
        $characters = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = '_lotn_validated' AND v.meta_value = '1'
             JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_lotn_chr_sub_status' AND s.meta_value = 'approved'
             WHERE p.post_author = %d
               AND p.post_type = 'lotn_sheet'
               AND p.post_status = 'publish'",
            $user_id
        ) );

        // Storytellers also get published SPCs, scoped to the same chronicles as their PCs.
        if ( self::is_storyteller() ) {
            // Collect chronicle IDs from this user's PCs.
            $my_chronicle_ids = [];
            foreach ( $characters as $char ) {
                $cid = (int) get_post_meta( $char->ID, '_lotn_home_chronicle_id', true );
                if ( $cid ) {
                    $my_chronicle_ids[ $cid ] = true;
                }
            }

            if ( ! empty( $my_chronicle_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $my_chronicle_ids ), '%d' ) );
                $chronicle_values = array_keys( $my_chronicle_ids );

                $spcs = $wpdb->get_results( $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type
                     FROM {$wpdb->posts} p
                     JOIN {$wpdb->postmeta} cm ON cm.post_id = p.ID
                       AND cm.meta_key = '_lotn_spc_chronicle_id'
                       AND cm.meta_value IN ({$placeholders})
                     WHERE p.post_type = 'lotn_spc'
                       AND p.post_status = 'publish'
                     ORDER BY p.post_title ASC",
                    ...$chronicle_values
                ) );
            } elseif ( current_user_can( 'manage_options' ) ) {
                // Site admins with no PCs: fall back to all SPCs.
                $spcs = $wpdb->get_results(
                    "SELECT p.ID, p.post_title, p.post_type
                     FROM {$wpdb->posts} p
                     WHERE p.post_type = 'lotn_spc'
                       AND p.post_status = 'publish'
                     ORDER BY p.post_title ASC"
                );
            } else {
                $spcs = [];
            }

            $characters = array_merge( $characters, $spcs );
        }

        $wallets = [];
        foreach ( $characters as $char ) {
            $wallet = FangCoin_DB::get_or_create_wallet( (int) $char->ID );
            $clan   = get_post_meta( $char->ID, '_lotn_clan', true );
            $wallets[] = [
                'wallet_id'      => (int) $wallet->id,
                'character_id'   => (int) $char->ID,
                'character_name' => $char->post_title,
                'type'           => $char->post_type === 'lotn_spc' ? 'SPC' : 'PC',
                'clan'           => $clan ?: '',
                'balance'        => (int) $wallet->balance,
                'address'        => $wallet->address,
            ];
        }

        return new \WP_REST_Response( $wallets );
    }

    /**
     * Transfer FC from one wallet to another by wallet address.
     */
    public static function transfer( \WP_REST_Request $request ): \WP_REST_Response {
        $params       = $request->get_json_params();
        $from_char_id = (int) ( $params['from_character_id'] ?? 0 );
        $to_address   = sanitize_text_field( $params['to_address'] ?? '' );
        $amount       = (int) ( $params['amount'] ?? 0 );
        $note         = sanitize_text_field( $params['note'] ?? '' );

        if ( ! $from_char_id || ! $to_address || $amount < 1 ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid parameters.' ], 400 );
        }

        if ( ! self::is_storyteller() && ! self::user_owns_character( $from_char_id ) ) {
            return new \WP_REST_Response( [ 'error' => 'You do not own this character.' ], 403 );
        }

        $from_wallet = FangCoin_DB::get_or_create_wallet( $from_char_id );
        $to_wallet   = FangCoin_DB::get_wallet_by_address( $to_address );

        if ( ! $to_wallet ) {
            return new \WP_REST_Response( [ 'error' => 'Wallet address not found.' ], 404 );
        }

        if ( (int) $from_wallet->id === (int) $to_wallet->id ) {
            return new \WP_REST_Response( [ 'error' => 'Cannot send to your own wallet.' ], 400 );
        }

        if ( (int) $from_wallet->balance < $amount ) {
            return new \WP_REST_Response( [ 'error' => 'Insufficient balance.' ], 400 );
        }

        // Chronicle boundary checks for cross-chronicle transfers.
        $from_chronicle = FangCoin_DB::get_character_chronicle_id( $from_char_id );
        $to_chronicle   = FangCoin_DB::get_character_chronicle_id( (int) $to_wallet->character_id );

        if ( $from_chronicle && $to_chronicle && $from_chronicle !== $to_chronicle ) {
            $from_settings = FangCoin_DB::get_chronicle_settings( $from_chronicle );
            $to_settings   = FangCoin_DB::get_chronicle_settings( $to_chronicle );

            if ( ! $from_settings['allow_outgoing'] ) {
                return new \WP_REST_Response( [ 'error' => 'This chronicle does not allow FangCoin to leave.' ], 403 );
            }
            if ( ! $to_settings['allow_incoming'] ) {
                return new \WP_REST_Response( [ 'error' => 'The recipient\'s chronicle does not allow incoming FangCoin.' ], 403 );
            }
        }

        $ok = FangCoin_DB::transfer( (int) $from_wallet->id, (int) $to_wallet->id, $amount, get_current_user_id(), $note );

        if ( ! $ok ) {
            return new \WP_REST_Response( [ 'error' => 'Transfer failed.' ], 500 );
        }

        $updated = FangCoin_DB::get_wallet( (int) $from_wallet->id );

        return new \WP_REST_Response( [
            'success'     => true,
            'new_balance' => (int) $updated->balance,
        ] );
    }

    /**
     * Look up a wallet address. Returns only whether it exists (no identifying info).
     */
    public static function lookup_address( \WP_REST_Request $request ): \WP_REST_Response {
        $address = sanitize_text_field( $request->get_param( 'address' ) );
        $wallet  = FangCoin_DB::get_wallet_by_address( $address );

        return new \WP_REST_Response( [
            'valid' => (bool) $wallet,
        ] );
    }

    public static function get_transactions( \WP_REST_Request $request ): \WP_REST_Response {
        $character_id = (int) $request->get_param( 'character_id' );
        $wallet       = FangCoin_DB::get_wallet_by_character( $character_id );

        if ( ! $wallet ) {
            return new \WP_REST_Response( [] );
        }

        $transactions = FangCoin_DB::get_transactions( (int) $wallet->id );
        $wallet_id    = (int) $wallet->id;

        $result = [];
        foreach ( $transactions as $tx ) {
            $is_incoming = (int) $tx->to_wallet_id === $wallet_id;

            // For transfers, show the other party's address (anonymous).
            $other_label = '';
            if ( $tx->type === 'transfer' ) {
                $other_label = $is_incoming
                    ? ( $tx->from_address ?: 'Unknown' )
                    : ( $tx->to_address ?: 'Unknown' );
            }

            $result[] = [
                'id'            => (int) $tx->id,
                'type'          => $tx->type,
                'direction'     => $is_incoming ? 'in' : 'out',
                'amount'        => (int) $tx->amount,
                'note'          => $tx->note,
                'other_address' => $other_label,
                'date'          => $tx->created_at,
            ];
        }

        return new \WP_REST_Response( $result );
    }

    /* ---------------------------------------------------------------
     * Code endpoints
     * ------------------------------------------------------------- */

    public static function generate_codes( \WP_REST_Request $request ): \WP_REST_Response {
        $params       = $request->get_json_params();
        $character_id = (int) ( $params['character_id'] ?? 0 );
        $count        = (int) ( $params['count'] ?? 0 );

        if ( ! $character_id || $count < 1 ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid parameters.' ], 400 );
        }

        if ( ! self::is_storyteller() && ! self::user_owns_character( $character_id ) ) {
            return new \WP_REST_Response( [ 'error' => 'You do not own this character.' ], 403 );
        }

        // Chronicle boundary: check outgoing is allowed.
        $chronicle_id = FangCoin_DB::get_character_chronicle_id( $character_id );
        if ( $chronicle_id ) {
            $settings = FangCoin_DB::get_chronicle_settings( $chronicle_id );
            if ( ! $settings['allow_outgoing'] ) {
                return new \WP_REST_Response( [ 'error' => 'This chronicle does not allow FangCoin to leave.' ], 403 );
            }
        }

        $wallet = FangCoin_DB::get_or_create_wallet( $character_id );
        $codes  = FangCoin_DB::generate_codes_from_wallet( (int) $wallet->id, $count, get_current_user_id(), $chronicle_id );

        if ( $codes === false ) {
            return new \WP_REST_Response( [ 'error' => 'Insufficient balance or generation failed.' ], 400 );
        }

        $updated = FangCoin_DB::get_wallet( (int) $wallet->id );

        return new \WP_REST_Response( [
            'success'     => true,
            'codes'       => $codes,
            'new_balance' => (int) $updated->balance,
        ] );
    }

    public static function verify_code( \WP_REST_Request $request ): \WP_REST_Response {
        $code     = sanitize_text_field( $request->get_param( 'code' ) );
        $code_row = FangCoin_DB::verify_code( $code );

        if ( ! $code_row ) {
            return new \WP_REST_Response( [ 'valid' => false, 'status' => 'not_found' ] );
        }

        return new \WP_REST_Response( [ 'valid' => true, 'status' => $code_row->status ] );
    }

    public static function redeem_code( \WP_REST_Request $request ): \WP_REST_Response {
        $params       = $request->get_json_params();
        $code         = sanitize_text_field( $params['code'] ?? '' );
        $character_id = (int) ( $params['character_id'] ?? 0 );

        if ( ! $code || ! $character_id ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid parameters.' ], 400 );
        }

        if ( ! self::is_storyteller() && ! self::user_owns_character( $character_id ) ) {
            return new \WP_REST_Response( [ 'error' => 'You do not own this character.' ], 403 );
        }

        // Chronicle boundary: check that this character's chronicle allows incoming,
        // and that the code's chronicle matches if the depositing chronicle blocks incoming.
        $char_chronicle = FangCoin_DB::get_character_chronicle_id( $character_id );
        if ( $char_chronicle ) {
            $settings = FangCoin_DB::get_chronicle_settings( $char_chronicle );
            if ( ! $settings['allow_incoming'] ) {
                // Incoming blocked: only allow if the code was minted within this same chronicle.
                $code_row = FangCoin_DB::verify_code( $code );
                if ( $code_row ) {
                    $code_chronicle = (int) ( $code_row->chronicle_id ?? 0 );
                    if ( $code_chronicle !== $char_chronicle ) {
                        return new \WP_REST_Response( [ 'error' => 'This chronicle does not allow external FangCoin deposits.' ], 403 );
                    }
                }
            }
        }

        $wallet = FangCoin_DB::get_or_create_wallet( $character_id );
        $ok     = FangCoin_DB::redeem_code( $code, (int) $wallet->id, get_current_user_id() );

        if ( ! $ok ) {
            return new \WP_REST_Response( [ 'error' => 'Code is invalid or already redeemed.' ], 400 );
        }

        $updated = FangCoin_DB::get_wallet( (int) $wallet->id );

        return new \WP_REST_Response( [
            'success'     => true,
            'new_balance' => (int) $updated->balance,
        ] );
    }

    /* ---------------------------------------------------------------
     * ST endpoints
     * ------------------------------------------------------------- */

    public static function st_stats( \WP_REST_Request $request ): \WP_REST_Response {
        $chronicle_id = (int) ( $request->get_param( 'chronicle_id' ) ?? 0 );
        return new \WP_REST_Response( FangCoin_DB::get_stats( $chronicle_id ) );
    }

    public static function st_wallets( \WP_REST_Request $request ): \WP_REST_Response {
        $chronicle_id = (int) ( $request->get_param( 'chronicle_id' ) ?? 0 );
        $wallets = FangCoin_DB::get_all_wallets( $chronicle_id ?: null );
        $result  = [];

        foreach ( $wallets as $w ) {
            $clan = get_post_meta( (int) $w->character_id, '_lotn_clan', true );
            $result[] = [
                'wallet_id'      => (int) $w->id,
                'character_id'   => (int) $w->character_id,
                'character_name' => $w->character_name,
                'type'           => $w->post_type === 'lotn_spc' ? 'SPC' : 'PC',
                'clan'           => $clan ?: '',
                'balance'        => (int) $w->balance,
                'address'        => $w->address,
            ];
        }

        return new \WP_REST_Response( $result );
    }

    /**
     * Get all characters and SPCs for the ST mint selector.
     * PCs require approval. SPCs just need to be published.
     */
    public static function st_characters( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $chronicle_id = (int) ( $request->get_param( 'chronicle_id' ) ?? 0 );

        // Approved PCs.
        $pc_sql = "SELECT p.ID, p.post_title, 'PC' AS type,
                    MAX(CASE WHEN pm.meta_key = '_lotn_clan' THEN pm.meta_value END) AS clan
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = '_lotn_validated' AND v.meta_value = '1'
             JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_lotn_chr_sub_status' AND s.meta_value = 'approved'
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_lotn_clan'
             WHERE p.post_type = 'lotn_sheet' AND p.post_status = 'publish'";

        if ( $chronicle_id ) {
            $pc_sql .= $wpdb->prepare(
                " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} hc WHERE hc.post_id = p.ID AND hc.meta_key = '_lotn_home_chronicle_id' AND hc.meta_value = %d)",
                $chronicle_id
            );
        }

        $pc_sql .= " GROUP BY p.ID ORDER BY p.post_title ASC";
        $pcs = $wpdb->get_results( $pc_sql );

        // Published SPCs (no approval meta required).
        $spc_sql = "SELECT p.ID, p.post_title, 'SPC' AS type,
                    MAX(CASE WHEN pm.meta_key = '_lotn_clan' THEN pm.meta_value END) AS clan
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_lotn_clan'
             WHERE p.post_type = 'lotn_spc' AND p.post_status = 'publish'";

        if ( $chronicle_id ) {
            $spc_sql .= $wpdb->prepare(
                " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} sc WHERE sc.post_id = p.ID AND sc.meta_key = '_lotn_spc_chronicle_id' AND sc.meta_value = %d)",
                $chronicle_id
            );
        }

        $spc_sql .= " GROUP BY p.ID ORDER BY p.post_title ASC";
        $spcs = $wpdb->get_results( $spc_sql );

        $result = [];
        foreach ( array_merge( $pcs, $spcs ) as $char ) {
            $result[] = [
                'id'   => (int) $char->ID,
                'name' => $char->post_title,
                'type' => $char->type,
                'clan' => $char->clan ?: '',
            ];
        }

        usort( $result, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

        return new \WP_REST_Response( $result );
    }

    public static function st_mint_to_wallet( \WP_REST_Request $request ): \WP_REST_Response {
        $params        = $request->get_json_params();
        $amount        = (int) ( $params['amount'] ?? 0 );
        $note          = sanitize_text_field( $params['note'] ?? '' );
        $st_chronicle  = (int) ( $params['chronicle_id'] ?? 0 );

        // Accept either character_id or spc_id.
        $post_id = (int) ( $params['character_id'] ?? $params['spc_id'] ?? 0 );

        if ( ! $post_id || $amount < 1 ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid parameters.' ], 400 );
        }

        // Chronicle boundary: if the ST's active chronicle has incoming blocked,
        // only allow minting to characters within that same chronicle.
        if ( $st_chronicle ) {
            $char_chronicle = FangCoin_DB::get_character_chronicle_id( $post_id );
            $settings       = FangCoin_DB::get_chronicle_settings( $st_chronicle );
            if ( ! $settings['allow_incoming'] && $char_chronicle !== $st_chronicle ) {
                return new \WP_REST_Response( [ 'error' => 'This chronicle does not allow external FangCoin. The character must belong to this chronicle.' ], 403 );
            }
        }

        $wallet = FangCoin_DB::get_or_create_wallet( $post_id );
        $ok     = FangCoin_DB::mint_to_wallet( (int) $wallet->id, $amount, get_current_user_id(), $note );

        if ( ! $ok ) {
            return new \WP_REST_Response( [ 'error' => 'Mint failed.' ], 500 );
        }

        $updated = FangCoin_DB::get_wallet( (int) $wallet->id );

        return new \WP_REST_Response( [
            'success'     => true,
            'new_balance' => (int) $updated->balance,
        ] );
    }

    public static function st_mint_codes( \WP_REST_Request $request ): \WP_REST_Response {
        $params       = $request->get_json_params();
        $count        = (int) ( $params['count'] ?? 0 );
        $chronicle_id = (int) ( $params['chronicle_id'] ?? 0 );

        if ( $count < 1 || $count > 200 ) {
            return new \WP_REST_Response( [ 'error' => 'Count must be between 1 and 200.' ], 400 );
        }

        $codes = FangCoin_DB::mint_codes( $count, get_current_user_id(), $chronicle_id );

        if ( $codes === false ) {
            return new \WP_REST_Response( [ 'error' => 'Mint failed.' ], 500 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'codes' => $codes, 'count' => count( $codes ) ] );
    }

    /* ---------------------------------------------------------------
     * Chronicle settings endpoints
     * ------------------------------------------------------------- */

    public static function get_chronicle_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $chronicle_id = (int) $request->get_param( 'chronicle_id' );
        $settings     = FangCoin_DB::get_chronicle_settings( $chronicle_id );
        return new \WP_REST_Response( $settings );
    }

    public static function save_chronicle_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $chronicle_id  = (int) $request->get_param( 'chronicle_id' );
        $params        = $request->get_json_params();
        $allow_incoming = (bool) ( $params['allow_incoming'] ?? true );
        $allow_outgoing = (bool) ( $params['allow_outgoing'] ?? true );

        FangCoin_DB::save_chronicle_settings( $chronicle_id, $allow_incoming, $allow_outgoing );

        return new \WP_REST_Response( [
            'success'        => true,
            'allow_incoming' => $allow_incoming,
            'allow_outgoing' => $allow_outgoing,
        ] );
    }
}
