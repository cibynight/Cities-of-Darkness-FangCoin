(function () {
    'use strict';

    var REST = FangCoinData.restBase;
    var NONCE = FangCoinData.nonce;
    var SITE_URL = FangCoinData.siteUrl;
    var PRINT_URL = FangCoinData.printUrl;

    var currentCharId = 0;
    var currentAddress = '';
    var currentView = 'acct';
    var selectedFormat = 'card';
    var scanner = null;
    var scanTarget = null; // 'verify', 'redeem', or 'send'

    var views = {
        acct:     { label: 'Account' },
        send:     { label: 'Send' },
        withdraw: { label: 'Withdraw' },
        deposit:  { label: 'Deposit' }
    };

    function $(id) { return document.getElementById(id); }

    function api(path, opts) {
        opts = opts || {};
        var headers = { 'X-WP-Nonce': NONCE };
        if (opts.body) headers['Content-Type'] = 'application/json';
        return fetch(REST + path, {
            method: opts.method || 'GET',
            headers: headers,
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        }).then(function (r) { return r.json(); });
    }

    function formatDate(d) {
        return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }

    /* ----- Navigation ----- */

    function renderNav() {
        var c = $('fc-nav-btns');
        c.innerHTML = '';
        Object.keys(views).forEach(function (k) {
            if (k === currentView) return;
            var btn = document.createElement('button');
            btn.className = 'fc-nav-btn';
            btn.textContent = views[k].label;
            btn.dataset.view = k;
            btn.addEventListener('click', function () { switchView(this.dataset.view); });
            c.appendChild(btn);
        });
    }

    function switchView(id) {
        stopScanner();
        currentView = id;
        document.querySelectorAll('.fc-view').forEach(function (v) { v.classList.remove('active'); });
        $('fc-view-' + id).classList.add('active');
        renderNav();
    }

    /* ----- Characters ----- */

    function loadCharacters() {
        api('/wallets').then(function (wallets) {
            var sel = $('fc-char-select');
            sel.innerHTML = '';
            if (!wallets.length) {
                sel.innerHTML = '<option value="">No approved characters</option>';
                return;
            }
            wallets.forEach(function (w) {
                var opt = document.createElement('option');
                opt.value = w.character_id;
                opt.textContent = w.character_name + (w.clan ? ' \u00b7 ' + w.clan : '');
                opt.dataset.walletId = w.wallet_id;
                opt.dataset.balance = w.balance;
                opt.dataset.address = w.address;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', onCharChange);
            sel.dispatchEvent(new Event('change'));
        });
    }

    function onCharChange() {
        var sel = $('fc-char-select');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;

        currentCharId = parseInt(opt.value);
        currentAddress = opt.dataset.address;
        $('fc-balance').textContent = opt.dataset.balance + ' FC';

        renderWalletAddress();
        loadTransactions();
    }

    /* ----- Wallet address display ----- */

    function renderWalletAddress() {
        var container = $('fc-address-qr');
        var codeEl = $('fc-address-code');

        container.innerHTML = '';
        codeEl.textContent = currentAddress;

        var qrData = SITE_URL + '?fangcoin_send=' + encodeURIComponent(currentAddress);
        new QRCode(container, {
            text: qrData,
            width: 96,
            height: 96,
            colorDark: '#e8e8e8',
            colorLight: '#0d0d0d',
            correctLevel: QRCode.CorrectLevel.M,
        });

        $('fc-address-copy').onclick = function () {
            var btn = this;
            navigator.clipboard.writeText(currentAddress).then(function () {
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = 'Copy address';
                    btn.classList.remove('copied');
                }, 2000);
            });
        };
    }

    /* ----- Transactions ----- */

    function loadTransactions() {
        if (!currentCharId) return;
        api('/transactions/' + currentCharId).then(function (txs) {
            var container = $('fc-transactions');
            var income = 0, spending = 0;
            var thirtyAgo = Date.now() - 30 * 86400000;

            if (!txs.length) {
                container.innerHTML = '<p class="fc-empty">No transactions yet.</p>';
                $('fc-income').textContent = '+0';
                $('fc-spending').textContent = '-0';
                return;
            }

            container.innerHTML = '';
            txs.forEach(function (tx) {
                var d = new Date(tx.date).getTime();
                if (d >= thirtyAgo) {
                    if (tx.direction === 'in') income += tx.amount;
                    else spending += tx.amount;
                }

                var ico, cls, pfx, label, meta;
                if (tx.type === 'generate_code') {
                    ico = 'fc-tx-ico-out'; cls = 'fc-tx-out'; pfx = '-'; label = 'Withdrawal';
                    meta = formatDate(tx.date) + ' \u00b7 ' + tx.amount + ' FC to cash';
                } else if (tx.type === 'redeem_code') {
                    ico = 'fc-tx-ico-qr'; cls = 'fc-tx-in'; pfx = '+'; label = 'Deposit';
                    meta = formatDate(tx.date) + ' \u00b7 QR code redeemed';
                } else if (tx.type === 'mint' || tx.type === 'mint_code') {
                    ico = 'fc-tx-ico-mint'; cls = 'fc-tx-in'; pfx = '+'; label = 'Received from FangCoin Network';
                    meta = formatDate(tx.date) + (tx.note ? ' \u00b7 ' + esc(tx.note) : '');
                } else if (tx.direction === 'in') {
                    ico = 'fc-tx-ico-in'; cls = 'fc-tx-in'; pfx = '+';
                    label = 'Received from ' + esc(tx.other_address);
                    meta = formatDate(tx.date) + (tx.note ? ' \u00b7 ' + esc(tx.note) : ' \u00b7 Transfer');
                } else {
                    ico = 'fc-tx-ico-out'; cls = 'fc-tx-out'; pfx = '-';
                    label = 'Sent to ' + esc(tx.other_address);
                    meta = formatDate(tx.date) + (tx.note ? ' \u00b7 ' + esc(tx.note) : ' \u00b7 Transfer');
                }

                var row = document.createElement('div');
                row.className = 'fc-tx';
                row.innerHTML =
                    '<div class="fc-tx-ico ' + ico + '">\u25CF</div>' +
                    '<div class="fc-tx-info"><p class="fc-tx-name">' + label + '</p><p class="fc-tx-meta">' + meta + '</p></div>' +
                    '<div class="fc-tx-amt ' + cls + '">' + pfx + tx.amount + ' FC</div>';
                container.appendChild(row);
            });

            $('fc-income').textContent = '+' + income;
            $('fc-spending').textContent = '-' + spending;
        });
    }

    /* ----- Send ----- */

    function initSend() {
        $('fc-transfer-btn').addEventListener('click', function () {
            var addr = $('fc-send-address').value.trim().toLowerCase();
            var amount = parseInt($('fc-transfer-amount').value);
            var note = $('fc-transfer-note').value.trim();
            var msg = $('fc-transfer-msg');

            if (!addr) { msg.className = 'fc-msg fc-msg-error'; msg.textContent = 'Enter a wallet address.'; return; }
            if (!amount || amount < 1) { msg.className = 'fc-msg fc-msg-error'; msg.textContent = 'Enter a valid amount.'; return; }

            $('fc-transfer-btn').disabled = true;

            api('/transfer', {
                method: 'POST',
                body: { from_character_id: currentCharId, to_address: addr, amount: amount, note: note },
            }).then(function (res) {
                $('fc-transfer-btn').disabled = false;
                if (res.error) { msg.className = 'fc-msg fc-msg-error'; msg.textContent = res.error; return; }
                msg.className = 'fc-msg fc-msg-success';
                msg.textContent = 'Sent ' + amount + ' FC.';
                $('fc-balance').textContent = res.new_balance + ' FC';
                updateBal(res.new_balance);
                $('fc-transfer-amount').value = '1';
                $('fc-transfer-note').value = '';
                $('fc-send-address').value = '';
                $('fc-send-address-status').innerHTML = '';
                loadTransactions();
            });
        });

        $('fc-send-scan-btn').addEventListener('click', function () {
            startScanner('send');
        });
    }

    /* ----- Withdraw ----- */

    function initWithdraw() {
        document.querySelectorAll('.fc-fmt-opt').forEach(function (opt) {
            opt.addEventListener('click', function () {
                document.querySelectorAll('.fc-fmt-opt').forEach(function (o) { o.classList.remove('selected'); });
                this.classList.add('selected');
                selectedFormat = this.dataset.format;
            });
        });

        $('fc-withdraw-btn').addEventListener('click', function () {
            var count = parseInt($('fc-gen-count').value);
            var msg = $('fc-withdraw-msg');
            if (!count || count < 1) { msg.className = 'fc-msg fc-msg-error'; msg.textContent = 'Enter a valid number.'; return; }

            $('fc-withdraw-btn').disabled = true;
            api('/codes/generate', {
                method: 'POST',
                body: { character_id: currentCharId, count: count },
            }).then(function (res) {
                $('fc-withdraw-btn').disabled = false;
                if (res.error) { msg.className = 'fc-msg fc-msg-error'; msg.textContent = res.error; return; }
                msg.className = 'fc-msg fc-msg-success';
                msg.textContent = 'Withdrew ' + res.codes.length + ' FC.';
                $('fc-balance').textContent = res.new_balance + ' FC';
                updateBal(res.new_balance);
                loadTransactions();
                window.open(PRINT_URL + '&codes=' + encodeURIComponent(res.codes.join(',')) + '&format=' + selectedFormat, '_blank');
            });
        });
    }

    /* ----- Deposit (verify + redeem) ----- */

    function formatCodeInput(input) {
        input.addEventListener('input', function () {
            var v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (v.length > 2 && v.substring(0, 2) === 'FC') v = 'FC' + v.substring(2);
            var f = v.length <= 2 ? v : v.length <= 6 ? v.substring(0, 2) + '-' + v.substring(2) : v.substring(0, 2) + '-' + v.substring(2, 6) + '-' + v.substring(6, 10);
            input.value = f;
        });
    }

    function initDeposit() {
        formatCodeInput($('fc-verify-code'));
        formatCodeInput($('fc-redeem-code'));

        $('fc-verify-btn').addEventListener('click', function () {
            var code = $('fc-verify-code').value.trim();
            var r = $('fc-verify-result');
            if (!code) { r.innerHTML = ''; return; }
            api('/codes/verify/' + encodeURIComponent(code)).then(function (res) {
                if (!res.valid) r.innerHTML = '<span class="fc-verify-status fc-status-invalid">Code not found</span>';
                else if (res.status === 'active') r.innerHTML = '<span class="fc-verify-status fc-status-valid">Valid, unredeemed</span>';
                else r.innerHTML = '<span class="fc-verify-status fc-status-redeemed">Already redeemed</span>';
            });
        });

        $('fc-redeem-btn').addEventListener('click', function () {
            var code = $('fc-redeem-code').value.trim();
            var r = $('fc-redeem-result');
            if (!code) { r.innerHTML = '<span class="fc-verify-status fc-status-invalid">Enter a claim code.</span>'; return; }
            if (!currentCharId) { r.innerHTML = '<span class="fc-verify-status fc-status-invalid">Select a character.</span>'; return; }

            $('fc-redeem-btn').disabled = true;
            api('/codes/redeem', {
                method: 'POST',
                body: { code: code, character_id: currentCharId },
            }).then(function (res) {
                $('fc-redeem-btn').disabled = false;
                if (res.error) { r.innerHTML = '<span class="fc-verify-status fc-status-redeemed">' + esc(res.error) + '</span>'; return; }
                r.innerHTML = '<span class="fc-verify-status fc-status-valid">Deposited! Balance: ' + res.new_balance + ' FC</span>';
                $('fc-balance').textContent = res.new_balance + ' FC';
                updateBal(res.new_balance);
                $('fc-redeem-code').value = '';
                loadTransactions();
            });
        });

        $('fc-verify-scan-btn').addEventListener('click', function () { startScanner('verify'); });
        $('fc-redeem-scan-btn').addEventListener('click', function () { startScanner('redeem'); });
    }

    /* ----- QR Scanner ----- */

    var scanProcessing = false;

    function startScanner(target) {
        scanTarget = target;
        scanProcessing = false;
        $('fc-scanner-wrap').style.display = 'block';
        $('fc-scanner-label').textContent = 'Point camera at QR code';

        if (scanner) {
            try { scanner.clear(); } catch (e) {}
        }

        scanner = new Html5Qrcode('fc-scanner');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 200, height: 200 } },
            onScanSuccess,
            function () {}
        ).catch(function (err) {
            $('fc-scanner-label').textContent = 'Camera not available. Enter the code manually.';
            console.error('Scanner error:', err);
        });
    }

    function stopScanner() {
        var s = scanner;
        scanner = null;
        if (s) {
            s.stop().then(function () {
                try { s.clear(); } catch (e) {}
            }).catch(function () {
                try { s.clear(); } catch (e) {}
            });
        }
        $('fc-scanner-wrap').style.display = 'none';
    }

    function onScanSuccess(decodedText) {
        if (scanProcessing) return;
        scanProcessing = true;

        var target = scanTarget;
        stopScanner();

        var text = decodedText.trim();
        var claimCode = extractClaimCode(text);
        var walletAddr = extractWalletAddress(text);

        if (target === 'send') {
            if (walletAddr) {
                $('fc-send-address').value = walletAddr;
                $('fc-send-address-status').innerHTML = '<span class="fc-verify-status fc-status-valid">Address scanned</span>';
            } else if (claimCode) {
                switchView('deposit');
                $('fc-redeem-code').value = claimCode;
            } else {
                $('fc-send-address-status').innerHTML = '<span class="fc-verify-status fc-status-invalid">Unrecognized QR code</span>';
            }
        } else if (target === 'verify') {
            if (claimCode) {
                $('fc-verify-code').value = claimCode;
                setTimeout(function () { $('fc-verify-btn').click(); }, 100);
            } else if (walletAddr) {
                $('fc-verify-result').innerHTML = '<span class="fc-verify-status fc-status-invalid">That\'s a wallet address, not a coin.</span>';
            } else {
                $('fc-verify-result').innerHTML = '<span class="fc-verify-status fc-status-invalid">Unrecognized QR code</span>';
            }
        } else if (target === 'redeem') {
            if (claimCode) {
                $('fc-redeem-code').value = claimCode;
            } else if (walletAddr) {
                $('fc-redeem-result').innerHTML = '<span class="fc-verify-status fc-status-invalid">That\'s a wallet address, not a coin.</span>';
            } else {
                $('fc-redeem-result').innerHTML = '<span class="fc-verify-status fc-status-invalid">Unrecognized QR code</span>';
            }
        }
    }

    /**
     * Extract a claim code (FC-XXXX-XXXX) from a scanned string.
     */
    function extractClaimCode(str) {
        // Try URL query param first.
        try {
            var url = new URL(str);
            var param = url.searchParams.get('fangcoin_verify');
            if (param) return param.toUpperCase();
        } catch (e) {}

        // Try regex on the full string (handles malformed URLs).
        var match = str.match(/fangcoin_verify=([A-Z0-9\-]+)/i);
        if (match) return match[1].toUpperCase();

        // Try raw code.
        match = str.trim().match(/^FC-[A-Z0-9]{4}-[A-Z0-9]{4}$/i);
        if (match) return match[0].toUpperCase();

        return null;
    }

    /**
     * Extract a wallet address (fc1xxxxxxxx) from a scanned string.
     */
    function extractWalletAddress(str) {
        // Try URL query param first.
        try {
            var url = new URL(str);
            var param = url.searchParams.get('fangcoin_send');
            if (param) return param.toLowerCase();
        } catch (e) {}

        // Try regex on the full string.
        var match = str.match(/fangcoin_send=([a-z0-9]+)/i);
        if (match) return match[1].toLowerCase();

        // Try raw address.
        match = str.trim().match(/^fc1[a-z0-9]{8}$/i);
        if (match) return match[0].toLowerCase();

        return null;
    }

    /* ----- Helpers ----- */

    function updateBal(n) {
        var sel = $('fc-char-select');
        var opt = sel.options[sel.selectedIndex];
        if (opt) opt.dataset.balance = n;
    }

    /* ----- Init ----- */

    function init() {
        if (!$('fangcoin-app')) return;
        renderNav();
        loadCharacters();
        initSend();
        initWithdraw();
        initDeposit();
        $('fc-scanner-close').addEventListener('click', stopScanner);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
