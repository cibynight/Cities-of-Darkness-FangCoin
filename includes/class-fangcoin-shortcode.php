<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FangCoin_Shortcode {

    public static function register() {
        add_shortcode( 'fangcoin', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>You must be logged in to use FangCoin.</p>';
        }

        wp_enqueue_style( 'fangcoin-css', FANGCOIN_PLUGIN_URL . 'assets/css/fangcoin.css', [], FANGCOIN_VERSION );
        wp_enqueue_script( 'fangcoin-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], '1.0.0', true );
        wp_enqueue_script( 'html5-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js', [], '2.3.8', true );
        wp_enqueue_script( 'fangcoin-js', FANGCOIN_PLUGIN_URL . 'assets/js/fangcoin.js', [ 'fangcoin-qrcode', 'html5-qrcode' ], FANGCOIN_VERSION, true );

        wp_localize_script( 'fangcoin-js', 'FangCoinData', [
            'restBase' => esc_url_raw( rest_url( 'fangcoin/v1' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'siteUrl'  => home_url(),
            'printUrl' => home_url( '?fangcoin_print=1' ),
        ] );

        ob_start();
        ?>
        <div class="fangcoin-app">
            <div class="fc-wrap" id="fangcoin-app">

                <div class="fc-sidebar">
                    <div class="fc-sidebar-brand">
                        <div class="fc-brand-icon">FC</div>
                        <span class="fc-brand-name">FangCoin</span>
                    </div>

                    <select id="fc-char-select" class="fc-char-select">
                        <option value="">Loading...</option>
                    </select>

                    <div class="fc-bal">
                        <div class="fc-bal-coin fc-bal-coin-mobile">FC</div>
                        <p class="fc-bal-num" id="fc-balance">0 FC</p>
                        <p class="fc-bal-label">Available balance</p>
                    </div>

                    <div class="fc-btns" id="fc-nav-btns"></div>

                    <div class="fc-flow" id="fc-flow-summary">
                        <div class="fc-flow-cell">
                            <div class="fc-flow-label"><span class="fc-flow-dot" style="background:#4ade80;"></span> In</div>
                            <div class="fc-flow-val" style="color:#4ade80;" id="fc-income">+0</div>
                        </div>
                        <div class="fc-flow-cell">
                            <div class="fc-flow-label"><span class="fc-flow-dot" style="background:#f87171;"></span> Out</div>
                            <div class="fc-flow-val" style="color:#f87171;" id="fc-spending">-0</div>
                        </div>
                    </div>
                    <div class="fc-flow-period">Last 30 days</div>
                </div>

                <div class="fc-body">

                    <!-- QR Scanner (shared, shown inline when activated) -->
                    <div id="fc-scanner-wrap" class="fc-scanner-wrap" style="display:none;">
                        <div class="fc-scanner-header">
                            <span class="fc-scanner-label" id="fc-scanner-label">Point camera at QR code</span>
                            <button id="fc-scanner-close" class="fc-scanner-close">&times;</button>
                        </div>
                        <div id="fc-scanner" class="fc-scanner"></div>
                    </div>

                    <!-- Account view -->
                    <div class="fc-view active" id="fc-view-acct">
                        <div class="fc-card fc-address-card" style="background:#0d0d0d;color:#e8e8e8;">
                            <div class="fc-card-title">Your wallet address</div>
                            <div class="fc-address-row">
                                <div id="fc-address-qr" class="fc-address-qr"></div>
                                <div class="fc-address-info">
                                    <code class="fc-address-code" id="fc-address-code"></code>
                                    <p class="fc-address-help">Show this QR code to receive FangCoin from other players.</p>
                                    <button id="fc-address-copy" class="fc-address-copy">Copy address</button>
                                </div>
                            </div>
                        </div>

                        <div class="fc-sec-header">
                            <span class="fc-sec-title">Recent activity</span>
                        </div>
                        <div id="fc-transactions">
                            <p class="fc-empty">No transactions yet.</p>
                        </div>
                    </div>

                    <!-- Send view -->
                    <div class="fc-view" id="fc-view-send">
                        <div class="fc-view-title">Send FangCoin</div>
                        <div class="fc-card">
                            <p class="fc-card-help">Send FC to a wallet address. Scan the recipient's QR code or enter their address manually.</p>
                            <div class="fc-fieldset">
                                <div class="fc-label">Recipient address</div>
                                <div class="fc-verify-row">
                                    <input type="text" id="fc-send-address" placeholder="fc1xxxxxxxx" class="fc-input fc-input-mono" />
                                    <button id="fc-send-scan-btn" class="fc-verify-btn">Scan</button>
                                </div>
                                <div id="fc-send-address-status" class="fc-address-status"></div>
                            </div>
                            <div class="fc-send-row">
                                <div class="fc-fieldset">
                                    <div class="fc-label">Note (optional)</div>
                                    <input type="text" id="fc-transfer-note" placeholder="Payment for services rendered..." class="fc-input" />
                                </div>
                                <div class="fc-fieldset">
                                    <div class="fc-label">Amount</div>
                                    <input type="number" id="fc-transfer-amount" min="1" value="1" class="fc-input fc-input-lg" />
                                </div>
                            </div>
                            <button id="fc-transfer-btn" class="fc-submit">Send</button>
                            <div id="fc-transfer-msg" class="fc-msg"></div>
                        </div>
                    </div>

                    <!-- Withdraw view -->
                    <div class="fc-view" id="fc-view-withdraw">
                        <div class="fc-view-title">Withdraw to cash</div>
                        <div class="fc-info-box">
                            <div class="fc-info-icon">&#9432;</div>
                            <div style="font-size:13px;color:#888;line-height:1.5;">Each QR code is 1 FC. Once withdrawn, the coins are anonymous bearer cash. If you lose them, they're gone.</div>
                        </div>
                        <div class="fc-card">
                            <div class="fc-fieldset">
                                <div class="fc-label">Number of coins</div>
                                <input type="number" id="fc-gen-count" min="1" value="1" class="fc-input" style="width:120px;text-align:center;" />
                            </div>
                            <div class="fc-label">Print format</div>
                            <div class="fc-fmt-row">
                                <div class="fc-fmt-opt selected" data-format="card">
                                    <div class="fc-fmt-label">Paper cards</div>
                                    <div class="fc-fmt-sub">2.5" x 3.5" cut-outs</div>
                                </div>
                                <div class="fc-fmt-opt" data-format="sticker">
                                    <div class="fc-fmt-label">Chip stickers</div>
                                    <div class="fc-fmt-sub">1" x 1" DYMO labels</div>
                                </div>
                            </div>
                            <button id="fc-withdraw-btn" class="fc-submit">Withdraw and print</button>
                            <div id="fc-withdraw-msg" class="fc-msg"></div>
                        </div>
                    </div>

                    <!-- Deposit view -->
                    <div class="fc-view" id="fc-view-deposit">
                        <div class="fc-view-title">Deposit</div>
                        <div class="fc-two-col">
                            <div class="fc-card">
                                <div class="fc-card-title">Verify a coin</div>
                                <p class="fc-card-help">Check if a coin is legitimate without redeeming it.</p>
                                <div class="fc-verify-row">
                                    <input type="text" id="fc-verify-code" placeholder="FC-XXXX-XXXX" class="fc-input fc-input-mono" maxlength="14" />
                                    <button id="fc-verify-btn" class="fc-verify-btn">Verify</button>
                                </div>
                                <button id="fc-verify-scan-btn" class="fc-scan-btn">Scan to verify</button>
                                <div id="fc-verify-result" class="fc-verify-result"></div>
                            </div>
                            <div class="fc-card">
                                <div class="fc-card-title">Deposit to account</div>
                                <p class="fc-card-help">Redeem a physical coin. The QR code is burned after deposit.</p>
                                <div class="fc-verify-row">
                                    <input type="text" id="fc-redeem-code" placeholder="FC-XXXX-XXXX" class="fc-input fc-input-mono" maxlength="14" />
                                    <button id="fc-redeem-btn" class="fc-verify-btn fc-deposit-btn">Deposit</button>
                                </div>
                                <button id="fc-redeem-scan-btn" class="fc-scan-btn">Scan to deposit</button>
                                <div id="fc-redeem-result" class="fc-verify-result"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
