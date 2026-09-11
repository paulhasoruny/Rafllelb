RAFFLELB PREMIUM MOBILE MENU
Version 1.1.8

1.1.8
- Premium typography pass using the RaffleLB Design System Manrope variable.
- Main drawer labels use 16px/700 with clean tracking; referral supporting text is now at least 11px and higher contrast.
- Drawer geometry, icons, link order/destinations, logo, animation, overlay, scrolling, body lock, account/cart behavior, and JavaScript are unchanged.

1.1.7

- Sends Raffles to the site-aware Shop raffle mode URL: `/shop/?rl_view=raffle#rl-shop-controls`.
- Restores My Raffles to WooCommerce's existing `rafflelb-entries` account endpoint.

INSTALLATION

1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Upload rafflelb-premium-mobile-menu.zip.
3. Click Install Now, then Activate.
4. Clear Woodmart, Elementor and website/CDN caches.
5. Test the existing hamburger icon on a phone or at a browser width below 1025px.

WHAT IT DOES

- Uses the existing Woodmart hamburger button.
- Detects and replaces Woodmart's native .mobile-nav / .wd-nav-mobile drawer, including touch-driven opening.
- Mounts the premium RaffleLB design directly inside Woodmart's existing drawer so the legacy menu content cannot remain visible.
- Renders the complete premium menu through WordPress before the page reaches the browser, so it does not depend on JavaScript or WP Rocket timing.
- Prevents common optimization tools from delaying the menu replacement script until after the first tap.
- Replaces only the mobile drawer presentation.
- Does not change account, raffle, order, referral, points, checkout or payment logic.
- Uses the bundled official RaffleLB WebP logo; it does not load a theme, WordPress, or remote logo image.
- Sends Raffles to the Shop's existing Raffle Only view.
- Uses the verified My Raffles endpoint: /my-account/rafflelb-entries/
- Uses the verified Refer & Earn endpoint: /my-account/refer-and-earn/
- Shows Login / Register to guests and My Account to logged-in users.
- Adds keyboard focus handling, Escape-to-close, backdrop-to-close and safe mobile scrolling.
- Does not affect desktop navigation.

SOCIAL LINKS

Instagram, Facebook and TikTok icons are always shown. The menu first tries to reuse matching links already present in the website footer.

You can set or override them under:
Appearance > RaffleLB Mobile Menu

ROLLBACK

Deactivate this plugin. The original Woodmart mobile menu will immediately be used again.
