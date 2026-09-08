RaffleLB Notifications v1.0.0

PURPOSE
A small badge on the account icon, a "Notifications" tab in My Account, and
an admin screen to control/send notifications. Built as a separate plugin
so it can be deactivated independently; it listens to RaffleLB Draw
Engine's `rafflelb_draw_completed` action hook (added in Draw Engine
v0.33.64) instead of duplicating any draw logic.

REQUIRES
- WooCommerce
- RaffleLB Draw Engine v0.33.64+ (fires `rafflelb_draw_completed` when a
  winner is permanently recorded - both Secure Random Draw and Record
  Chosen Winner)

WHAT IT DOES
- Account icon badge: a small lime counter badge on the header account
  icon showing the current user's unread notification count. Hidden when
  there are none.
- My Account -> Notifications: a new tab listing every notification for
  that customer (winner alerts, draw-completed alerts, admin
  announcements), newest first. Unread ones are visually highlighted;
  visiting the tab marks everything shown as read.
- Automatic notifications (WooCommerce -> Notifications in wp-admin, two
  toggles, both on by default):
  - Notify the winner when a draw is completed.
  - Notify every other entrant in that raffle that the draw is complete.
  Both are deduplicated per (raffle, type, user) so a retried request can
  never double-notify someone for the same draw.
- Manual notifications (same admin screen): title, message, optional link,
  and a target of "All registered users" or "a specific user" (by email,
  username, or user ID). For later use as an announcements tool.
- Recent Notifications log (last 50) on the same admin screen, for
  visibility into what has actually been sent and to whom.

DATA
One new table, `{prefix}rafflelb_notifications`: id, user_id, type
(winner / draw_completed / announcement), title, message, link_url,
product_id, is_read, created_at, read_at. Nothing here writes to or reads
from RaffleLB Draw Engine's own tables except via the public hook.
