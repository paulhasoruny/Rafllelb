# RaffleLB architecture

RaffleLB is a set of modular WordPress plugins. One plugin owns one main
responsibility. Working rules for making changes are in
[`../CLAUDE.md`](../CLAUDE.md).

## Repository layout

```
Rafllelb/
├── CLAUDE.md
├── docs/
│   └── RAFFLELB-ARCHITECTURE.md
└── plugins/
    ├── rafflelb-core/
    ├── rafflelb-draw-engine/
    ├── rafflelb-raffle-manager/
    ├── rafflelb-account/
    ├── rafflelb-homepage/
    ├── rafflelb-shop/
    ├── rafflelb-custom-checkout/
    ├── rafflelb-referral-points/
    ├── rafflelb-notifications/
    ├── rafflelb-omt-pay/
    ├── rafflelb-whish-manual-gateway/
    ├── rafflelb-premium-mobile-menu/
    ├── rafflelb-cart-manager/
    └── rafflelb-admin/
```

Directories whose plugin source has not been added to this repo yet contain a
placeholder `README.md`.

---

## RaffleLB Core

Shared contracts and common infrastructure. Must stay lightweight.

Owns: shared constants; common compatibility contracts; shared account/access
helpers; common RaffleLB infrastructure.

Known baseline: **0.1.1**

---

## RaffleLB Draw Engine

The authoritative owner of raffle transaction logic.

Owns: raffle entries; reservation/hold logic; capacity; entry allocation;
cancellation and refund voiding; closing raffles; the eligible entry pool;
winner selection; draw results and history; raffle progress calculations.

Stable data and contracts — do **not** rename, recreate, migrate or reinterpret
without explicit approval:

| Contract | Kind |
| --- | --- |
| `rafflelb_entries` | table |
| `rafflelb_draw_results` | table |
| `rafflelb_holds` | table |
| `rafflelb_draw_status` | meta |
| `rafflelb_winner_entry_id` | meta |
| `rafflelb_purchase_mode` | meta |

Internal schema compatibility stays at **0.34.18** even when the WordPress
plugin header version increases.

Known baseline: **0.34.18.9** · Shop bridge: `SHOP_BRIDGE_VERSION = 1`

---

## RaffleLB Raffle Manager

Admin and management interface for raffles. Uses Draw Engine data and APIs.
Must not independently implement entry allocation, holds or winner logic.

---

## RaffleLB Account

Owns the RaffleLB WooCommerce My Account frontend and account-specific
presentation: Dashboard, Orders presentation, My Raffles, Addresses, Account
Details, account UI.

Do not put Shop frontend logic here.

---

## RaffleLB Homepage

Owns homepage-specific design and functionality. Not for Shop or product-page
functionality.

---

## RaffleLB Shop

Owns: Shop/archive frontend; Store Only and raffle shopping presentation;
single-product page design; Buy Direct presentation; product-page frontend
integration; Shop-specific guest/login gating UI.

Consumes raffle information from Draw Engine. **Must not own raffle transaction
logic.**

Approved baseline: **0.1.43**

The 0.1.42/0.1.43 single-product first-paint solution is important. Do not
casually modify, unless the task explicitly concerns these systems:

- `product_layout_bootstrap()`
- early `wp_head` product stylesheet loading
- the CSS readiness / computed-style probe
- WP Rocket exclusions
- first-paint protection
- bootstrap timing

Single-product desktop and mobile designs are currently approved. Avoid
redesigning approved sections while making unrelated changes.

---

## RaffleLB Custom Checkout

Owns checkout-specific RaffleLB behaviour. Uses `_rafflelb_purchase_mode` to
distinguish raffle-entry purchases from `buy_now`.

Do not implement checkout rules inside Shop or Draw Engine unnecessarily.

---

## RaffleLB Referral & Points

Owns the referral system, the points system and Refer & Earn.

If the floating `REFER & EARN` control needs modification, inspect this plugin
first. Do not patch its behaviour permanently inside Shop.

---

## RaffleLB Notifications

Owns raffle and customer notification behaviour. Listens to events including
`rafflelb_draw_completed` and consumes Draw Engine data.

Not responsible for selecting winners.

---

## RaffleLB OMT Pay

Owns OMT payment gateway functionality.

---

## RaffleLB Whish Manual Gateway

Owns Whish / manual payment gateway functionality.

---

## RaffleLB Premium Mobile Menu

Owns custom mobile navigation and menu behaviour. Do not redesign the global
mobile header or menu inside Shop.

---

## RaffleLB Cart Manager

Owns RaffleLB cart/reservation administration and cart visibility. Consumes
Draw Engine hold data; must not create a second hold system.

The raffle reservation window is approximately 15 minutes.

---

## RaffleLB Admin

Owns the RaffleLB WordPress admin visual layer and admin UX. No transactional
raffle logic here.

Known baseline: **1.0.7**
