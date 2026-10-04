<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FangCoin_ST {

    const TAB = 'fangcoin';

    public static function register() {
        add_filter( 'lotn_st_dashboard_tabs', [ __CLASS__, 'render_tab_link' ], 10, 4 );
        add_action( 'lotn_st_dashboard_tab', [ __CLASS__, 'maybe_render_tab' ], 10, 2 );
    }

    /**
     * Render the tab link, matching Chronicle Tools' button styling.
     *
     * Filter signature: apply_filters( 'lotn_st_dashboard_tabs', '', $active_tab, $tab_base, $chronicle_id )
     */
    public static function render_tab_link( $html, $active_tab, $tab_base, $chronicle_id ) {
        $url = add_query_arg( 'lotn_tab', self::TAB, $tab_base );

        return $html . '<a href="' . esc_url( $url ) . '" class="lotn-fe-btn lotn-fe-btn-sm'
            . ( self::TAB === $active_tab ? ' lotn-fe-btn-primary' : '' )
            . '">FangCoin</a>';
    }

    /**
     * Render the tab content when FangCoin is the active tab.
     *
     * Action signature: do_action( 'lotn_st_dashboard_tab', $active_tab, $chronicle_id )
     */
    public static function maybe_render_tab( $active_tab, $chronicle_id = 0 ) {
        if ( self::TAB !== $active_tab ) {
            return;
        }

        wp_enqueue_style( 'fangcoin-css', FANGCOIN_PLUGIN_URL . 'assets/css/fangcoin.css', [], FANGCOIN_VERSION );
        wp_enqueue_script( 'fangcoin-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], '1.0.0', true );
        wp_enqueue_script( 'fangcoin-st-js', FANGCOIN_PLUGIN_URL . 'assets/js/fangcoin-st.js', [ 'fangcoin-qrcode' ], FANGCOIN_VERSION, true );

        wp_localize_script( 'fangcoin-st-js', 'FangCoinSTData', [
            'restBase'    => esc_url_raw( rest_url( 'fangcoin/v1' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'siteUrl'     => home_url(),
            'printUrl'    => home_url( '?fangcoin_print=1' ),
            'chronicleId' => absint( $chronicle_id ),
        ] );

        ?>
        <div id="fangcoin-st" class="fc-st-app">
            <!-- Chronicle settings -->
            <div class="fc-st-section" id="fc-st-chronicle-settings">
                <h3 class="fc-st-section-title">Chronicle economy settings</h3>
                <p class="fc-st-help">Control whether FangCoin can enter or leave this chronicle. Useful for one-shot games and sponsored events that should have a sealed economy.</p>
                <div class="fc-st-toggles">
                    <label class="fc-st-toggle">
                        <input type="checkbox" id="fc-st-allow-incoming" checked />
                        <span class="fc-st-toggle-label">Allow incoming FangCoin</span>
                        <span class="fc-st-toggle-help">When off, only FangCoin minted within this chronicle can be deposited. Cross-chronicle transfers and external QR codes are blocked.</span>
                    </label>
                    <label class="fc-st-toggle">
                        <input type="checkbox" id="fc-st-allow-outgoing" checked />
                        <span class="fc-st-toggle-label">Allow outgoing FangCoin</span>
                        <span class="fc-st-toggle-help">When off, characters in this chronicle cannot send FangCoin to other chronicles or withdraw to QR codes.</span>
                    </label>
                </div>
                <div id="fc-st-settings-msg" class="fc-msg"></div>
            </div>

            <!-- Stats row -->
            <div class="fc-stats-row" id="fc-st-stats">
                <div class="fc-stat-card">
                    <div class="fc-stat-label">Total in circulation</div>
                    <div class="fc-stat-value" id="fc-st-total">0</div>
                </div>
                <div class="fc-stat-card">
                    <div class="fc-stat-label">In wallets</div>
                    <div class="fc-stat-value" id="fc-st-wallets-total">0</div>
                </div>
                <div class="fc-stat-card">
                    <div class="fc-stat-label">Active QR codes</div>
                    <div class="fc-stat-value" id="fc-st-codes">0</div>
                </div>
                <div class="fc-stat-card">
                    <div class="fc-stat-label">Active wallets</div>
                    <div class="fc-stat-value" id="fc-st-wallet-count">0</div>
                </div>
            </div>

            <!-- Mint section -->
            <div class="fc-st-section">
                <h3 class="fc-st-section-title">Mint FangCoin</h3>
                <p class="fc-st-help">Introduce new FangCoin into the game economy. This creates currency from nothing.</p>

                <div class="fc-st-form-row">
                    <div class="fc-st-form-group" style="flex: 1;">
                        <label for="fc-st-mint-char">Recipient</label>
                        <select id="fc-st-mint-char">
                            <option value="">Select character or SPC...</option>
                        </select>
                    </div>
                    <div class="fc-st-form-group" style="flex: 0 0 120px;">
                        <label for="fc-st-mint-amount">Amount</label>
                        <input type="number" id="fc-st-mint-amount" min="1" value="1" />
                    </div>
                </div>
                <div class="fc-st-form-group">
                    <label for="fc-st-mint-note">Reason (logged, not visible to players)</label>
                    <input type="text" id="fc-st-mint-note" placeholder="Downtime reward, plot payout, etc." />
                </div>
                <button id="fc-st-mint-btn" class="fc-st-btn fc-st-btn-primary">Mint to wallet</button>
                <div id="fc-st-mint-msg" class="fc-msg"></div>
            </div>

            <!-- Batch QR generation -->
            <div class="fc-st-section">
                <h3 class="fc-st-section-title">Batch QR generation</h3>
                <p class="fc-st-help">Generate QR codes to print and apply to poker chip stickers, or as paper cards for handouts. These are minted from nothing.</p>

                <div class="fc-st-form-row">
                    <div class="fc-st-form-group" style="flex: 0 0 160px;">
                        <label for="fc-st-batch-count">Number of coins</label>
                        <input type="number" id="fc-st-batch-count" min="1" max="200" value="10" />
                    </div>
                </div>
                <div class="fc-st-form-row">
                    <button id="fc-st-batch-stickers-btn" class="fc-st-btn fc-st-btn-primary">Print as chip stickers</button>
                    <button id="fc-st-batch-cards-btn" class="fc-st-btn fc-st-btn-secondary">Print as paper cards</button>
                </div>
                <div id="fc-st-batch-msg" class="fc-msg"></div>
            </div>

            <!-- Wallet balances -->
            <div class="fc-st-section">
                <h3 class="fc-st-section-title">Wallet balances</h3>
                <div class="fc-filter-row">
                    <button class="fc-filter-btn active" data-filter="all">All</button>
                    <button class="fc-filter-btn" data-filter="PC">PCs</button>
                    <button class="fc-filter-btn" data-filter="SPC">SPCs</button>
                </div>
                <div id="fc-st-wallet-list" class="fc-wallet-list">
                    <p class="fc-empty" style="color:#999;">Loading...</p>
                </div>
            </div>
        </div>
        <?php
    }
}
