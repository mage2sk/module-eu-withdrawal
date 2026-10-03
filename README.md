# Magento 2 EU Withdrawal Button

This module adds a digital order withdrawal (cancellation) function to a Magento 2 or Adobe Commerce storefront. Customers and guests open a withdrawal form from an always-visible button or link, identify their order by order number and email address, confirm the withdrawal in a second step, and receive a confirmation email with a unique proof reference. Store staff get a request grid in the admin, a panel on the order view, notification emails and optional refund-deadline reminders. It is intended for stores that sell to consumers in the EU. The storefront templates, CSS and JavaScript are theme-neutral and are written to work on both Hyva and Luma.

The module's configuration comments refer to Directive (EU) 2023/2673. The module provides the technical function only and does not by itself make a store legally compliant. Merchants should confirm their obligations, withdrawal periods and notice texts with their own legal adviser.

Product page: [Magento 2 EU Withdrawal Button](https://kishansavaliya.com/magento-2-eu-withdrawal.html)

## Features

- Storefront entry points: a floating side button (left or right), a header link, a footer link and a "My withdrawals" link in the customer account navigation. Which ones appear is set in the configuration. On phones and tablets (up to 1024 px) the floating button is a 44 x 44 px icon on the shared bottom stack, and it hides while a menu, drawer, mini cart, search dropdown or modal is open and on the checkout page. Floating elements share one bottom stack per side: back-to-top sits at the bottom, the WhatsApp button above it, the EU withdrawal tab (phones and tablets) above that. Each element adds `--panth-bottom-bar-offset` (bottom notification bars), the slot variables `--panth-float-slot-btt-<side>` and `--panth-float-slot-wa-<side>` of the elements below it, and `--panth-float-edge` (24px, 16px below 768px).
- A withdrawal button (using the configured label) on the customer's order view page, shown only while the order is inside the withdrawal period, is not canceled or closed, and has no existing request.
- A modal withdrawal form opened from the entry points, with the `/withdrawal` page as the fallback when JavaScript is not available. The modal keeps keyboard focus inside the dialog (Tab and Shift+Tab cycle through its controls), closes with Escape, the close button or a backdrop click, returns focus to the trigger, and keeps the page scroll position.
- Two-step flow: step 1 asks for order number, email address, name and an optional reason; step 2 shows an order summary and asks the customer to confirm. Nothing is recorded before the confirmation.
- Works for guest orders; no login is needed. For logged-in customers the order number field becomes a dropdown of their recent orders that are still within the withdrawal period and have no request yet, and name and email are pre-filled.
- One request per order. A second attempt for the same order shows the status of the existing request.
- Withdrawal period in days (default 14), counted from the order date or the first shipment date.
- Each request is stored with a proof reference (`WDR-` followed by 16 characters), the date and time, a text snapshot of the withdrawn items and order total, the IP address and the user agent.
- A status history comment is added to the order, and an optional order status can be applied.
- Customer confirmation email, admin notification email and a pre-filled, signed withdrawal link appended to order emails.
- Customer account pages listing the customer's requests with status and a detail view.
- Admin grid of requests with keyword search (proof reference, order number, customer name and email, reason), filters and mass delete, a detail page to change the status and add an internal note, and a panel on the admin order view.
- Cron batch processor that retries unsent customer confirmations and sends refund-deadline reminders to the admin address.
- Honeypot field, a minimum form-fill time check and per-IP limits on the lookup and submit steps of the public form.
- Editable notice texts (right of withdrawal, excluded products, return shipping costs, refund policy).
- Translation files for en_US, de_DE, fr_FR and nl_NL.

## Compatibility

| Item | Supported |
|---|---|
| Magento Open Source / Adobe Commerce | 2.4.4 to 2.4.8 |
| PHP | 8.1, 8.2, 8.3, 8.4 |
| Themes | Hyva and Luma |

Magento Composer constraints: `magento/framework >=103.0`, `magento/module-backend >=102.0`, `magento/module-store >=101.1`, `magento/module-sales >=103.0`, `magento/module-ui >=101.2`.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8
- PHP 8.1 to 8.4
- `mage2kishan/module-core` `^1.0` (module `Panth_Core`), installed automatically by Composer
- Magento cron running, for the batch processor
- Working outgoing email, for the confirmation and notification emails

## Installation

```bash
composer require mage2kishan/module-eu-withdrawal
bin/magento module:enable Panth_Core Panth_EuWithdrawal
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode, also compile and deploy the static assets (the module ships files under `view/frontend/web` and `view/adminhtml/web`):

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Check that the module is enabled:

```bash
bin/magento module:status Panth_EuWithdrawal
```

## Configuration

Admin path: **Stores > Configuration > Panth Extensions > EU Withdrawal Button**

The same page is linked from the admin menu entry **EU Withdrawal Button > Configuration**. All config paths start with `panth_euwithdrawal/`.

### General

| Field | Type | Default | Config path | Notes |
|---|---|---|---|---|
| Enable Withdrawal Button | Yes/No | Yes | `panth_euwithdrawal/general/enabled` | No disables the storefront pages, links and the order email link. |
| Button / Link Label | Text | Cancel my order | `panth_euwithdrawal/general/button_label` | Used for the button, links, form page title and order email link. |
| Storefront Placement | Multiselect | Floating side button, Customer Account | `panth_euwithdrawal/general/placement` | Options: "Floating side button (recommended)", "Header link", "Footer link", "Customer Account". |
| Floating Button Side | Select | Right | `panth_euwithdrawal/general/float_side` | Right or Left. |
| Withdrawal Period (days) | Text (digits, greater than zero) | 14 | `panth_euwithdrawal/general/period_days` | Falls back to 14 if empty or 0. |
| Period Starts From | Select | Order date | `panth_euwithdrawal/general/period_basis` | "Order date" or "Shipment date (date of receipt)". |
| Set Order Status On Withdrawal | Select (order statuses) | (empty) | `panth_euwithdrawal/general/order_status` | Empty keeps the current status. A status history comment is always added. |

All fields in this group except "Enable Withdrawal Button" are shown only when "Enable Withdrawal Button" is Yes.

### Withdrawal Form

| Field | Type | Default | Config path | Notes |
|---|---|---|---|---|
| Ask For Withdrawal Reason | Yes/No | Yes | `panth_euwithdrawal/form/ask_reason` | The reason field is always optional. |
| Enable Honeypot Spam Protection | Yes/No | Yes | `panth_euwithdrawal/form/enable_honeypot` | Hidden field plus a minimum fill time of 1.2 seconds when JavaScript ran. |
| Lookups Allowed Per IP (per 10 min) | Text (digits) | 10 | `panth_euwithdrawal/form/rate_limit` | Applies to the lookup step and, with a separate counter, to the submit step. 0 disables the limit. |
| Failed Attempts Allowed Per Order Number (per hour) | Text (digits) | 5 | `panth_euwithdrawal/form/reference_attempt_limit` | Failed lookups and submits for one order number are counted across all IP addresses. Once the limit is reached the order number is locked for one hour after the last failure, even with the correct email. 0 disables the lock. |

### Compliance Content

These texts are shown on the withdrawal pages. The refund policy text is also shown on the confirm and success pages and included in the customer confirmation email.

| Field | Type | Config path |
|---|---|---|
| Right Of Withdrawal Notice | Textarea | `panth_euwithdrawal/compliance/intro_text` |
| Excluded Products Notice | Textarea | `panth_euwithdrawal/compliance/excluded_products_text` |
| Return Shipping Costs Notice | Textarea | `panth_euwithdrawal/compliance/return_shipping_text` |
| Refund Policy Notice | Textarea | `panth_euwithdrawal/compliance/refund_policy_text` |

Each field has a default English text in `etc/config.xml`. Review and adjust these texts to match your own terms.

### Notifications & Emails

| Field | Type | Default | Config path | Shown when |
|---|---|---|---|---|
| Email Sender | Select | General Contact | `panth_euwithdrawal/email/sender_identity` | |
| Send Customer Confirmation (durable proof) | Yes/No | Yes | `panth_euwithdrawal/email/send_customer_confirmation` | |
| Customer Confirmation Template | Select | Panth EU Withdrawal - Customer Confirmation (durable proof) | `panth_euwithdrawal/email/customer_template` | Send Customer Confirmation = Yes |
| Send Admin Notification | Yes/No | Yes | `panth_euwithdrawal/email/send_admin_notification` | |
| Admin Notification Email | Text (email) | orders@example.com | `panth_euwithdrawal/email/recipient_email` | Send Admin Notification = Yes |
| Admin Notification Template | Select | Panth EU Withdrawal - Admin Notification | `panth_euwithdrawal/email/admin_template` | Send Admin Notification = Yes |
| Add Withdrawal Link To Order Emails | Yes/No | Yes | `panth_euwithdrawal/email/inject_order_email_link` | |

Replace the placeholder "Admin Notification Email" with a real address. The refund-deadline reminder is also sent to this address.

### Batch Processing & Reminders

This group is available at default (global) scope only.

| Field | Type | Default | Config path | Shown when |
|---|---|---|---|---|
| Enable Batch Processor (cron) | Yes/No | Yes | `panth_euwithdrawal/batch/cron_enabled` | |
| Batch Size | Text (digits, greater than zero) | 50 | `panth_euwithdrawal/batch/batch_size` | Enable Batch Processor = Yes |
| Send Refund-Deadline Reminder To Admin | Yes/No | Yes | `panth_euwithdrawal/batch/refund_reminder_enabled` | Enable Batch Processor = Yes |
| Remind After (days) | Text (digits, greater than zero) | 10 | `panth_euwithdrawal/batch/refund_reminder_days` | Send Refund-Deadline Reminder = Yes |

## Usage

### Storefront URLs

| URL | Purpose |
|---|---|
| `/withdrawal` | Withdrawal form page (step 1). Accepts `o`, `e` and `t` query parameters to pre-fill order number and email when the signed token is valid. |
| `/withdrawal/index/lookup` | POST target of step 1. Shows the confirmation page (step 2) or the status of an existing request. |
| `/withdrawal/index/submit` | POST target of step 2. Records the withdrawal. |
| `/withdrawal/index/success` | Confirmation page with the proof reference. |
| `/withdrawal/account` | "My withdrawals" list for logged-in customers. |
| `/withdrawal/account/view/request_id/<id>` | Detail of one request for the logged-in customer. |
| `/withdrawal/customer/orders` | JSON list of the logged-in customer's orders, used by the form's order dropdown. |

When the module is disabled, the form page returns a 404 and the other actions redirect away.

### Customer and guest flow

1. The customer opens the form from the floating button, the header or footer link, the account link, the button on the order view page, or the link in the order email.
2. Step 1: the customer enters order number, email address, name and, if enabled, a reason. Logged-in customers can choose an order from a dropdown. The dropdown takes up to 25 of the customer's most recent orders that are not canceled or closed (when the period starts from the order date, only orders created within the configured number of days) and lists those that are still within the withdrawal period.
3. On lookup the module checks, in order: the honeypot and fill-time check, the per-IP limit, that all required fields are filled and the email is valid, the per-order-number lock, the signed token when one is present, that an order with that increment ID exists and its customer email matches (case-insensitive), that no request exists for the order yet, that the order is not in the canceled or closed state, and that the current time is within the withdrawal period.
4. Step 2: the confirmation page shows the order items and order total. The customer confirms.
5. On submit the checks run again, with a separate per-IP counter, then the request is saved with status "Received", a status history comment with the date and proof reference is added to the order, and the configured order status is applied if one is set.
6. The customer is redirected to the success page showing the proof reference.

Withdrawal period: the start is the order creation date or, when "Period Starts From" is "Shipment date (date of receipt)", the creation date of the order's first shipment (the order date is used when there is no shipment). The deadline is the start plus the configured number of days, compared in UTC.

The module records the withdrawal only. It does not cancel, credit or refund the order; staff handle the refund.

### Order email link

When "Add Withdrawal Link To Order Emails" is Yes, a plugin on `Magento\Sales\Block\Order\Email\Items` appends a short block with a button below the items of order emails, as long as the order is still within the withdrawal period and is not canceled or closed. The link points to `/withdrawal` with the order number, email and an HMAC-SHA256 token keyed with the store's `crypt/key`.

### Emails

| Template ID | Label | Sent to | When |
|---|---|---|---|
| `panth_euwithdrawal_email_customer_template` | Panth EU Withdrawal - Customer Confirmation (durable proof) | Customer | On submit, if enabled. Retried by cron until sent. |
| `panth_euwithdrawal_email_admin_template` | Panth EU Withdrawal - Admin Notification | Admin Notification Email | On submit, if enabled. |
| `panth_euwithdrawal_refund_reminder` | Panth EU Withdrawal - Refund Deadline Reminder | Admin Notification Email | By cron, after "Remind After (days)". |

The customer and admin emails contain the proof reference, order number, date and time, reason (if given) and the content snapshot. The customer and admin templates can be copied under Marketing > Email Templates and selected in the configuration; the reminder template ID is fixed in code. When "Email Customer On Status Change" (`panth_euwithdrawal/email/notify_status_change`, default No) is Yes, changing a request's status in the admin sends the "Panth EU Withdrawal - Status Update" template (`panth_euwithdrawal/email/status_template`) to the customer with the new status, proof reference, order number and date. The internal note is not included. With the default No, a status change sends no email.

### Customer account

Logged-in customers see a "My withdrawals" link in the account navigation when "Customer Account" is selected in "Storefront Placement". The list shows requests for orders that belong to the logged-in customer's account (matched by customer ID, not by email), with status and proof reference, and a detail page per request. Requests for guest orders are not listed there; guests use the lookup form.

### Admin

- **EU Withdrawal Button > Withdrawal Requests** opens a grid with ID, Proof Reference, Order (linked to the order), Customer, Email, Reason, Status, Requested At and Store View, with keyword search, filters, View and Delete row actions and a mass Delete action. Delete and mass Delete accept POST requests only.
- The request detail page shows the stored data and proof content, and lets staff set the status (Received, Acknowledged, Refunded, Rejected) and save an internal note.
- The admin order view shows an "EU Withdrawal Request" panel with status, proof reference (linked to the request) and request date when a request exists for the order.

### Cron

| Job | Schedule | What it does |
|---|---|---|
| `panth_euwithdrawal_process_requests` | `*/15 * * * *` (every 15 minutes, `default` group) | When "Enable Batch Processor (cron)" is Yes: resends the customer confirmation for requests where it has not been sent (excluding Rejected), and, when reminders are enabled, sends a refund-deadline reminder for Received or Acknowledged requests older than "Remind After (days)". A reminder is marked sent only when the email was sent, so it is retried on the next run if sending fails or no Admin Notification Email is set. Each part handles up to "Batch Size" records per run. |

## Developer Notes

| Item | Value |
|---|---|
| Module name | `Panth_EuWithdrawal` |
| Composer package | `mage2kishan/module-eu-withdrawal` |
| PHP namespace | `Panth\EuWithdrawal` |
| Module sequence | `Magento_Store`, `Magento_Backend`, `Magento_Sales`, `Magento_Ui`, `Panth_Core` |
| Frontend route | `withdrawal` |
| Admin route | `panth_euwithdrawal` |
| Database table | `panth_eu_withdrawal_request` (unique keys on `order_id` and `proof_reference`) |
| Admin grid UI component | `panth_euwithdrawal_request_listing` |

Key classes:

- `Model\WithdrawalService`: order lookup, withdrawal window, duplicate check, content snapshot, submit, order comment and email dispatch.
- `Model\Config`: reads the `panth_euwithdrawal/*` settings.
- `Model\Mail`: sends the three emails.
- `Model\TokenManager`: HMAC-SHA256 token for pre-filled links, signed with the newest key in `crypt/key`; tokens signed with an older key after a key rotation are still accepted. There is no built-in fallback key; without `crypt/key` no token is issued or accepted.
- `Model\RateLimiter`: per-IP lookup and submit counters kept in the cache for 10 minutes, and a per-order-number failure counter kept for one hour. Cache keys use SHA-256.
- `Model\BotGuard`: honeypot and fill-time check.
- `Plugin\OrderEmailWithdrawalLink`: `afterToHtml` plugin on `Magento\Sales\Block\Order\Email\Items`.
- `Cron\ProcessRequests`: confirmation retries and refund reminders.
- `ViewModel\Withdrawal`: data for the storefront templates.

ACL resources:

- `Panth_EuWithdrawal::config` (configuration section)
- `Panth_EuWithdrawal::withdrawal` (EU Withdrawal Button)
  - `Panth_EuWithdrawal::request_view` (View Withdrawal Requests: grid and detail)
  - `Panth_EuWithdrawal::request_manage` (Manage Withdrawal Requests: save, delete, mass delete)

Status values stored in `panth_eu_withdrawal_request.status`: 1 Received, 2 Acknowledged, 3 Refunded, 4 Rejected.

## Uninstallation

```bash
bin/magento module:disable Panth_EuWithdrawal
composer remove mage2kishan/module-eu-withdrawal
bin/magento setup:upgrade
bin/magento cache:flush
```

The module has no uninstall script. After removal, the `panth_eu_withdrawal_request` table (with the stored withdrawal records) and any saved `panth_euwithdrawal/*` rows in `core_config_data` remain in the database. Export any records you need to keep before deleting them manually.

## Support

- Product page: [kishansavaliya.com/magento-2-eu-withdrawal.html](https://kishansavaliya.com/magento-2-eu-withdrawal.html)
- Contact: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- GitHub issues: [github.com/mage2sk/module-eu-withdrawal/issues](https://github.com/mage2sk/module-eu-withdrawal/issues)

## Documentation

See [USER_GUIDE.md](USER_GUIDE.md) for a configuration and usage guide.

Screenshots in `docs/screenshots`:

- [Floating button on the storefront](docs/screenshots/01-storefront-floating-button.png)
- [Withdrawal modal](docs/screenshots/02-withdrawal-modal.png)
- [Confirm withdrawal page](docs/screenshots/03-confirm-withdrawal.png)
- [Withdrawal received page](docs/screenshots/04-withdrawal-received.png)
- [My withdrawals in the customer account](docs/screenshots/05-account-my-withdrawals.png)
- [Withdrawal detail in the customer account](docs/screenshots/06-account-withdrawal-detail.png)
- [Admin configuration](docs/screenshots/07-admin-configuration.png)
- [Admin requests grid](docs/screenshots/08-admin-requests-grid.png)
- [Admin request detail](docs/screenshots/09-admin-request-detail.png)
- [Admin order view panel](docs/screenshots/10-admin-order-view-panel.png)

## License

Commercial software license. See [LICENSE.txt](LICENSE.txt) in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions catalogue: [kishansavaliya.com/magento-extensions.html](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [github.com/mage2sk/module-eu-withdrawal](https://github.com/mage2sk/module-eu-withdrawal)
- Packagist: [packagist.org/packages/mage2kishan/module-eu-withdrawal](https://packagist.org/packages/mage2kishan/module-eu-withdrawal)
