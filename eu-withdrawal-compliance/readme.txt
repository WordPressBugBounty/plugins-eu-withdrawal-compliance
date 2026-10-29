=== EU Withdrawal and Legal Guarantee Compliance ===
Contributors: fernandot, ayudawp
Tags: woocommerce, withdrawal, consumer-rights, legal-guarantee, garan
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free EU consumer-rights toolkit: withdrawal function, harmonised legal guarantee notice (GARAN), checkout consents, Article 16 exclusions.

== Description ==

Two EU deadlines, one plugin. From **19 June 2026**, Directive 2023/2673 obliges every online retailer in the EU to offer a digital withdrawal function at least as easy to use as the purchase flow. From **27 September 2026**, Directive (EU) 2024/825 and Implementing Regulation (EU) 2025/1960 oblige every shop selling goods to display the harmonised notice on the legal guarantee of conformity. Most plugins in the directory stop at "a withdrawal button". This one covers both, free and complete.

= The legal guarantee notice =

The official notice ships bundled in the 24 EU languages and is shown unedited, as the regulation requires: above the place-order button of both the classic and the block checkout, and in your order emails with the official PDF attached, so it survives the mail clients that block remote images. Three display modes (the whole notice, a disclosure, or a native popover for narrow columns), the Spanish three-year note where it applies, a shortcode for your own guarantee page, a footer link, and a private order note recording which notice each buyer saw. Only shown when the cart holds goods: the notice does not cover services or digital content.

= The withdrawal function =

* **Two-step confirmation (Art. 11a(3))**: the form leads to a review screen with a read-only summary and a "Confirm withdrawal" button, and the request is registered only when that button is pressed. Held server-side in a single-use token, so it works without JavaScript.
* **Durable-medium acknowledgement (Art. 11a(4))**: the confirmation email reproduces the full declaration and the exact date and time of submission, with a **verifiable SHA-256 receipt hash** recomputable from the stored fields if a dispute arises.
* **Annex I.B model withdrawal form** generated from your shop data, collapsible below the public form and printable from the same URL. Meets the information obligation of Art. 6(1)(h), which the new directive does not replace.
* **Two consent checkboxes at the WooCommerce checkout**: the mandatory one for digital content (Art. 16(m)), which blocks the order until accepted, and the optional one for services started inside the 14-day window (Art. 14(4)(a)), which enables pro-rated billing. Both are persisted on the order with the exact text shown, accepted or declined, timestamp, IP and user agent.
* **One "Withdrawal status" dropdown per product and per category**, with four options, driving the Article 16 exclusion and the matching checkout consent at once, with full subcategory inheritance. Competing plugins gate this behind a paid tier.
* **Configurable notice on excluded products**, between price and add-to-cart, with its own title and body per type of exception.
* **Native GDPR integration**: Privacy Policy snippet, personal-data exporter and eraser, all keyed on the customer email.
* **Standalone mode**: form, shortcode, request log, emails, receipt hash, Annex I.B and GDPR all run without WooCommerce, in the same **Withdrawals** menu on every install.

= On the front end =

* A withdrawal page created on activation, with a neutral template ready to publish and the reminder to review it with a lawyer kept in the dashboard rather than in the page.
* `[ayudawp_withdrawal_form]` for the form, `[ayudawp_withdrawal_link]` for a permanent link to it from any footer or widget area, `[ayudawp_guarantee_notice]` for the guarantee notice, `[ayudawp_guarantee_link]` for its page, and `[ayudawp_withdrawal_excluded_notice]` for page builders that skip the standard WooCommerce hooks.
* Semantic form with HTML5 validation, honeypot, escaped output, sanitized input and CSRF nonces, plus a privacy-policy checkbox linked to your configured page.

= With WooCommerce =

* **My Account → Right of withdrawal**, with a per-order "Withdraw" button while the order is in an eligible status, deep-linked to the form with the order pre-filled.
* **Request tracking for the customer**: the same screen lists their requests with date, order, scope, status, your resolution note and the receipt code, and the order row shows an open request instead of an empty slot.
* **Withdrawal notice in the transactional emails**, with a direct link to the form. Eligible statuses configurable; admin emails never receive it.
* **Automatic verification of the order and email pair**, gated by the configured statuses. The 14-day deadline is an advisory flag for you, not an automatic rejection, because for goods the period runs from delivery. Basis and grace days configurable, with an optional strict mode.
* **Optional "Accept unmatched requests"** mode: register what does not match an order as *Unverified* for manual review instead of rejecting it. Off by default.
* **Order-number compatibility** with Sequential Order Numbers and Custom Order Numbers (Tyche and WPFactory), plus a filter for any other scheme.
* **"Withdrawal" column** on the orders screen, private order notes at every step, and HPOS compatibility declared.

= In the admin =

* **Full request log** as a private post type with its status lifecycle, customer details, scope, IP, user agent and UTC timestamp.
* **CSV export** for accounting and consumer-protection audits, by bulk action or filtered by status and date range, with cells escaped against formula injection.
* **Audit trail per request**: resolution timestamp on every status change and whether the acknowledgement was accepted for delivery, in the detail screen and in the CSV.
* **Bulk actions**, a status metabox with a required comment when rejecting, and the captured checkout consents on file.
* **Emails**: acknowledgement to the customer, notification to the shop with reply-to set to the customer, and a follow-up on every status change.
* **Legal disclaimer** in the settings page, and a Mandatory / Recommended / Optional tag on every setting.

= Multilingual stores: WPML and Polylang =

Compliance cannot depend on the language the customer was browsing in, so both are supported with nothing to configure. Set the withdrawal status once, on the product or category in your original language, and it holds across every translation, with an explicit status on a translation still winning. Every link follows the visitor's language, and so does the guarantee notice. The bundled `wpml-config.xml` exposes your own editable texts to String Translation and marks the request log as non-translatable. See the FAQ for the current limitation on the plugin's own emails.

= Built for production =

* CSS only loads where it is needed: the withdrawal page, the checkout, product pages that actually show a notice, and the plugin's own admin screens.
* Delivered as language packs from [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/eu-withdrawal-compliance/). Follows the WordPress Coding Standards, with escaped output, sanitized input, capability checks and nonces.
* **19 documented filters and 4 actions**, so agencies can extend it without forking.
* PHP 7.4+, WordPress 6.0+, WooCommerce 7.0+ (optional).

== Why this plugin? ==

* **Fully free, no paid tier.** No premium add-on, no feature behind an upsell, no "Pro" version on the horizon.
* **The only plugin in the directory** that issues a SHA-256 receipt hash as durable proof of each request, that ships the Annex I.B model form, that injects the two checkout consents with proof on the order, and that gives you Article 16 exclusions with subcategory inheritance without a paid tier.
* **The harmonised guarantee notice included**, in the 24 languages, at the checkout and in the emails, which is the second obligation landing in 2026 and the one most plugins still ignore.
* **Real multilingual support** with WPML and Polylang, and everything configurable from the settings screen without writing a line of code.
* **Maintained by a Spanish WordPress trainer with 20+ years on the platform**, with the es_ES translation kept up to date by the author, replies on the support forum and an active roadmap of free improvements.

== Roadmap ==

Planned for upcoming free versions: the GARAN durability label per product; the checkout consents inside the Checkout block; HTML emails inheriting the WooCommerce theme, and status-change emails in the customer's language; a Gutenberg block and a widget for the withdrawal link; a custom "Withdrawal requested" order status; a PDF of the request with its receipt hash; and a dashboard widget.

== Privacy ==

For each request the plugin stores the customer name and email, the order reference and date, the IP address and User-Agent, the UTC submission timestamp and the SHA-256 receipt hash. All of it serves the legal traceability the directive asks for and lets the shop handle the request.

Data lives in a private custom post type (`ayudawp_withdrawal`) reachable only by the roles you authorise. Nothing is sent to third-party services: everything happens between your shop and your customer through standard WordPress emails.

Add a section to your privacy policy describing this storage. The plugin contributes a suggested snippet you can paste from **Settings → Privacy → Policy Guide**, and withdrawal data is exposed to **Tools → Export Personal Data** and **Tools → Erase Personal Data**, filtered by customer email.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. The plugin creates a "Right of withdrawal" page as a draft, with a sample legal template. Review it from **Pages** and publish it: until then customers cannot reach the form.
4. Go to **EU Compliance → Settings** to configure the notification email address and the page that hosts the form.
5. Add the URL of the withdrawal page to your footer or to the legal links section so it is visible from any page on your site.

== Frequently Asked Questions ==

= What is the harmonised legal guarantee notice, and do I have to show it? =

It is the official EU notice on the legal guarantee of conformity: the one headed "LEGAL GUARANTEE", with the QR code and the GARAN label. Article 22a of Directive 2011/83/EU, added by Directive (EU) 2024/825, makes it mandatory from **27 September 2026** for everyone selling goods to consumers in the EU, and Implementing Regulation (EU) 2025/1960 fixes its design. It does not cover B2B sales, services or digital content, so the plugin only shows it when the cart or the order holds at least one product that is not virtual.

Online the law asks for it in colour, unedited and in a prominent place, and the Commission's guidelines add that it should be shown in full, legible at the default display size and with a clickable link to the same destination as its QR code. The plugin does all of that, in the 24 EU languages, at both checkouts and in your order emails.

It arrives **switched off on sites updating from an earlier version**, with a notice in the dashboard, because it changes what your customers see at the checkout. New installs start with it on. Switch it on under **EU Compliance → Settings → EU legal guarantee notice (GARAN)**.

= Does the plugin show the GARAN label? =

Not yet, and most shops do not need it. GARAN is a different thing from the notice: the notice covers the **legal** guarantee and every shop selling goods has to display it, while GARAN stands for a **commercial durability guarantee** that a *producer* chooses to offer. It is mandatory only when that guarantee costs the consumer nothing, covers the whole product and lasts more than two years. When it does, the producer must use the label and you, as the seller, have to show it on the products that carry one, which in many catalogues is none of them.

It cannot ship bundled the way the notice does, because the producer fills in the years, the trademark and the model identifier. Per-product GARAN fields are the next step for this module. Unlike the notice, the label may be shown online in a nested format that expands on the first click.

= Where do the notice files come from, and under what licence? =

They are the official files from the European Commission [guidelines page](https://commission.europa.eu/publications/practical-guidelines-and-high-resolution-vector-files-eu-notice-and-label-product-guarantees_en), bundled unmodified under `assets/guarantee-notice/` with the md5 of each one in `CHECKSUMS.txt` so you can check that nothing was touched. The one exception is the English PNG: the Commission's PNG package ships 23 languages and no English, so that single file is rendered from the official English PDF at the same size as the other 23, with nothing edited, cropped or added. Its origin is recorded in the same file.

© European Union, 2025. Reused under the [CC BY 4.0 licence](https://creativecommons.org/licenses/by/4.0/), the Commission reuse policy implemented by Decision 2011/833/EU.

= The notice is too big, or too small, in my checkout. What do I do? =

Pick another display mode under **EU Compliance → Settings → EU legal guarantee notice (GARAN)**:

* **The whole notice** (default). The reading nobody disputes: the guidelines do accept showing it behind a first click, but the regulation describes that nested format only for the GARAN label.
* **A disclosure**: a line that expands into the notice, with the native `<details>` element.
* **A popover**: a button that opens the notice large over the page. This is the one for narrow columns, such as the Storefront order review at about 279px, where a full-width notice comes out too small to read.
* **Nothing at the checkout**, for shops that place it themselves. Emails and footer link keep working.

On phones the full notice is around 330px wide, so the image always links to the full-size file.

= The notice says two years and in Spain the legal guarantee is three. Isn't that wrong? =

The notice says "minimum two years" because that is the EU floor, and it cannot be edited: the regulation requires it unaltered. Spain raised the legal guarantee on new goods to three years from delivery (Art. 120.1 TRLGDCU), so the plugin prints a short line **next to** the notice, never inside it, linking to your terms and conditions page and, if you write the `id` of its heading in the settings, to the exact section. Automatic when your WooCommerce base country is Spain, and forceable either way for shops selling into Spain from elsewhere. Second-hand goods can carry a shorter period, never under one year, and the official notice already says so.

= Can I publish the notice on its own page, or link it from my footer? =

Yes. Put `[ayudawp_guarantee_notice]` on a page for the notice in full (`mode="details"` or `mode="popover"` for the other two), select that page in the settings, and `[ayudawp_guarantee_link]` gives you a permanent link to it, exactly like `[ayudawp_withdrawal_link]` does for the withdrawal page. Both accept `text` and `class`.

You can also replace the bundled file per language from your media library, in the same settings section, for example to serve it from your own CDN. Whatever you pick is shown unedited, so it has to be the official notice in that language, in colour and complete.

= In which language is the notice shown? =

In the language of the page, and in the language of the order for the emails, on shops running WPML or Polylang. The Commission publishes it in the 24 official EU languages; a shop in a language that is not one of them gets the official language of its own member state, so Catalan, Basque and Galician get the Spanish notice and Luxembourgish the French one. Anything else falls back to the language you pick in settings. The `ayudawp_euw_guarantee_fallback_lang` and `ayudawp_euw_guarantee_language_aliases` filters change both rules.

= WooCommerce ships its own order withdrawal page. Do I still need this plugin? =

They solve different halves of the same obligation: the native feature is a form, and this plugin is the toolkit around it.

WooCommerce 11.1 added `order_withdrawal` (off by default, under **WooCommerce > Settings > Advanced > Features**): a two-step public form on its own My Account endpoint, with a review screen and an acknowledgement email. What it does not do is link that form from anywhere: no My Account entry, no button on the order row, no line in the transactional emails, no footer link, which is the part Article 11a is actually about. It also keeps no record you can work with (no statuses, no notes, no export), and knows nothing about the Article 16 exceptions, the checkout consents, the Annex I.B model, the deadline of each order or the legal guarantee notice.

With both switched on, your shop answers with two forms at two addresses, each keeping its own record and sending its own acknowledgement, and nothing merges them. The settings page warns you when that happens. Keep one of the two.

= Can customers see what happened to a request they sent? =

Yes, on WooCommerce sites and for customers with an account. **My Account → Right of withdrawal** opens with their own requests: date, order, scope, current status, the note you write when you resolve it and the receipt code. The row of an order that already has a request shows that status where the button was, and a rejected request keeps its "Contest the rejection" button.

A request is listed when the customer sent it while signed in, or when it is linked to one of their own orders. The address of the account is deliberately not used: WooCommerce lets a customer change it without confirming the new one, so listing by address would expose requests sent as a guest from any address with no account. For guests the trace is the acknowledgement and the status emails, which is the durable medium the directive asks for. You can switch the whole thing off under **EU Compliance → Settings → General**.

= Can I move the checkout consent checkboxes somewhere else? =

Yes, with `ayudawp_euw_consent_hook`, but read the trade-off first. They render on `woocommerce_after_order_notes`, in the customer-details column, which WooCommerce renders once and leaves alone. The order-review panel next to the terms acceptance looks like the natural home, but WooCommerce re-renders it on every AJAX refresh, so a checkbox placed there is duplicated and loses what the customer already ticked whenever they change address, shipping or payment.

`add_filter( 'ayudawp_euw_consent_hook', function () { return 'woocommerce_review_order_before_submit'; } );`

Returning an empty string suppresses the render entirely. `ayudawp_euw_consent_applies` decides per cart and `ayudawp_euw_consent_is_required` makes either one mandatory.

= Will the form check the 14-day deadline? =

It does not auto-reject on it. For goods the period legally runs from delivery, a date the shop cannot detect, so rejecting on the order date would turn away customers still inside their real window. Requests that look late are **flagged** in the admin notification instead, and you check the real delivery date and decide.

You can tune the calculation under **EU Compliance → Settings**: order date or completion date as the basis, plus grace days. If your start date is reliable (services, digital content, shop pickup), switch **Deadline enforcement** from *Advisory* to *Strict* and late requests are blocked outright. Advisory is the default and the safe choice for goods.

= How do I mark products that are excluded from the right of withdrawal (Article 16)? =

With a single **Withdrawal status** dropdown, set per category (**Products → Categories**) or per product (General tab). A category applies its status to every product underneath and to its descendant categories; a product overrides it. The four options:

* **Standard** — withdrawal applies normally.
* **Digital content (Art. 16(m))** — excluded, and a mandatory consent checkbox appears at checkout for any cart containing it.
* **Service started early (Art. 14(4)(a))** — withdrawal still applies, with an optional consent so you can charge a pro-rated amount.
* **Dated services (Art. 16(l))** — accommodation, transport, car rental, catering, leisure: excluded, no consent needed.
* **Other Article 16 exception** — perishable, custom-made, hygiene-sealed, sealed media: excluded, no consent needed.

A request on an order containing excluded items is flagged for you, never auto-rejected, because a partial withdrawal over the rest of the order can still be valid. An order that only holds excluded products shows neither the withdrawal button nor the notice in its emails, and an order that mixes both names the excluded ones in that notice. Untick **Orders with excluded products** under **Eligible order statuses** if you grant the withdrawal on excluded products too. If the **Excluded products notice** is on, a configurable notice also appears on the product page between price and add-to-cart.

= The excluded-product notice does not appear with my page builder (Divi, Elementor, Bricks…). What can I do? =

Page builders such as Divi, Elementor, Bricks or ShopLentor render their own single-product template and skip the standard WooCommerce hook (`woocommerce_single_product_summary`) where the plugin injects the excluded-product notice, so it does not appear automatically. Drop the `[ayudawp_withdrawal_excluded_notice]` shortcode into your product layout (most builders have a "Shortcode" element) and the notice will print for the current product whenever that product is flagged as excluded. With no attributes it resolves the product being viewed; pass `id="123"` to target a specific product.

= How do the checkout consent checkboxes work (Art. 16(m) and Art. 14(4)(a))? =

The plugin injects them when the cart holds products flagged for them:

* **Type A (mandatory, Art. 16(m))**, digital content. The customer must accept it to complete the order; without it recorded, they keep the 14-day right even after accessing the content.
* **Type B (optional, Art. 14(4)(a))**, services started inside the window. Accepted, it lets you charge a pro-rated amount if they withdraw later. Without it, an early withdrawal means a full refund.

Each flag is set per product or per category, with subcategory inheritance. The exact text shown, the accepted or declined state, the timestamp, the IP and the user agent are persisted on the order as proof, and surfaced in the metabox of each request. Both can be switched off and their text edited under **EU Compliance → Settings → Checkout consent**.

= I sell to businesses (B2B). Can I exclude them from the right of withdrawal? =

The right of withdrawal protects consumers (natural persons acting outside their trade or profession), not business buyers, but the plugin never decides that for you. Enable **Consumer self-declaration** under **EU Compliance → Settings → Public withdrawal form** and the form shows a required checkbox where the buyer declares they purchased as a consumer; a business that cannot declare it self-excludes, and the declaration is stored with the request as proof. It is off by default. Use the `consumer_check="yes"` shortcode attribute to force it on a specific landing page, or the `ayudawp_euw_show_consumer_check` filter for custom logic (VIES validation, a customer-type field, etc.).

= Does the plugin include the Annex I.B model withdrawal form required by Directive 2011/83/EU? =

Yes, built from your shop name, address (from WooCommerce when available), contact email and an optional phone. It appears as a collapsible block below the public form, with a printable view. Providing it is a pre-contractual duty under Art. 6(1)(h) of Directive 2011/83/EU, which the online function does not replace.

= The withdrawal page cites Directive 2011/83/EU. Isn't the new law Directive 2023/2673? =

Both are right. Directive 2023/2673 is titled after financial services, but it also adds Article 11a to Directive 2011/83/EU, and that article obliges every online shop to offer the withdrawal function from 19 June 2026. The right itself, its 14 days and its exceptions still come from 2011/83/EU.

Financial services contracted at a distance follow their own rules, so the sample page gives them a separate section: delete it if you do not offer them. The plugin does not cover the pre-contractual information those contracts require.

= What is the receipt verification code in the customer email? =

It is a SHA-256 hash computed from the request data (post ID, customer name, email, order reference, scope, order date and submission timestamp). The customer keeps the email as a tamper-evident proof on a durable medium. If a dispute later arises, you can recompute the hash from the stored fields with the `ayudawp_euw_compute_receipt_hash()` helper and confirm the original submission was not altered.

= Can I choose who can manage withdrawal requests? =

Yes, from **EU Compliance → Settings → Permissions**: requests hold personal data, so you pick which roles besides the administrator may manage them. New installs start administrator-only; sites updated from an earlier version keep the roles that already had access until you untick them.

= Does it support HPOS (High-Performance Order Storage)? =

Yes. The plugin declares HPOS compatibility on load.

= Does the plugin work without WooCommerce? =

Yes. The form, shortcode, request log, emails, receipt hash, Annex I.B and GDPR tools run on their own, under a **Withdrawals** menu. With WooCommerce active you also get order validation, the My Account endpoint, the notice in order emails, the orders column, order notes and the Article 16 exclusions. Activating or deactivating WooCommerce later leaves the standalone features intact.

= Does it work with plugins that change the WooCommerce order number (Sequential Order Numbers, Custom Order Numbers, etc.)? =

Yes. The form accepts both the internal WooCommerce order ID and the displayed order number. The resolver checks a list of known meta keys: the standard `_order_number` and `_order_number_formatted` (WooCommerce Sequential Order Numbers, free and Pro), plus `_alg_wc_full_custom_order_number` and `_alg_wc_custom_order_number` (Tyche / WPFactory "Custom Order Numbers for WooCommerce"). Add other numbering plugins with the `ayudawp_euw_order_number_meta_keys` filter; for schemes computed on the fly (e.g. YITH Sequential Order Number, custom integrations), short-circuit the lookup with the `ayudawp_euw_pre_resolve_wc_order` filter.

= The form says it cannot match the email with the order number. Can I accept those requests anyway? =

Yes, with **Accept unmatched requests** under **EU Compliance → Settings → Eligible order statuses**. By default the form rejects a submission whose order number and email match no order. With the option on it is registered anyway and flagged as *Unverified*: the customer sees a notice inviting them to check the reference and can still confirm, and the request arrives highlighted in the notification email, in the list and in its detail screen, so you verify it against your records before deciding. Unverified requests are never linked to an order, and the acknowledgement is still sent. Off by default.

An order with no billing email, such as a phone order keyed in by hand, gives the form nothing to check against. Its requests are always registered as unverified, even with the option off, and the order is suggested to you only when the order date the customer gives matches it.

= The form says the request is no longer awaiting confirmation, right after the customer submitted it. Why? =

The declaration waits on the server, in a 15-minute single-use transient, between the form and the review screen. An object cache that drops transients (a misconfigured Redis or Memcached drop-in, or a plugin flushing them) loses it before the customer confirms. Disable the object cache for a moment and submit again: if it works, fix the cache configuration.

= Will the notice appear on every WooCommerce email? =

No. The notice goes into the customer email WooCommerce sends for each order status ticked under **EU Compliance → Settings → Eligible order statuses**: Processing and Completed by default, plus On hold or Refunded if you tick them. So does the manual invoice email, for orders in those statuses. Admin emails never receive it. Change the email list with the `ayudawp_euw_email_ids` filter and the status list with `ayudawp_euw_allowed_statuses`.

= Can I make the withdrawal notice in the emails more discreet? =

Yes. Return an empty array from the `ayudawp_euw_email_ids` filter to remove the notice block (heading, text and button) from every order email:

`add_filter( 'ayudawp_euw_email_ids', '__return_empty_array' );`

The My Account button, the public form and the footer link keep working, so the withdrawal function stays accessible as Article 11a requires. You can then add your own wording with WooCommerce's per-email **Additional content** field (**WooCommerce → Settings → Emails**). Point it at your withdrawal page, and add the `{order_number}` placeholder to keep the order pre-filled, for example `.../withdrawal/?order_id={order_number}`. Do keep some reference in the order confirmation: it is the contract confirmation on a durable medium, so the withdrawal information should stay reachable from it, just not necessarily as a prominent button.

= Does it work on multilingual sites (WPML, Polylang)? =

Yes. The withdrawal status is set once, on the product or category in the site original language, and applies to every translation: excluded-product notice, checkout consents and excluded items on the order all resolve back to the original. An explicit status on a translation still wins. Links to the withdrawal page follow the visitor language whenever a translation exists, and so does the guarantee notice.

The bundled `wpml-config.xml` turns the settings that hold customer-facing copy into translatable strings under **WPML → String Translation** or **Languages → Translations**. A setting shows up there once you write your own text in it: left empty, the text comes from the language pack of each locale and already follows the visitor.

The acknowledgement of receipt follows the language of the page the customer sent the form from, provided the language is part of the URL; the notification to the shop, the request title and the order note stay in the site default language. One limitation: the status-change emails (accepted, rejected, completed) are still composed in the site default language, with per-customer language on the roadmap. The notices injected into the WooCommerce order emails, withdrawal and guarantee alike, follow the order language.

= Does the plugin pass GDPR requirements? =

It asks for explicit acceptance of your privacy policy, stores the IP and user agent only for the legal traceability of the request, and plugs into the WordPress privacy tools. The **Privacy** section above lists the stored fields and those tools.

= What happens if the customer deletes their WordPress user account? =

Nothing: the log is keyed on the customer email, not on the user account. To remove their requests, use **Tools → Erase Personal Data** with that email, or delete the entries by hand if your retention policy requires it.

= Can I change the texts on the withdrawal form? =

Yes, under **EU Compliance → Settings → Public withdrawal form**:

* **Intro text**: the paragraph above the form. You can also hide it.
* **Withdrawal conditions note**: the reminder of deadlines and exceptions. It shows on the form, on the confirmation screen and in the acknowledgement email, the same in all three.

Left empty, each one uses the bundled text, translated for each visitor. The conditions note is your responsibility: it can offer better terms, such as a longer period, but it cannot reduce the customer's statutory rights.

= Can I customise the emails? =

Yes. From **EU Compliance → Settings → Withdrawal emails** you can set the sender ("From name" and "From address") for the plugin's emails and edit the body of the accepted, rejected and completed status emails; left empty, each text falls back to the bundled default. The admin notification can be tailored with the `ayudawp_euw_admin_email_lines` filter, and all strings remain translatable through the standard WordPress text-domain. The emails are sent in plain text; HTML templates that inherit the WooCommerce email theme are planned for a later release.

= Which hooks does the plugin expose for developers? =

22 filters and 4 actions. The withdrawal side: `ayudawp_euw_grace_days` (extra days on the deadline), `ayudawp_euw_allowed_statuses` (order statuses that get the button and the notice), `ayudawp_euw_show_withdrawal` (hide both for specific orders, or bring them back on an order that only holds excluded products), `ayudawp_euw_email_ids` (emails carrying the withdrawal notice), `ayudawp_euw_allow_unverified_order`, `ayudawp_euw_pre_resolve_wc_order` and `ayudawp_euw_resolve_wc_order` (short-circuit or audit the order resolver), `ayudawp_euw_order_number_meta_keys` (meta keys checked when matching a typed order number), `ayudawp_euw_admin_email_lines`, `ayudawp_euw_validation_result` (reject a submission, for a captcha), `ayudawp_euw_form_via_admin_post` (send the form through admin-post.php again), `ayudawp_euw_show_consumer_check` and `ayudawp_euw_legal_conditions_text` (the conditions note on the form, the confirmation screen and the acknowledgement email; an empty string hides it). The checkout consents: `ayudawp_euw_consent_hook`, `ayudawp_euw_consent_hook_priority`, `ayudawp_euw_consent_applies` and `ayudawp_euw_consent_is_required`. The guarantee notice: `ayudawp_euw_guarantee_email_ids`, `ayudawp_euw_guarantee_notice_html`, `ayudawp_euw_guarantee_image_id`, `ayudawp_euw_guarantee_fallback_lang` and `ayudawp_euw_guarantee_language_aliases`.

Actions: `ayudawp_euw_after_submission` (CPT ID, submission data), `ayudawp_euw_after_status_change` (CPT ID, new status, comment), `ayudawp_euw_after_form` (inside the form wrapper, after `</form>`) and `ayudawp_euw_form_before_submit` (before the submit button, for a captcha or an extra field).

`ayudawp_euw_skip_deadline_check` is kept for back-compat and has no effect: the deadline is advisory and gates nothing.

= Is this plugin enough to comply with EU Directive 2023/2673? =

It covers the functional requirements the directive imposes EU-wide from 19 June 2026: a discoverable digital withdrawal function, eligibility by order status with an advisory deadline flag, Article 16 exclusions with inheritance, durable proof through the SHA-256 receipt hash, the Annex I.B model form and the two checkout consents. It adds the operational tools the directive does not mandate but that make requests workable, and the harmonised guarantee notice that becomes mandatory in September.

Member States can layer national requirements on top: the two-step confirmation expected by the strictest of them is built in. **Compliance ultimately depends on your business model, catalogue and jurisdiction; the plugin provides the building blocks, not legal advice. Consult a consumer-law specialist for your case.**

= Is this plugin related to the EU Omnibus Directive? =

Partially. This plugin implements the right of withdrawal requirements of Directive 2011/83/EU on consumer rights, one of the directives amended by Directive (EU) 2019/2161 (the Omnibus Directive).

It does not cover other Omnibus Directive obligations, such as displaying the lowest price of the last 30 days in price reductions or verifying customer reviews. Those requirements need their own dedicated solutions.

== Screenshots ==

1. Public withdrawal form with all required fields.
2. Withdrawal log inside the WordPress admin.
3. Per-request detail screen with status management, including the captured checkout consents (text, accepted/declined, timestamp, IP, user agent).
4. WooCommerce My Account integration with per-order Withdraw button.
5. EU Withdrawal Settings Page (WooCommerce active), with the Checkout consent, Annex I.B model and Excluded products notice sections.
6. EU Withdrawal Settings Page (Standalone).
7. Per-product "Withdrawal status" dropdown in the WooCommerce product editor (General tab), with the inheritance notice when the value is pulled from the product's category.
8. Per-category "Withdrawal status" dropdown in Products → Categories edit screen.
9. Excluded products notice rendered on the public single product page (between price and add-to-cart button).
10. Mandatory digital-content consent (Art. 16(m)) shown at the WooCommerce checkout.
11. Optional service-start consent (Art. 14(4)(a)) shown at the WooCommerce checkout.
12. Annex I.B model withdrawal form rendered as a collapsible block below the public withdrawal form, with a printable view link.
13. Two-step confirmation screen (Article 11a(3)): read-only summary of the declaration with the dedicated "Confirm withdrawal" button, shown before the request is registered.
14. Export withdrawals page: filtered CSV export of the request log by status and date range for accounting and consumer-protection audits.

== Changelog ==

= 2.3.4 =
You can now edit the withdrawal conditions note shown on the form, the confirmation and the acknowledgement email. The note and the notice in order emails now say the 14 days run from the contract for services and digital content.

* Improved: The withdrawal conditions note can be edited under **EU Compliance → Settings → Public withdrawal form**. It shows on the form, on the confirmation screen and in the acknowledgement email, the same in all three, and left empty it keeps the bundled text, translated for each visitor. Developers can change it or hide it with the new `ayudawp_euw_legal_conditions_text` filter.
* Improved: On a new install the settings show one notice under the withdrawal page selector instead of two that said the same thing: review the sample text with your legal advisor, delete what does not apply and publish the page.
* Improved: In the shop notification of an unverified request, the heading no longer says the details could not be matched with an order right above a line that names the matching order.
* Fix: The bundled conditions note said that, for digital content, the 14 days run from the start of the download. For services and digital content they run from the day the contract was concluded; starting a download is what can end the right, when the customer agreed to it beforehand. The withdrawal notice in WooCommerce order emails, the sample withdrawal page created on new installs, the late-request warning in the shop notification, the deadline settings and the readme now say the same, where until now they only counted from delivery. If you changed the old sentence of the note with Loco Translate or a gettext filter, that change no longer applies: write your text in the new setting instead.
* Fix: Typing the ID of a WooCommerce refund in the form matched that refund as if it were an order, and the request was registered as one on an order without a billing email. It is now treated as an order that does not exist. The `ayudawp_euw_resolve_wc_order` filter only accepts a `WC_Order` from now on: anything else counts as no match.
* Fix: If another plugin filtered page content, the record of the sample page written on activation could be taken from the filtered content, and then the settings never said that the page still held the sample text.

= 2.3.3 =
New installs create the withdrawal page as a draft and hide links to an unpublished page. Requests on orders without a billing email are no longer linked to the order. Also warns about checkout consents that do not match your products.

* Improved: On a new install the withdrawal page is created as a draft, so you can review its sample text before customers see it. A dashboard notice stays until the page is published, and the activation notice and the page warning in the settings say why it is a draft.
* Improved: While the withdrawal page is not published, the plugin leaves out every link to it (the notice and button in WooCommerce emails, the withdrawal button for guests, the `[ayudawp_withdrawal_link]` shortcode, the link in the excluded-product notice and the printable model form) instead of pointing customers to a page they cannot open. A guest is given the shop contact email instead. This also applies to sites whose withdrawal page was already unpublished. On multilingual sites, a translation that is not published falls back to the published original.
* Improved: The sample withdrawal page now cites Directive 2011/83/EU and its Article 11a as the source of the right, instead of Directive 2023/2673 alone, and has a separate section for financial services contracted at a distance. Existing pages are not changed.
* Improved: The checkout consent settings warn when a consent is switched off while some products or categories still use the withdrawal status that needs it, and note when a consent that is on is used by none.
* Improved: When the Annex I.B contact email field is empty, the settings name the address customers are being shown instead (the first notification address, or the site admin email) so you can decide whether to fill it in.
* Improved: After sending a request the customer sees what happens next: the acknowledgement to keep, the emails that will follow, the link to follow it in their account when they have one, and the shop contact email.
* Improved: Tested up to WooCommerce 11.2.
* Fix: A request on an order with no billing email, such as a phone order keyed in by hand, was linked to that order just by typing its number, and the form could tell whoever typed it whether that order already had a request open. It is now registered as unverified and not linked, even with "Accept unmatched requests" off, and the order is suggested to you only when the order date the customer gives matches it, give or take a day. Such a request no longer shows in the Withdrawal column of the order nor holds back a second one: you resolve it from its detail screen.
* Fix: An order number containing "#" or "&" broke the pre-filled links to the withdrawal form, in the customer account and in the order emails.
* Fix: On sites in a right-to-left language (Arabic, Hebrew, Persian) the page with the withdrawal form could be scrolled sideways for about 10,000 pixels, pushed by the hidden anti-spam field, and the buttons and notice borders sat on the wrong side. The front-end styles now use logical properties.
* Fix: The checkout consent settings described an older way of choosing the products each consent applies to, and named a past version of the plugin.

= 2.3.2 =
Orders that only hold products excluded from the right of withdrawal no longer show the withdrawal button or the notice in their emails, and mixed orders name the excluded ones. Also fixes the withdrawal button that sent guests to the login screen.

* Improved: An order that only holds products excluded from the right of withdrawal (Article 16) no longer shows the withdrawal button in the customer account nor the notice in its WooCommerce emails. Until now both appeared on every eligible order, so a customer who had only bought excluded products was told they had 14 days to withdraw. The withdrawal form keeps accepting a request for any order and flags it for your review, as before. On by default: untick "Orders with excluded products" under "Eligible order statuses" if you grant the withdrawal on excluded products too.
* Improved: On an order that mixes excluded and regular products, the notice in the WooCommerce emails now names the products the right of withdrawal does not apply to.
* Improved: The `ayudawp_euw_show_withdrawal` filter can now bring the button and the notice back on an order that only holds excluded products. It still cannot offer them on an order in a status you left out, or past the deadline in strict mode.
* Fix: On the order received page and on the order tracking page, the "Withdraw from order" button sent a visitor without a session to the My Account login screen, a dead end for anyone who bought as a guest. It now opens the withdrawal form with the order number filled in, the same link the order emails carry. If no withdrawal page is set, the visitor is given the contact email of the shop instead.
* Fix: In plain-text order emails an apostrophe in the withdrawal notice or in the legal guarantee notice was dropped, so a translation such as "l'ordine" arrived as "lordine".
* Fix: Saving the settings page while WooCommerce was deactivated switched off or emptied every setting whose field is only shown with WooCommerce active: the eligible order statuses, the status in the customer account, accepting unmatched requests, both checkout consents and their texts, the excluded products notice and its texts, and the order note and PDF attachment of the legal guarantee notice. They now keep their values. Version 2.3.1 had only protected the list of emails that carry the guarantee notice.

= 2.3.1 =
The withdrawal form now posts to its own page, so plugins that close wp-admin to visitors or customers can no longer swallow it. Also: one open request per whole order, the acknowledgement in the language of the customer, and fixes for the guarantee notice and the CSV export.

* Improved: On multilingual sites (WPML, Polylang) the acknowledgement of receipt is now written in the language of the page the customer sent the form from, as long as the language is part of the URL, which is the usual setup in both. The notification to the shop, the title of the request in the log and the note on the order stay in the default language of the site. Until now all four came out in the default language.
* Improved: A request is no longer registered again while one for the whole order is pending or accepted. The customer is told it is already being handled and that replying to the acknowledgement of receipt is the way to add to it. A partial request never holds a later one back, so withdrawing from some products now and from others later works as before, and a rejected request can still be contested.
* Improved: The withdrawal notice in the WooCommerce emails now follows the statuses ticked under "Eligible order statuses". Ticking On hold used to bring the button to My Account but not the notice to the on-hold email, which is the first one a customer paying by bank transfer receives. If you have On hold or Refunded ticked, those emails carry the notice from this version on.
* Improved: The dashboard notice inviting you to switch the legal guarantee notice on no longer shows on shops with no physical products, which the notice does not apply to. The settings section says so too, instead of leaving you wondering why nothing shows at the checkout.
* Improved: Two filters for developers. `ayudawp_euw_show_withdrawal` keeps the withdrawal button and the email notice off specific orders, for example a course that starts once the withdrawal period is over. `ayudawp_euw_form_via_admin_post` sends the form through admin-post.php again, in case something in front of a site refuses a POST to a public page.
* Fix: The withdrawal form did not get past its first step on sites where another plugin keeps visitors or customers out of wp-admin (reported with JetEngine's Profile Builder and with WP Cerber). It was sent to admin-post.php, was intercepted there, and the customer landed on the front page with nothing registered. The form now posts to its own page, out of reach of anything guarding wp-admin. Pages cached with the old form keep working. If a firewall or rate-limit rule of yours targeted admin-post.php for this form, point it at the page that holds the form; code hooked to the admin_post_ayudawp_euw_review or admin_post_ayudawp_euw_confirm actions no longer runs, and `ayudawp_euw_validation_result` is its place.
* Fix: Dismissing the dashboard notice about the legal guarantee took you to the settings page. It now leaves you on the screen you were on.
* Fix: The reminder under the withdrawal page selector, the one saying the page still holds the sample text, was laid out in three columns with its link stranded in the middle.
* Fix: With PHP 8.4 or later and errors displayed on screen, the CSV export of withdrawal requests carried a deprecation notice inside the file. A field with a backslash right before a quote is now also written the way spreadsheets read it.
* Fix: Saving the settings page while WooCommerce was deactivated emptied the list of order emails that carry the legal guarantee notice.

= 2.3.0 =
New module for the EU harmonised legal guarantee notice, mandatory from 27 September 2026: the official notice in 24 languages at the checkout and in your order emails, with the PDF attached. It arrives switched off, with a notice in your dashboard to turn it on.

* New: The EU harmonised notice on the legal guarantee of conformity, which Article 22a of Directive 2011/83/EU (added by Directive (EU) 2024/825) makes mandatory from 27 September 2026, with the design fixed by Implementing Regulation (EU) 2025/1960. The plugin bundles the official file in the 24 EU languages and shows it unedited, as the regulation requires: above the place-order button of both the classic and the block checkout, and in the customer order emails with the official PDF attached, which is what reaches the customer when their mail client blocks remote images. The caption links to the Your Europe guarantees page in their language, the destination of the QR code printed on the notice. It is shown only when the cart or the order holds goods, because the notice does not apply to services or digital content.
* New: Three display modes for the notice, plus the option of placing it yourself. The whole notice by default, which is the reading nobody disputes; a native disclosure; and a native popover that opens it large over the page, for narrow checkout columns such as the Storefront order review, where a full-width notice comes out too small to read. All three are built with plain HTML, no JavaScript, so they also work inside the block checkout.
* New: The Spanish three-year note. The official notice says "minimum two years" because that is the EU floor and it cannot be edited, while Spain raised the legal guarantee on new goods to three years from delivery (Art. 120.1 TRLGDCU). The plugin prints that difference next to the notice, never inside it, with a link to your terms and conditions page and, if you want, to the exact section of it. Automatic when your shop base country is Spain, and forceable either way.
* New: A "Legal guarantee" page of your own, with the [ayudawp_guarantee_notice] shortcode for the notice in full and [ayudawp_guarantee_link] for a permanent link to it from your footer, the same pair the withdrawal page already had. Plus a private order note recording the language and the display mode of the notice each buyer was shown, which is what lets you answer an inspection about an order placed months ago.
* New: Five developer filters for the notice: the emails that carry it, its markup, the media-library file served instead of the bundled one, the fallback language and the map of languages with no official file. The settings screen also lets you replace the bundled file per language from your media library.
* Improved: On a site updating from an earlier version the module arrives switched off, and says so in the dashboard with a button that switches it on. It changes what your customers see at the checkout, which is not a decision to take on someone else's behalf while they are not looking. New installs start with it on.
* Improved: The plugin is now around 5 MB, up from 560 KB, because the official notice ships bundled in 24 languages as an image and as a PDF. Nothing is downloaded at runtime and nothing leaves your server: the regulation does not allow altering the files, so they travel as the Commission publishes them.
* Improved: The admin menu is now called EU Compliance, with a new icon. It used to say Withdrawals, which named half of what the plugin does now that the legal guarantee notice hangs from the same menu. Inside, everything keeps its name: Withdrawals for the request list, Settings and Export withdrawals.
* Improved: Plugin renamed as EU Withdrawal and Legal Guarantee Compliance. It covered one directive and now covers two, and the old name said nothing about the half that becomes mandatory in September. Nothing moves: same slug, same folder, same settings and same translations, only the name you see in the plugins list and in the directory.
* Improved: Tested up to WooCommerce 11.1.

For older changelog entries, please check the [changelog.txt](https://plugins.svn.wordpress.org/eu-withdrawal-compliance/trunk/changelog.txt) file

== Upgrade Notice ==

= 2.3.4 =
You can now edit the withdrawal conditions note shown on the form, the confirmation and the acknowledgement email. The note and the notice in order emails now say the 14 days run from the contract for services and digital content.

== Support ==

Need private support or custom development?

Do you need one-on-one help, priority troubleshooting, or a custom feature, integration, or tweak built specifically for your site? I offer private support and custom development. Just [contact me](mailto:eu-withdrawal-compliance@ayudawp.com) and tell me what you need.

Need help or have suggestions?

* [Official website](https://servicios.ayudawp.com)
* [WordPress support forum](https://wordpress.org/support/plugin/eu-withdrawal-compliance/)
* [YouTube channel](https://www.youtube.com/AyudaWordPressES)
* [Documentation and tutorials](https://ayudawp.com)

Love the plugin? Please leave us a 5-star review and help spread the word!

== About AyudaWP.com ==

We are specialists in WordPress security, SEO, AI and performance optimization plugins. We create tools that solve real problems for WordPress site owners while maintaining the highest coding standards and accessibility requirements.