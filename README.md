# Cities of Darkness - FangCoin

A fictional cryptocurrency system for the Cities of Darkness LARP chronicle. Provides digital wallets, peer-to-peer transfers, and QR-coded physical coins backed by real poker chips.

**Version:** 1.4.3
**Requires:** Cities of Darkness Chronicle Tools 3.6.440+
**Requires PHP:** 8.0+
**Requires WordPress:** 6.0+

## Installation

1. Ensure the **Cities of Darkness Chronicle Tools** plugin is installed and active.
2. Upload the `cities-of-darkness-fangcoin` folder to `/wp-content/plugins/`.
3. Activate the plugin through the WordPress Plugins screen.
4. Create a page with the `[fangcoin]` shortcode for the player-facing interface.

The FangCoin tab appears automatically in the Storyteller Dashboard.

## Features

### Player Features
- **Digital wallets** for each approved character (one wallet per character, not per player).
- **Direct transfers** to any PC or SPC with an optional note.
- **QR code generation** to convert digital FangCoin into physical bearer tokens. Each QR code is worth 1 FangCoin and is deducted from the player's wallet.
- **Verify codes** without redeeming them, so coins can stay in physical circulation.
- **Redeem codes** to deposit a physical coin back into a digital wallet.
- **Transaction history** showing all incoming and outgoing activity.

### Storyteller Features (ST Dashboard tab)
- **Economy overview** with total circulation, wallet balances, and active QR codes.
- **Mint to wallet** to introduce new FangCoin directly into any PC or SPC wallet.
- **Batch QR generation** to mint physical coins from nothing, with two print formats:
  - **Chip stickers** (1" x 1", sized for DYMO 30332 labels applied to poker chips)
  - **Paper cards** (2.5" x 3.5" playing-card size, for handouts)
- **Wallet balance list** with PC/SPC filtering.
- **Chronicle economy controls** with per-chronicle toggles to block FangCoin entering or leaving. Useful for one-shot games and sponsored events that need a sealed economy.

### QR Code System
- Codes use the format `FC-XXXX-XXXX` with an unambiguous character set (no 0/O/1/I/L).
- QR codes encode a URL that opens a standalone verification page when scanned with any phone camera app.
- **No expiry, no voiding.** Once FangCoin leaves a wallet as a QR code, it is bearer currency. If the physical coin is lost, the currency is lost.
- Verification is public (no login required). Redemption requires a logged-in player with an approved character.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[fangcoin]` | Full player interface with wallet, transfer, QR codes, and verify/redeem tabs. |

## REST API Endpoints

All endpoints are under the `fangcoin/v1` namespace.

### Player Endpoints (authenticated)
- `GET /wallets` - List wallets for the current user's characters.
- `GET /wallet/{character_id}` - Get a specific character's wallet.
- `POST /transfer` - Direct wallet-to-wallet transfer.
- `GET /transactions/{character_id}` - Transaction history.
- `POST /codes/generate` - Generate QR codes from a wallet.
- `GET /codes/active/{character_id}` - List active codes from a wallet.
- `POST /codes/redeem` - Redeem a code into a wallet.
- `GET /characters` - List all approved characters/SPCs for the transfer picker.

### Public Endpoints
- `GET /codes/verify/{code}` - Check if a code is valid or redeemed.

### Storyteller Endpoints (ST role required)
- `GET /st/stats` - Economy-wide statistics.
- `GET /st/wallets` - All wallet balances.
- `POST /st/mint-to-wallet` - Mint FangCoin directly to a wallet.
- `POST /st/mint-codes` - Batch mint QR codes.

## Database Tables

- `{prefix}fangcoin_wallets` - One row per character, tracks balance.
- `{prefix}fangcoin_transactions` - Immutable ledger of all activity.
- `{prefix}fangcoin_codes` - QR claim codes with active/redeemed status.

## Dependencies

- **Cities of Darkness Chronicle Tools** (required) - uses character/SPC post types, approval status meta keys, chronicle scoping, and ST Dashboard tab hooks.
- **qrcode.js** (loaded from cdnjs.cloudflare.com) - client-side QR code rendering for print views.
