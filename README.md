# WP Booking System - Payrexx

Payrexx payment gateway add-on for [WP Booking System Premium](https://www.wpbookingsystem.com/).
Customers are redirected to a Payrexx payment page (TWINT, credit cards, PostFinance, …) and the booking is created once Payrexx confirms the payment.

## Requirements

- WP Booking System Premium 5.12.7 or newer
- A Payrexx instance and its API secret

## Setup

1. Activate the plugin.
2. In **WP Booking System → Settings → Payment → Payrexx**, enable Payrexx and enter
   - **Instance name**: the part before `.payrexx.com` (e.g. `meinhotel` for `meinhotel.payrexx.com`)
   - **API secret**: Payrexx admin → *Integrations → API & Plugins*
3. Set the WP Booking System currency (Settings → Payment) to **CHF** (or another currency your Payrexx instance accepts).
4. In Payrexx admin → *Webhooks*, add the webhook URL shown on the settings page
   (`https://example.ch/?wpbs-payrexx-webhook`), type **Normal (PHP-Post)** or **JSON**.
5. Add the *Payrexx* option to the payment method field of your booking form.

## How it works

1. On submit, the add-on creates a Payrexx **Gateway** (`POST /Gateway/`) with the amount in Rappen,
   the booking data transient ID as `referenceId`, and success/failed/cancel redirect URLs.
2. The customer pays on the Payrexx page and is redirected back.
3. Payrexx calls the webhook. The add-on never trusts the webhook payload: it re-reads the gateway
   from the Payrexx API and only creates the booking when the gateway is `confirmed`.
4. As a fallback (e.g. on `localhost`, where Payrexx cannot reach the webhook) the confirmation page
   checks the gateway status itself while it shows the "processing payment" message.

Part payments (deposit + final payment), payments on the secondary payment page and refunds from the
booking modal are supported.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `wpbs_payrexx_gateway_args` | filter | Change the arguments sent to `POST /Gateway/` |
| `wpbs_payrexx_metadata` | filter | Change the metadata collected from the form |
| `wpbs_payrexx_api_base_url` | filter | Change the API base URL (default `https://api.payrexx.com/v1.0/`) |
| `wpbs_payrexx_payment_scroll_to_anchor` | filter | Anchor appended to the success redirect URL |
