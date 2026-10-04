<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FangCoin - Send</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0d0d0d;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: #111;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            max-width: 360px;
            width: 100%;
        }
        .logo {
            font-family: "Arial Black", Arial, sans-serif;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -3px;
            color: #e8e8e8;
        }
        .brand {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 20px;
        }
        .addr {
            font-family: "SF Mono", "Consolas", monospace;
            font-size: 18px;
            color: #e8e8e8;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }
        .status {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .valid { background: #1a2e1a; color: #4ade80; }
        .invalid { background: #2e1a1a; color: #f87171; }
        .help {
            font-size: 13px;
            color: #555;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">FC</div>
        <div class="brand">FangCoin</div>
        <div class="addr"><?php echo esc_html( $address ); ?></div>
        <?php if ( $valid ) : ?>
            <div class="status valid">Valid wallet address</div>
            <p class="help">Open FangCoin in your browser to send FC to this address.</p>
        <?php else : ?>
            <div class="status invalid">Address not found</div>
            <p class="help">This wallet address was not found in the system.</p>
        <?php endif; ?>
    </div>
</body>
</html>
