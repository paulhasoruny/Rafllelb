RaffleLB Notifications v1.2.0

WHAT CHANGED IN 1.2.0
- Listens to Draw Engine's fulfillment-status event without writing fulfillment state itself.
- Adds an optional My Account notification when the winner's prize is marked Prize Fulfilled.
- Fulfillment notifications are deduplicated by permanent draw result id and are not repeated by page refreshes or same-status saves.
- Adds the Prize Fulfilled notification type/icon to customer and admin notification views.
- No fulfillment email is sent automatically; winner-email behavior remains unchanged.

PURPOSE
Owns customer notification delivery for RaffleLB: account notifications,
unread badge, winner email delivery, draw-completed alerts for other entrants,
and admin announcements. Draw Engine remains the owner of draw state/results and
emits the permanent draw-completed event.

REQUIRES
- WooCommerce
- RaffleLB Draw Engine 0.34.18.29+ recommended.
  The first five rafflelb_draw_completed hook arguments remain backward
  compatible; Draw Engine 0.34.18.29 adds a sixth read-only context argument
  (entry number, order id, selected timestamp, method) so Notifications does
  not need to duplicate draw/result logic.

WHAT CHANGED IN 1.1.0
- Winner delivery ownership moved into Notifications.
- Automatic winner notification now creates the My Account notification and
  sends the branded winner email from the same draw-completed event.
- Winner message/email includes the prize, winning entry number, and draw date.
- Duplicate protection is tied to the permanent draw result id, with legacy
  product/type/user fallback for older notification rows.
- Notification rows now record source_result_id, email_address, and
  email_sent_at.
- On successful email delivery, Notifications emits rafflelb_winner_email_sent.
  Draw Engine listens to that event and updates its own winner_email_sent_at
  audit field, preserving plugin ownership boundaries.
- The existing Draw Engine Send/Resend Winner Email admin control delegates to
  Notifications when it is active. If Notifications is disabled, Draw Engine's
  legacy manual sender remains as a compatibility fallback.
- Other entrants continue receiving the existing draw-completed account
  notification. Void entries remain excluded because Draw Engine supplies only
  eligible draw state and the entrant query still targets active entries.

ADMIN SETTINGS
WooCommerce -> Notifications
- Notify the winner in My Account and by email when a draw is completed.
- Notify every other active entrant that the draw is complete.
- Send manual announcements to all registered users or one specific user.
- Recent Notifications now shows winner email delivery status.

DATA
Table: {prefix}rafflelb_notifications
Fields include: id, user_id, type, title, message, link_url, product_id,
source_result_id, email_address, email_sent_at, is_read, created_at, read_at.

OWNERSHIP
- Draw Engine: raffle result, winner, entry eligibility, capacity, audit state.
- Notifications: customer-facing notification/email delivery and delivery log.
- No draw, winner-selection, capacity, order, hold, or entry mutation logic is
  duplicated here.
