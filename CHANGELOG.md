# Changelog

All notable changes to **Panth EU Withdrawal Button** are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-06-18
### Changed
- Rewrote README to match standard Panth Infotech template: added Quick Answer block,
  Who Is It For, Configuration table (all system.xml fields), How It Works walkthrough,
  FAQ section, Support table with product page row, and Quick Links table.
- Fixed canonical URL in SEO meta to the live product page.
- Removed link to commercemarketplace.adobe.com; Marketplace link now targets the live product page.

## [1.0.0] - 2026-06-16
### Added
- Digital withdrawal function for Directive (EU) 2023/2673 (mandatory from 19 June 2026).
- Public, guest-capable two-step flow (order lookup → confirmation) on a dedicated
  `/withdrawal` route, plus an in-place pop-up modal opened from any entry point.
- Always-visible floating side tab (left or right), with optional header, footer and
  customer-account placements.
- "Cancel my order" button on the customer order-view page, pre-filled and signed per order.
- Logged-in order dropdown with name and email pre-filled, served through a Full-Page-Cache
  safe session endpoint.
- Durable-medium customer confirmation email with proof reference, date/time and withdrawal
  content; admin notification email; signed pre-filled withdrawal link injected into order
  confirmation emails; refund-deadline reminder email.
- Customer account "My withdrawals" history with status badges and a per-request detail view.
- Duplicate submissions show the existing request's status page instead of recording again.
- Admin request grid and detail screen (status workflow + internal note), and an
  "EU Withdrawal Request" panel on the admin order view linking the order to its request.
- Configurable withdrawal window (default 14 days) from order or shipment date; optional
  order status on withdrawal; editable compliance notices.
- Honeypot, JavaScript speed-trap, per-IP rate limiting and signed HMAC tokens.
- Batch cron: retries failed confirmation emails and sends refund-deadline reminders.
- Full Hyvä (Alpine.js + Tailwind) and Luma support via the shared Panth_Core theme helper;
  external CSS/JS, CSP-safe.
- Translations: English, Dutch, German, French.
