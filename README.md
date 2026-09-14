A fully configurable, app-style custom checkout flow for WooCommerce, with your own choice of payment methods, colors and pages.

== Description ==

Replaces the default WooCommerce checkout with a custom, app-style flow (cart -> payment ->
confirmation), fully configurable from the WordPress dashboard — no code editing required:

* **Choose your payment methods**: PayPal (reuses your existing WooCommerce PayPal gateway — this
  plugin never handles card data), plus any combination of manual methods: Zelle, Binance Pay, bank
  transfer, mobile payment, Bancolombia, and cash on site.
* **Manual methods** create the order and mark it "Pending verification" with the customer's payment
  proof attached, so you can confirm it yourself from the WooCommerce order screen.
* **PayPal** creates the order and redirects to WooCommerce's own native "Pay for order" page, where
  your already-configured PayPal gateway handles the actual charge — the integration is never
  reimplemented, it's reused, which is the secure way to do it.
* **Full styling control**: colors, border radius and font family, all from Settings → Payment Gateway.
* **Automatic page setup**: on activation, the plugin creates the Cart, Checkout and Confirmation pages
  with the right shortcodes if they don't already exist. If they do exist, you can re-sync (overwrite)
  their content with one click from the settings page at any time.

= Security =

This plugin was built with a security-first approach and includes fixes applied during an audit of
the original script:

* **Real file-type validation for proof-of-payment uploads.** The original approach trusted the
  uploaded file's *name* to decide whether it was a JPG/PNG/PDF — a file renamed to
  `something.php.jpg` could bypass that check. Uploads are now validated with
  `wp_check_filetype_and_ext()`, which inspects the actual file content/signature, not just the name.
* **Basic anti-abuse protection** on the public order-creation endpoint: a honeypot field (invisible
  to real users, often filled in by bots) plus a per-IP rate limit, since the original had no
  protection against scripted spam order creation.
* **No business/financial data hardcoded in the plugin.** Bank accounts, Zelle/Binance details, and
  office addresses are configured from the settings page and stored in the database — never in
  plugin source code.
* **Order confirmation page uses a timing-safe key comparison** (`hash_equals()`) exactly like
  WooCommerce's own "order received" page, so an order can't be viewed by guessing its ID.
* Sanitization and output escaping reviewed throughout (`sanitize_text_field`, `sanitize_email`,
  `esc_html`, `esc_attr`, `esc_url` applied consistently on both input and output).

Developed by [Fran Velazco](https://www.linkedin.com/in/fran-velazco/).
Built with Claude (Anthropic) as a development assistant and security reviewer.

== Installation ==

1. Make sure WooCommerce is active, with at least one PayPal gateway enabled under WooCommerce →
   Settings → Payments (only needed if you plan to enable PayPal).
2. Upload the `custom-payment-gateway` folder to `/wp-content/plugins/`.
3. Activate it — this automatically creates the Cart, Checkout and Confirmation pages if they don't
   already exist.
4. Go to Payment Gateway (left-hand admin menu) to choose which payment methods are enabled, fill in
   your account/instructions text for each, and customize colors and style.
5. If you already had Cart/Checkout/Confirmation pages with different content, use the "Create missing
   pages / Sync" button at the bottom of the settings page (with "Overwrite" checked) to apply this
   plugin's design to them.

== Changelog ==

= 1.0.0 =
* Initial release: configurable payment methods, full color/style settings, automatic page creation
  and sync, and the security fixes described above (real file-type validation, rate limiting/honeypot,
  no hardcoded business data).
