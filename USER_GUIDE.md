# Panth EU Withdrawal Button — User Guide

This guide explains how the module works and how to configure it for compliance
with **Directive (EU) 2023/2673**.

## 1. How the customer flow works

1. The customer clicks the **withdrawal link** (header/footer) or the link in
   their **order confirmation email**, or visits `https://yourstore.com/withdrawal`.
2. **Step 1** — they enter the minimal details: order number, email, name, and
   (optionally) a reason. No login or account is required, including for guests.
3. The module verifies the order matches the email and is still inside the
   withdrawal window.
4. **Step 2** — a confirmation page summarises the order. The customer clicks
   **Confirm withdrawal**.
5. The withdrawal is recorded, a **durable-medium confirmation email** with the
   proof reference, date/time and content is sent, the store admin is notified,
   and a comment is added to the order.

Refunds are **not** processed automatically — your team handles the refund
within 14 days via the original payment method, as the directive requires.

## 2. Configuration

Open **Stores → Configuration → Panth Extensions → EU Withdrawal Button**.

### General
| Field | Notes |
|---|---|
| Enable Withdrawal Button | Master switch. |
| Button / Link Label | Use clear wording, e.g. "Cancel my order". |
| Storefront Placement | Header and/or footer; stays visible on every page. |
| Withdrawal Period (days) | Minimum 14. Extend to 365 if customers were not informed before purchase. |
| Period Starts From | Order date, or shipment date (date of receipt). |
| Set Order Status On Withdrawal | Optional custom status; a comment is always added. |

### Withdrawal Form
- **Ask For Withdrawal Reason** — when on, the reason field shows but is always optional.
- **Honeypot** and **Lookups Per IP** — abuse protection for the public lookup step.

### Compliance Content
Notices shown on the page and in emails: right of withdrawal, excluded products,
return-shipping costs, and refund policy. Edit these to match your terms.

### Notifications & Emails
- Customer confirmation (durable proof) and admin notification templates.
- **Add Withdrawal Link To Order Emails** — injects a signed, pre-filled link
  into every order confirmation email.

### Batch Processing & Reminders
- **Enable Batch Processor** — runs every 15 minutes.
- Retries any confirmation email that failed to send.
- **Refund-Deadline Reminder** — emails the admin before the 14-day refund window lapses.

## 3. Managing requests

**Panth Extensions → EU Withdrawal Button → Withdrawal Requests**

- Grid of all requests with order link, customer, status and date.
- Open a request to view the full proof content, change its status
  (Received → Acknowledged → Refunded / Rejected) and add an internal note.

## 4. Theme support

Rendering is automatic: Alpine.js + Tailwind markup on **Hyvä** storefronts and
standard markup on **Luma**, selected via the shared Panth theme detector. Order
emails are theme-independent.

## 5. Troubleshooting

| Symptom | Fix |
|---|---|
| Link not visible | Check **Enable** and **Placement**; flush cache. |
| Wrong theme markup | `bin/magento cache:flush` and remove `generated/code/Panth/`. |
| No confirmation email | Check sender identity and mail transport; the batch cron retries failures. |
| Order not found | Order number + email must match exactly; check the store scope. |

## 6. Privacy

Only the data needed for the withdrawal (name, email, order number, optional
reason) is stored, plus IP/user-agent for abuse prevention. Configure retention
to match your privacy policy.
