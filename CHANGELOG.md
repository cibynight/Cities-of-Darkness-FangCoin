# Changelog

## 1.4.3 - 2026-10-06

### Added
- `Requires Plugins` header declaring the dependency on Cities of Darkness Chronicle Tools. WordPress now shows the dependency on the Plugins screen and prevents activation if Chronicle Tools is not installed.

## 1.4.2 - 2026-10-04

### Changed
- ST Dashboard wallet list now sorted alphabetically by character name instead of by balance descending.

## 1.4.1 - 2026-10-04

### Fixed
- **Theme overriding button colors and other CSS elements.** Added comprehensive `#fangcoin-app` ID-scoped `!important` overrides for the submit button (`.fc-submit`), copy button, status badges, message colors, text labels, transaction rows, format labels, scanner controls, and all interactive elements that were missing from the theme override layer. Also added hover/disabled state overrides.
- **ST Dashboard showing wallets, stats, and characters from all chronicles.** Registered `chronicle_id` as a proper REST route argument on the `/st/stats`, `/st/wallets`, and `/st/characters` endpoints so WordPress correctly reads the query parameter. Previously, the param was passed by the JS but not registered in the route definition, which could cause it to be ignored.
- **Storytellers seeing SPCs from all chronicles in the player UI.** The `/wallets` endpoint now filters SPCs to only those belonging to the same chronicle(s) as the Storyteller's own PCs. Site administrators with no PCs still see all SPCs as a fallback. Previously, all published SPCs across every chronicle were shown.

## 1.4.0 - 2026-10-04

### Added
- **Chronicle economy scoping.** Storytellers can now control whether FangCoin can enter or leave their chronicle via two per-chronicle toggles on the ST Dashboard FangCoin tab:
  - **Allow incoming FangCoin** — when off, only FangCoin minted within this chronicle can be deposited. Cross-chronicle digital transfers and external QR code deposits are blocked.
  - **Allow outgoing FangCoin** — when off, characters in this chronicle cannot send FangCoin to other chronicles or withdraw to QR codes.
- Both toggles default to on (open economy). Turning both off creates a fully sealed economy ideal for one-shot games and sponsored events like Darkness Emergent.
- QR codes now carry a `chronicle_id` column in the database, stamped at creation time from the source character's chronicle (player withdrawals) or the ST's active chronicle (batch minting). This ensures that codes minted at a one-shot event stay within that event even after physical circulation.
- New REST endpoints: `GET /st/chronicle-settings/{chronicle_id}` and `POST /st/chronicle-settings/{chronicle_id}` for reading and saving chronicle economy settings.
- ST mint-to-wallet now receives the active `chronicle_id` and enforces incoming restrictions — minting to a character outside a sealed chronicle is blocked.
- ST batch mint now stamps each code with the active chronicle's ID.

### Changed
- Cross-chronicle digital transfers now check both the sender's "allow outgoing" and the recipient's "allow incoming" settings. Same-chronicle transfers always succeed regardless of settings.
- QR code deposits check the depositing character's chronicle settings. If incoming is blocked, only codes whose `chronicle_id` matches the character's chronicle are accepted.

## 1.3.7 - 2026-08-30

### Fixed
- Storytellers could not access SPC wallets from the player-facing interface. The `/wallets` endpoint only queried `lotn_sheet` posts owned by the current user. Now, when the current user has the Storyteller role, all published `lotn_spc` posts are also included in the character selector.
- Storytellers can now send from, withdraw from, and manage SPC wallets through the same player-facing UI. The `user_owns_character` check now passes for Storytellers on any character.
- Wallet list response now includes a `type` field (`PC` or `SPC`) for display purposes.

## 1.3.6 - 2026-08-30

### Changed
- Removed inline `style` attribute from the FangCoin ST Dashboard tab link. Chronicle Tools 3.6.458 moved tab styling into CSS classes (`.lotn-st-tabs .lotn-fe-btn` with `border-radius: 4px 4px 0 0` and `flex-wrap` support), so the inline border-radius and border-bottom overrides are no longer needed.

## 1.3.5 - 2026-08-30

### Fixed
- Theme background was visible below the FangCoin app, creating a seam at the bottom. Added `background: #0d0d0d !important` and `min-height: 80vh` to `.fangcoin-app` so the dark background fills the page container. `.fc-wrap` also gets `min-height: 80vh` and `display: flex; flex-direction: column` so `.fc-body` (with `flex: 1`) stretches to fill all remaining vertical space. On desktop, the flex direction switches to `row` for the sidebar layout.

## 1.3.4 - 2026-08-30

### Fixed
- Added a comprehensive theme override layer using `#fangcoin-app` ID-scoped selectors with `!important` on all dark surfaces: wrap, sidebar, body, cards, inputs, selects, buttons, flow cells, scanner, info box, and format options. ID selectors have higher specificity than any class-based theme rules, and combined with `!important` they should beat even aggressive theme stylesheets.

## 1.3.3 - 2026-08-30

### Fixed
- Wallet address card was showing white text on a white background. The WordPress theme was overriding `.fc-card` background color. Added `!important` to the address card background and text colors, plus an inline `style` attribute on the card element as a fallback against high-specificity theme selectors.
- Bumped address help text from `#555` to `#888` for better readability.

## 1.3.2 - 2026-08-30

### Fixed
- QR scanner was not recognizing scanned wallet addresses or claim codes. Rewrote both `extractClaimCode` and `extractWalletAddress` to use `new URL()` with `searchParams.get()` as the primary parser (handles any URL format the site generates), with regex fallback for malformed URLs and raw code/address strings.
- Fixed variable shadowing in `onScanSuccess` where `var decoded = decoded.trim()` re-declared the parameter name, which could cause issues in some JavaScript engines. Renamed parameter to `decodedText`.
- Added "Unrecognized QR code" fallback message when neither a claim code nor a wallet address can be extracted from a scan, so failures are visible instead of silent.

## 1.3.1 - 2026-08-30

### Fixed
- Wallet address text on the Account view was too faint. Increased to `#fff`, `font-weight: 600`, and `font-size: 18px` for better visibility on the dark background.
- QR scanner was not processing scanned codes. The `html5-qrcode` success callback fires repeatedly and the async `scanner.stop()` was racing with the result processing. Added a `scanProcessing` guard to prevent double-firing, saved `scanTarget` before stopping, and separated the stop/clear lifecycle so the decoded result is processed reliably.
- Added a short `setTimeout` before triggering the verify button click after scanning, giving the DOM time to update the input value.

### Added
- Copy button next to the wallet address. Copies `fc1xxxxxxxx` to the clipboard with a "Copied!" confirmation that resets after 2 seconds.

## 1.3.0 - 2026-08-30

### Added
- **Wallet addresses.** Each wallet now has a unique address in the format `fc1xxxxxxxx`. Players send FC to addresses, not character names, keeping transfers anonymous.
- **Wallet address QR code** on the Account view. Players show this QR code on their phone for others to scan to send them FangCoin.
- **QR camera scanning** using the `html5-qrcode` library. Scan buttons on the Send view (scan a wallet address), Verify (scan a coin), and Deposit (scan a coin). The scanner auto-detects whether the QR contains a wallet address or a claim code and routes accordingly.
- **DB upgrade mechanism.** The plugin now stores a `fangcoin_db_version` option and runs `dbDelta` + address backfill on version changes, so existing installs get the new `address` column automatically.
- **Send redirect template.** When someone scans a wallet address QR with an external QR app, they see a standalone page confirming the address is valid and directing them to open FangCoin.
- **Separate ST characters endpoint** (`/st/characters`) that queries approved PCs and published SPCs separately, since SPCs don't have the `_lotn_validated` / `_lotn_chr_sub_status` approval meta keys.

### Changed
- **Send flow** now uses wallet address input + scan instead of a character dropdown. Players type `fc1xxxxxxxx` or scan a QR code. No character names are revealed during transfers.
- **Transaction history** shows the other party's wallet address instead of their character name. Transfers show "Sent to fc1xxxxxxxx" / "Received from fc1xxxxxxxx".
- **Transfer REST endpoint** now accepts `to_address` instead of `to_character_id`.
- **ST mint endpoint** accepts either `character_id` or `spc_id` parameter.
- **ST wallet list** now displays each wallet's address alongside the character name and balance.

### Fixed
- SPCs were not appearing in the ST character/wallet lists because the query required PC approval meta keys (`_lotn_validated`, `_lotn_chr_sub_status`) which SPCs don't have.

## 1.2.0 - 2026-08-30

### Changed
- Desktop layout switched to sidebar (Option A). Balance, character selector, nav buttons, and income/spending summary are pinned in a 220px left sidebar. The transaction feed and action views fill the right content area. On mobile (below 768px), the layout collapses to the same stacked view as before.
- Nav buttons stack vertically in the sidebar on desktop, remain horizontal on mobile.
- Income/spending summary is now always visible (bottom of sidebar on desktop, below nav buttons on mobile) rather than only on the Account view.
- FC coin icon hidden on desktop (sidebar is compact enough without it), shown on mobile.

### Fixed
- Chip sticker claim code font changed from regular weight `#333` to bold `700` weight `#000` for better legibility when printed on DYMO 30332 labels.

## 1.1.0 - 2026-08-30

### Changed
- Complete player UI redesign with dark crypto-bank theme. The interface now resembles a banking app with a dark background, centered balance display, and contextual navigation.
- Navigation model: three action buttons (Send, Withdraw, Deposit, Account) shown below the balance card, with the current view's button hidden. No redundant tab for where you already are.
- Renamed "Transfer" to "Send", "QR code generation" to "Withdraw to cash", and "Verify/Redeem" to "Deposit". Transaction history uses banking terms (Withdrawal, Deposit) instead of QR code jargon.
- Account view shows 30-day income vs spending summary, computed from the character's transaction history.
- QR codes are no longer listed as "active codes from your wallet." Once withdrawn, coins are anonymous bearer cash with no tracking back to the generating character.
- Responsive layout: mobile-first single-column stacking, desktop uses two-column layout for the Deposit view's verify/redeem cards and inline note+amount fields on the Send view.
- ST Dashboard CSS classes namespaced to `fc-st-` to prevent dark theme bleed from the player UI.

### Removed
- Active codes list from the player view. The `source_wallet_id` column remains in the database for audit purposes but is no longer surfaced to players.

## 1.0.4 - 2026-08-29

### Fixed
- Rewrote `FangCoin_ST` to match the exact Chronicle Tools ST Dashboard hook signatures. The `lotn_st_dashboard_tabs` filter passes 4 args (`$html`, `$active_tab`, `$tab_base`, `$chronicle_id`) and the `lotn_st_dashboard_tab` action passes 2 (`$active_tab`, `$chronicle_id`). Previous versions only accepted 1 arg each, causing the tab to not render or navigate.
- Tab link now uses the correct query parameter `lotn_tab` (not `tab`).
- Tab link now uses Chronicle Tools' button classes (`lotn-fe-btn lotn-fe-btn-sm`, with `lotn-fe-btn-primary` for active state) and matching inline border-radius styling.
- Tab content renderer now receives the `$chronicle_id` parameter and passes it to the frontend JS for future chronicle-scoped queries.

## 1.0.3 - 2026-08-29

### Fixed
- FangCoin tab link on the Storyteller Dashboard was not navigable. The ST Dashboard uses server-side tab switching via URL query parameters, not client-side JavaScript. Changed `href="#"` to a proper URL using `add_query_arg('tab', 'fangcoin')` so clicking the tab reloads the page with the correct active tab.

## 1.0.2 - 2026-08-29

### Fixed
- FangCoin tab not appearing on the Storyteller Dashboard (showed "Array" instead). The `lotn_st_dashboard_tabs` filter passes a string of HTML, not an array. Changed `add_tab` to concatenate the `<a>` tag onto the string.

## 1.0.1 - 2026-08-29

### Fixed
- Critical error on Storyteller Dashboard caused by strict PHP 8 type hints on `lotn_st_dashboard_tabs` and `lotn_st_dashboard_tab` hook callbacks. Removed strict parameter types and added defensive checks so the callbacks handle any input Chronicle Tools passes.
- `get_or_create_wallet` return type changed from `object` to `?object` to prevent TypeError if the database insert fails.

## 1.0.0 - 2026-08-29

Initial release.

### Added
- Digital wallets for approved PCs and SPCs (one wallet per character).
- Direct wallet-to-wallet transfers with optional notes.
- QR code generation from player wallets (1 code = 1 FangCoin, deducted from balance).
- QR code verification without redemption, allowing coins to stay in physical circulation.
- QR code redemption into a character's wallet.
- Standalone verification page for external QR scanner apps (no login required).
- Transaction history with full ledger of incoming/outgoing activity.
- `[fangcoin]` shortcode for the player-facing interface (wallet, transfer, QR codes, verify/redeem tabs).
- Storyteller Dashboard tab (hooks into Chronicle Tools `lotn_st_dashboard_tabs`).
- ST economy overview: total circulation, wallet balances, active QR code count.
- ST mint-to-wallet: introduce new FangCoin directly into any PC or SPC wallet with a logged reason.
- ST batch QR generation: mint codes from nothing in bulk.
- Print template for DYMO 30332 chip stickers (1" x 1" square labels for poker chips).
- Print template for paper FangCoin cards (2.5" x 3.5" playing-card size with FC logo, denomination, QR code, and claim code).
- Claim codes use the format `FC-XXXX-XXXX` with an unambiguous character set (no 0, O, 1, I, or L).
- Auto-formatting input for claim code entry.
- REST API with player, public, and ST endpoints under `fangcoin/v1`.
- Database tables created on activation: wallets, transactions, codes.
- Dependency check for Cities of Darkness Chronicle Tools on activation.
