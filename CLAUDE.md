# RaffleLB — working rules

RaffleLB is **not** one combined WordPress plugin. The site is deliberately
split into modular plugins, one main responsibility each.

Full ownership map: [`docs/RAFFLELB-ARCHITECTURE.md`](docs/RAFFLELB-ARCHITECTURE.md).

## Before changing any code

1. Identify the **owning plugin** for the requested functionality.
2. Inspect the existing implementation there.
3. Inspect its dependencies and callers.
4. Confirm the change belongs in that plugin.
5. Make the smallest scoped change possible.
6. Do not duplicate functionality another plugin already owns.
7. Do not modify unrelated plugins.
8. Preserve existing public methods, hooks and contracts unless the task requires otherwise.
9. Bump the modified plugin's version.
10. Package using the canonical folder name.

## Core architecture rule

**One plugin = one main responsibility.**

- Do not move functionality between plugins unless explicitly requested.
- Do not duplicate functionality owned by another plugin.
- Do not patch another plugin's responsibility inside the plugin being edited.
- If a change genuinely needs more than one plugin, **stop and explain**: which
  plugins are involved, why, and what must change in each. Never make silent
  cross-plugin architectural changes.

## Dependency direction

```
Core
  ↓
Draw Engine / transactional services
  ↓
Feature plugins
  ↓
Frontend presentation plugins
```

Frontend plugins consume APIs and contracts from transactional plugins rather
than recreating their logic: Shop → Draw Engine, Raffle Manager → Draw Engine,
Notifications → Draw Engine, Cart Manager → Draw Engine, Custom Checkout →
WooCommerce + RaffleLB purchase-mode contracts.

## Never change casually

Only when the task explicitly requires it:

- database table names and schemas
- existing meta keys
- WooCommerce product/order metadata
- purchase mode values
- hold logic, entry allocation, winner selection, reservation timing
- checkout logic

Preserve backward compatibility between RaffleLB plugins at all times.

## Frontend work

- Inspect the DOM, the CSS cascade and specificity, JavaScript-generated markup,
  and responsive behaviour before writing CSS.
- Preserve functional forms and hooks.
- Avoid blindly adding override layers.
- **Do not claim a visual issue is fixed without verifying the rendered result.**
- Approved layouts stay frozen unless the task specifically changes them.

## Packaging

A release zip must contain the canonical plugin folder at its root.

```
rafflelb-shop-0.1.44.zip
└── rafflelb-shop/          ✅

NOT  rafflelb-shop-0.1.44/  ❌
NOT  files at zip root      ❌
```

## Report at the end of every task

- plugin modified
- previous version → new version
- files changed
- functionality changed
- dependencies affected
- database changes, if any
- whether other RaffleLB plugins need updating

If none do, say exactly: `No cross-plugin changes required.`

## Repository note

All fourteen plugins are present under `plugins/`, so both ends of a contract can
be inspected before changing either. Shared identifiers live in
`plugins/rafflelb-core/core.php` (`RaffleLB\\Core\\Contracts`) — read the constant
rather than hardcoding a table name or meta key.
