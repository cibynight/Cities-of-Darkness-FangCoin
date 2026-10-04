<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FangCoin - Verify</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .verify-card {
            background: #fff;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            max-width: 360px;
            width: 100%;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .verify-logo {
            font-family: "Arial Black", Arial, sans-serif;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -3px;
            color: #1a1a1a;
        }
        .verify-brand {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 20px;
        }
        .verify-code {
            font-family: "Courier New", monospace;
            font-size: 18px;
            color: #333;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }
        .verify-status {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .fc-verify-valid {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .fc-verify-redeemed {
            background: #fbe9e7;
            color: #c62828;
        }
        .fc-verify-invalid {
            background: #f5f5f5;
            color: #666;
        }
        .verify-help {
            font-size: 13px;
            color: #999;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="verify-logo">FC</div>
        <div class="verify-brand">FangCoin</div>
        <div class="verify-code"><?php echo esc_html( $code ); ?></div>
        <div class="verify-status <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $status ); ?></div>
        <?php if ( $status === 'VALID' ) : ?>
            <p class="verify-help">This coin has not been redeemed. It is worth 1 FangCoin.</p>
        <?php elseif ( $status === 'ALREADY REDEEMED' ) : ?>
            <p class="verify-help">This coin has already been redeemed to a wallet.</p>
        <?php else : ?>
            <p class="verify-help">This code was not found in the system.</p>
        <?php endif; ?>
    </div>
</body>
</html>
