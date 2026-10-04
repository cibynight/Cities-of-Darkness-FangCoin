<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FangCoin Chip Stickers</title>
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
        .sticker-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 20px;
            justify-content: flex-start;
        }
        .sticker {
            width: 1in;
            height: 1in;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .sticker .qr-container {
            width: 0.8in;
            height: 0.8in;
        }
        .sticker .claim-code {
            font-family: "Courier New", monospace;
            font-size: 5.5pt;
            font-weight: 700;
            color: #000;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }

        @media print {
            .controls { display: none; }
            body { background: #fff; }
            .sticker-grid {
                gap: 0;
                padding: 0;
            }
            .sticker {
                page-break-inside: avoid;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="controls">
        <button onclick="window.print()">Print stickers</button>
        <p><?php echo count( $valid_codes ); ?> sticker(s) ready. Size: 1" x 1" (DYMO 30332)</p>
    </div>

    <div class="sticker-grid" id="sticker-grid">
        <?php foreach ( $valid_codes as $vc ) : ?>
            <div class="sticker">
                <div class="qr-container" id="qr-<?php echo esc_attr( $vc['code'] ); ?>"></div>
                <div class="claim-code"><?php echo esc_html( $vc['code'] ); ?></div>
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
                        width: 72,
                        height: 72,
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
