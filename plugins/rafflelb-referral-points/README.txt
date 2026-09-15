RaffleLB Referral & Points v1.2.18

- Added a reusable monetary-refund credit API that uses the configured checkout valuation (`spend_points_per_dollar`).
- Refund credits are transactionally idempotent through a private hashed marker, per-user advisory lock, and immutable Points ledger entry.
- Refund credits do not invoke paid-order referral earning and therefore cannot create referral rewards.

RaffleLB Referral & Points v1.2.4

- Mobile signed-in header: increased the website logo size while preserving Account + Raffle Points + Cart on the same row.
- Standard mobile logo cap increased from 176px to 190px.
- Very small mobile logo cap increased from 164px to 176px.
- No referral, points, checkout, account, or payment logic changed.

RaffleLB Referral & Points v1.2.2

Fixed My Account tab ordering dropping the Notifications tab to the end:
- account_menu() runs after RaffleLB Draw Engine's own account-menu filter and fully rebuilds the tab list from its own fixed $order array. Any endpoint not in that list (like RaffleLB Notifications' "Notifications" tab) fell through to the "leftover keys" loop and was appended after Logout - which is why it rendered alone on its own row.
- Added 'rafflelb-notifications' to that order list, positioned right after Dashboard and before My Raffles: Dashboard, Notifications, My Raffles, Orders, Refer & Earn, Addresses, Account Details, Logout.
- No change if RaffleLB Notifications isn't active - the order list simply skips endpoints that aren't present.

RaffleLB Referral & Points v1.2.1

Typography cleanup:
- Header points badge and account premium styling now lead with Inter to match the rest of the site's premium typography instead of falling back to a plain system font.
- Normalized invalid font-weight value (950) to 900.
- No referral earning, point balances, reversals, or gateway logic changed.

RaffleLB Referral & Points v1.1.6

- Removed all checkout white-background / black-text overrides from the referral plugin.
- Removed inline white styling from the Raffle Points payment box.
- Raffle Points checkout output is now semantic markup only.
- RaffleLB Dark Checkout is the single source of truth for checkout presentation.
- Referral earning, point balances, reversals, gateway logic, and floating Refer & Earn launcher are unchanged.
Version 1.2.5
- Rebuilt the Refer & Earn account content as a premium responsive dashboard while preserving the existing top account menu.
- Added real balance, joined/qualified metrics, wallet value, referral activity, sharing tools, three-step explanation, and a clear 10% reward example.
- Clarified that every $10 qualifying purchase by a referred friend earns 10 Raffle Points, equal to $1 at checkout under the current rates.
- Referral tracking, reward calculation, points balances, reversals, checkout payment, and account menu logic are unchanged.
Version 1.2.6
- Forced Inter on every element in the Refer & Earn content at final stylesheet priority.
- Increased supporting typography sizes and strengthened the visual hierarchy.
- Forced black text on lime buttons and lime-filled controls.
- Simplified direct sharing to WhatsApp only and added a proper WhatsApp logo.
Version 1.2.7
- Replaced the custom WhatsApp drawing with the clean standard WhatsApp glyph.
- Replaced the letter R in the Raffle Points statistic with the bundled RaffleLB website ticket logo.
Version 1.2.8
- Replaced the Points Wallet symbol with the bundled RaffleLB website ticket logo.
- Replaced the Personal Link arrow with a clean share icon.
- Replaced the Copy Link text symbol with a clean duplicate-document icon and preserved it after copying.
Version 1.2.9
- Replaced the basic Friends Joined, Qualified Referrals, and Qualification Rate symbols with professional SVG icons.
- Added consistent premium lime icon containers for the referral overview statistics.
Version 1.2.10
- Rebuilt the mobile Latest Referrals empty state with balanced spacing, centered content, and a compact total badge.
- Replaced the basic plus symbol beside No referrals yet with a professional user-plus SVG icon.
Version 1.2.11
- Locked the No referrals yet icon container and SVG to a true square aspect ratio on desktop and mobile.
- Added min/max dimensions and fixed flex sizing so WoodMart cannot stretch the icon.
Version 1.2.12
- Replaced the plain More Friends / More Chances / More Rewards slogan with a premium Referral Journey component.
- Added structured Share, Connect, and Earn steps with numbered lime markers, connector lines, and responsive mobile styling.
Version 1.2.13
- Enlarged the Referral Journey panel, title, numbered markers, step labels, spacing, and connector lines.
- Forced Inter directly throughout the component to prevent WoodMart's condensed typography from returning.
Version 1.2.14
- Softened the mobile Copy Link button from neon lime to a darker premium lime gradient.
- Removed the mobile button glow while preserving clear black text and icon contrast.
