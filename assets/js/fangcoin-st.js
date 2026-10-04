(function () {
    'use strict';

    var REST = FangCoinSTData.restBase;
    var NONCE = FangCoinSTData.nonce;
    var PRINT_URL = FangCoinSTData.printUrl;
    var CHRONICLE_ID = FangCoinSTData.chronicleId || 0;

    var allWallets = [];

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

    function loadStats() {
        api('/st/stats' + (CHRONICLE_ID ? '?chronicle_id=' + CHRONICLE_ID : '')).then(function (stats) {
            $('fc-st-total').textContent = stats.total_circulation;
            $('fc-st-wallets-total').textContent = stats.total_in_wallets;
            $('fc-st-codes').textContent = stats.active_codes;
            $('fc-st-wallet-count').textContent = stats.wallets_with_balance;
        });
    }

    function loadWallets() {
        api('/st/wallets' + (CHRONICLE_ID ? '?chronicle_id=' + CHRONICLE_ID : '')).then(function (wallets) {
            allWallets = wallets;
            renderWallets('all');
            populateCharSelect(wallets);
        });
    }

    function renderWallets(filter) {
        var container = $('fc-st-wallet-list');
        var filtered = allWallets;

        if (filter !== 'all') {
            filtered = allWallets.filter(function (w) { return w.type === filter; });
        }

        if (!filtered.length) {
            container.innerHTML = '<p class="fc-empty" style="padding: 16px;">No wallets found.</p>';
            return;
        }

        container.innerHTML = '';
        filtered.forEach(function (w) {
            var initials = w.character_name.split(/\s+/).map(function (n) { return n[0]; }).join('').substring(0, 2).toUpperCase();
            var row = document.createElement('div');
            row.className = 'fc-wallet-row';
            row.innerHTML =
                '<div class="fc-wallet-avatar' + (w.type === 'SPC' ? ' fc-wallet-avatar-spc' : '') + '">' + initials + '</div>' +
                '<div class="fc-wallet-info">' +
                    '<p class="fc-wallet-name">' + escHtml(w.character_name) + '</p>' +
                    '<p class="fc-wallet-clan">' + escHtml(w.clan || 'Unknown') + ' · ' + w.type + ' · <code>' + escHtml(w.address) + '</code></p>' +
                '</div>' +
                '<div class="fc-wallet-balance">' + w.balance + '</div>';
            container.appendChild(row);
        });
    }

    function populateCharSelect(wallets) {
        var select = $('fc-st-mint-char');
        select.innerHTML = '<option value="">Select character or SPC...</option>';

        // Also load all characters (including those without wallets).
        api('/st/characters' + (CHRONICLE_ID ? '?chronicle_id=' + CHRONICLE_ID : '')).then(function (chars) {
            chars.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name + (c.clan ? ' (' + c.clan + ')' : '') + ' [' + c.type + ']';
                select.appendChild(opt);
            });
        });
    }

    function initMint() {
        $('fc-st-mint-btn').addEventListener('click', function () {
            var charId = parseInt($('fc-st-mint-char').value);
            var amount = parseInt($('fc-st-mint-amount').value);
            var note = $('fc-st-mint-note').value.trim();
            var msg = $('fc-st-mint-msg');

            if (!charId) {
                msg.className = 'fc-msg fc-msg-error';
                msg.textContent = 'Select a recipient.';
                return;
            }
            if (!amount || amount < 1) {
                msg.className = 'fc-msg fc-msg-error';
                msg.textContent = 'Enter a valid amount.';
                return;
            }

            $('fc-st-mint-btn').disabled = true;

            api('/st/mint-to-wallet', {
                method: 'POST',
                body: {
                    character_id: charId,
                    amount: amount,
                    note: note,
                    chronicle_id: CHRONICLE_ID,
                },
            }).then(function (res) {
                $('fc-st-mint-btn').disabled = false;
                if (res.error) {
                    msg.className = 'fc-msg fc-msg-error';
                    msg.textContent = res.error;
                    return;
                }
                msg.className = 'fc-msg fc-msg-success';
                msg.textContent = 'Minted ' + amount + ' FangCoin. New balance: ' + res.new_balance;
                $('fc-st-mint-amount').value = '1';
                $('fc-st-mint-note').value = '';
                loadStats();
                loadWallets();
            });
        });
    }

    function initBatchGeneration() {
        function batchGenerate(format) {
            var count = parseInt($('fc-st-batch-count').value);
            var msg = $('fc-st-batch-msg');

            if (!count || count < 1) {
                msg.className = 'fc-msg fc-msg-error';
                msg.textContent = 'Enter a valid number.';
                return;
            }

            $('fc-st-batch-stickers-btn').disabled = true;
            $('fc-st-batch-cards-btn').disabled = true;

            api('/st/mint-codes', {
                method: 'POST',
                body: { count: count, chronicle_id: CHRONICLE_ID },
            }).then(function (res) {
                $('fc-st-batch-stickers-btn').disabled = false;
                $('fc-st-batch-cards-btn').disabled = false;

                if (res.error) {
                    msg.className = 'fc-msg fc-msg-error';
                    msg.textContent = res.error;
                    return;
                }

                msg.className = 'fc-msg fc-msg-success';
                msg.textContent = 'Minted ' + res.codes.length + ' code(s). Opening print view...';
                loadStats();

                var url = PRINT_URL + '&codes=' + encodeURIComponent(res.codes.join(',')) + '&format=' + format;
                window.open(url, '_blank');
            });
        }

        $('fc-st-batch-stickers-btn').addEventListener('click', function () { batchGenerate('sticker'); });
        $('fc-st-batch-cards-btn').addEventListener('click', function () { batchGenerate('card'); });
    }

    function initFilters() {
        var buttons = document.querySelectorAll('.fc-filter-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                renderWallets(btn.dataset.filter);
            });
        });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function loadChronicleSettings() {
        if (!CHRONICLE_ID) {
            var section = $('fc-st-chronicle-settings');
            if (section) section.style.display = 'none';
            return;
        }

        api('/st/chronicle-settings/' + CHRONICLE_ID).then(function (settings) {
            var incomingCb = $('fc-st-allow-incoming');
            var outgoingCb = $('fc-st-allow-outgoing');

            incomingCb.checked = settings.allow_incoming;
            outgoingCb.checked = settings.allow_outgoing;

            updateToggleStyle(incomingCb);
            updateToggleStyle(outgoingCb);

            incomingCb.addEventListener('change', saveChronicleSettings);
            outgoingCb.addEventListener('change', saveChronicleSettings);
        });
    }

    function saveChronicleSettings() {
        var incoming = $('fc-st-allow-incoming').checked;
        var outgoing = $('fc-st-allow-outgoing').checked;
        var msg = $('fc-st-settings-msg');

        updateToggleStyle($('fc-st-allow-incoming'));
        updateToggleStyle($('fc-st-allow-outgoing'));

        api('/st/chronicle-settings/' + CHRONICLE_ID, {
            method: 'POST',
            body: {
                allow_incoming: incoming,
                allow_outgoing: outgoing,
            },
        }).then(function (res) {
            if (res.error) {
                msg.className = 'fc-msg fc-msg-error';
                msg.textContent = res.error;
                return;
            }
            msg.className = 'fc-msg fc-msg-success';
            msg.textContent = 'Settings saved.';
            setTimeout(function () { msg.textContent = ''; }, 3000);
        });
    }

    function updateToggleStyle(checkbox) {
        var label = checkbox.closest('.fc-st-toggle');
        if (!label) return;
        if (checkbox.checked) {
            label.classList.remove('disabled');
        } else {
            label.classList.add('disabled');
        }
    }

    function init() {
        if (!$('fangcoin-st')) return;
        loadChronicleSettings();
        loadStats();
        loadWallets();
        initMint();
        initBatchGeneration();
        initFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
