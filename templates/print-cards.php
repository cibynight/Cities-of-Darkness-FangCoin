<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FangCoin Paper Cards</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            color: #000;
        }
        .controls {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .controls button {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 4px;
        }
        .controls p {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        .card-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 0.25in;
            justify-content: flex-start;
        }
        .fc-paper-card {
            width: 2.5in;
            height: 3.5in;
            border: 1px solid #ccc;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 10px 8px;
            position: relative;
            page-break-inside: avoid;
        }
        .fc-paper-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #000;
            border-radius: 6px 6px 0 0;
        }
        .card-inner-border {
            position: absolute;
            inset: 6px;
            border: 0.5px solid #ddd;
            border-radius: 3px;
            pointer-events: none;
        }
        .card-logo {
            font-family: "Arial Black", Arial, sans-serif;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -3px;
            color: #000;
            margin-top: 4px;
        }
        .card-brand {
            font-size: 7px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #666;
            margin-top: 1px;
        }
        .card-denomination {
            font-family: Georgia, serif;
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 6px 0 2px;
        }
        .card-qr {
            margin: 4px 0;
        }
        .card-claim-code {
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #555;
            letter-spacing: 2px;
            margin-top: 2px;
        }
        .card-footer {
            margin-top: auto;
            padding-top: 6px;
            border-top: 0.5px solid #ddd;
            width: 85%;
            text-align: center;
        }
        .card-footer-text {
            font-size: 5.5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #999;
        }

        @media print {
            .controls { display: none; }
            body { background: #fff; }
            .card-grid {
                gap: 0;
                padding: 0.125in;
            }
            .fc-paper-card {
                border-color: #999;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="controls">
        <button onclick="window.print()">Print cards</button>
        <p><?php echo count( $valid_codes ); ?> card(s) ready. Size: 2.5" x 3.5" (playing card). Prints ~8 per US Letter sheet.</p>
    </div>

    <div class="card-grid" id="card-grid">
        <?php foreach ( $valid_codes as $vc ) : ?>
            <div class="fc-paper-card">
                <div class="card-inner-border"></div>
                <div class="card-logo">FC</div>
                <div class="card-brand">FangCoin</div>
                <div class="card-denomination">1</div>
                <div class="card-qr" id="qr-<?php echo esc_attr( $vc['code'] ); ?>"></div>
                <div class="card-claim-code"><?php echo esc_html( $vc['code'] ); ?></div>
                <div class="card-footer">
                    <div class="card-footer-text">Scan to verify or redeem</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var codes = <?php echo wp_json_encode( $valid_codes ); ?>;
            codes.forEach(function (c) {
                var container = document.getElementById('qr-' + c.code);
                if (container) {
                    new QRCode(container, {
                        text: c.qr_data,
                        width: 110,
                        height: 110,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                }
            });
        });
    </script>
</body>
</html>
