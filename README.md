# Ropstam Solutions: Senior CMS Developer Task

Technical assessment with two parts in one repository:

1. **WordPress:** a lightweight plugin with a Services post type, a dynamic block and a live add-on price calculator.
2. **Shopify:** an Online Store 2.0 bundle builder section.

## Quick links

| Demo | Link | Notes |
|---|---|---|
| WordPress | <https://tan-cassowary-439520.hostingersite.com/> | Services Calculator block |
| WordPress shortcode | <https://tan-cassowary-439520.hostingersite.com/shortcode-demo/> | Same output through `[sac_services_calculator]` |
| Shopify | <https://ropstam-bundle-demo.myshopify.com/> | Storefront password: `ropstam2026`. Bundle builder on the home page |

## Key technical decisions

**Both parts**

- **Vanilla JS, zero dependencies.** Both components only need events, selectors and text updates, which browsers handle natively. No jQuery and no build step.
- **Prices in integer cents.** Avoids floating point errors such as `0.1 + 0.2`. Shopify already provides cents; the WordPress script converts before adding.
- **Native form controls.** Checkboxes and radios keep keyboard and screen reader support for free. CSS only changes how they look.
- **Assets only where used.** Each platform loads the CSS and JS only on pages that render the component.

**WordPress**

- **Dynamic block instead of a static one.** "Most recently updated" changes over time, so the markup is rendered on every request instead of being saved in post content.
- **`block.json` with registered handles.** One definition shared by PHP and the editor, and `viewScript` loads only when the block is on the page. The editor script uses WordPress globals, so there is no build step.
- **`register_post_meta()` plus a meta box.** The meta box is only the UI. Registration provides types, defaults, central sanitization and REST access for every save path.
- **One render function for the block and the shortcode.** No duplicated markup.

**Shopify**

- **Section blocks for the groups (`max_blocks: 3`).** Merchants can add, remove and reorder groups, which fixed settings can't offer.
- **One `/cart/add.js` request for the whole bundle.** Tested: when one item is sold out, Shopify rejects the whole request, so a partial bundle never reaches the cart. Three separate requests could leave half a bundle behind.
- **Shopify standard storefront events instead of theme-specific code.** The section dispatches `shopify:cart:lines-update`, so Horizon's cart icon and drawer refresh without depending on theme internals.
- **Self-contained section.** Customer-facing text lives in the schema `locales` object and only three files are needed, so the section can be copied into another Online Store 2.0 theme.
- **Only task files in Git.** The Horizon theme is pulled with Shopify CLI and ignored, so the repository and every commit show only the work written for this task.

## Repository structure

```
ropstam-cms-task/
├── wordpress/
│   └── service-addon-calculator/   WordPress plugin
└── shopify/                        Shopify section files (the rest of the theme is pulled with Shopify CLI)
```

---

## Part 1: WordPress, Service Add-on Calculator

**Live demo:** <https://tan-cassowary-439520.hostingersite.com/>
**Shortcode demo:** <https://tan-cassowary-439520.hostingersite.com/shortcode-demo/>

### Features

- `Services` custom post type (`sac_service`, archive at `/services/`) with `base_price` and `delivery_time_days` meta fields.
- **Services Calculator** dynamic block that lists the 3 most recently updated services.
- Optional add-ons on every card (e.g. Priority Turnaround +$50) shown as accessible toggle switches. The estimated price updates live with vanilla JavaScript.
- `[sac_services_calculator]` shortcode that renders the same output for the Classic Editor, page builders and widgets.

### Requirements

- WordPress 6.3 or later (tested on WordPress 7.1.3)
- PHP 7.4 or later (tested on PHP 8.3, also runs notice-free on PHP 8.5)
- No build step. No Composer or npm dependencies.

### Installation

1. Copy `wordpress/service-addon-calculator` into `wp-content/plugins/`, or zip the folder and upload it from **Plugins > Add New > Upload Plugin**.
2. Activate **Service Add-on Calculator**.
3. Rewrite rules are flushed on activation. If `/services/` returns a 404, visit **Settings > Permalinks** and click Save.

### Usage

1. Go to **Services > Add New**. Add a title, an excerpt and fill in the **Service Details** box (base price in USD, delivery time in days).
2. Edit any page, open the block inserter and add **Services Calculator** (Widgets category). The block defaults to wide alignment.
3. Alternatively, add the shortcode `[sac_services_calculator]`.

### How each requirement is met

| Brief | Implementation |
|---|---|
| **a.** Services CPT with `base_price` and `delivery_time_days` | `includes/post-type.php` registers the CPT on `init`. `includes/meta.php` registers both fields with `register_post_meta()` (typed, sanitized, `auth_callback`, `show_in_rest`). `includes/meta-box.php` adds the admin UI with nonce, autosave and capability checks. |
| **b.** Block fetching the 3 most recently updated services | Dynamic block defined in `blocks/services-calculator/block.json`. Data comes from `sac_get_recent_services()`: `WP_Query` with `orderby => modified`, `posts_per_page => 3`, `no_found_rows => true`. |
| **c.** Vanilla JS calculator with live price | `assets/js/calculator.js`: one delegated `change` listener per calculator, prices calculated in integer cents, formatted with `Intl.NumberFormat`, result announced through `aria-live`. |
| **d.** Prepared queries / `WP_Query`, escaping, `wp_enqueue_script` with dependencies | No raw SQL anywhere. Every dynamic value is escaped with `esc_html`, `esc_attr` or `esc_url`. Assets are registered with `wp_register_script` / `wp_register_style` (dependencies declared) and only enqueued where the calculator is used: by `block.json` handles for the block, and by `wp_enqueue_script` / `wp_enqueue_style` for the shortcode. |

### Plugin structure

```
service-addon-calculator/
├── service-addon-calculator.php     Bootstrap: header, constants, includes, activation hooks
├── includes/
│   ├── post-type.php                Services CPT
│   ├── meta.php                     Meta registration and price sanitization
│   ├── meta-box.php                 Admin meta box (loaded in admin only)
│   ├── assets.php                   Script and style registration
│   ├── services-calculator.php      Query, add-ons and shared markup
│   ├── block.php                    Block registration from block.json
│   └── shortcode.php                Shortcode fallback
├── blocks/services-calculator/
│   ├── block.json                   Block metadata and asset handles
│   └── render.php                   Server-side render
└── assets/
    ├── js/block-editor.js           Editor registration (no build step)
    ├── js/calculator.js             Front-end calculator
    └── css/calculator.css           Front-end and editor styles
```

### Extending the add-ons

Add-ons can be changed globally or per service with the `sac_service_addons` filter. Returned items are sanitized after the filter runs.

```php
add_filter( 'sac_service_addons', function ( $addons, $service_id ) {
	$addons[] = array(
		'id'    => 'seo-setup',
		'label' => 'SEO Setup',
		'price' => 99,
	);

	return $addons;
}, 10, 2 );
```

### Security

- Direct file access blocked with an `ABSPATH` check in every PHP file.
- Meta box saves verify a nonce, skip autosaves and check `edit_post` capability.
- Sanitization is registered centrally with `register_post_meta()`, so it applies to every save path (meta box, REST API, code).
- Prices are clamped to non-negative values. Add-on data is sanitized after the filter, since third-party code may return unexpected values.
- All output escaped late, at the point of output.

### Accessibility

- Semantic markup: `section`, `article`, `header`, `time`, `dl`, `fieldset` / `legend`, `output`.
- Add-ons are native checkboxes with `role="switch"`, so keyboard support (Tab, Space) and screen reader states come from the browser. Each whole row is a label.
- Live total announced with `aria-live="polite"`.
- Visible `:focus-visible` styles, 44px minimum touch targets, `prefers-reduced-motion` and `forced-colors` support.
- Unique IDs with `wp_unique_id()`, so multiple instances on one page stay valid.

### Performance

- Assets load only on pages that contain the block or shortcode. The calculator script has zero dependencies and loads with `defer` in the footer.
- `no_found_rows` and `update_post_term_cache => false` skip unneeded queries. Meta for all three services is primed in one query.
- The loop iterates `$query->posts` directly, leaving the global `$post` untouched.
- Only the total's text is updated on change, and only when the value differs.
- Cache-busting asset versions based on file modification time.

### Edge cases handled

- No published services: friendly empty state.
- Drafts and private services are never shown.
- Base price of 0: shows "On request" and hides the calculator.
- Delivery of 0: shows "To be confirmed". Correct singular and plural ("1 day", "5 days").
- Decimal prices calculated without floating point errors.
- Browsers that restore checkbox state on reload: totals are re-synced on load.
- Without JavaScript, the base price is still rendered server-side.

### Assumptions

- Prices are in USD, formatted as en-US.
- Add-ons are shared across services by default and customizable through the filter. There is no admin UI for them.
- The calculator shows an estimate only. In a real checkout the total would be recalculated on the server, never trusted from the client.
- Meta keys use the exact names from the brief (`base_price`, `delivery_time_days`) without a prefix.

### Possible improvements

- Admin UI to manage add-ons per service.
- Block settings (number of services, heading) through `InspectorControls`.
- Transient caching of the query, cleared on `save_post_sac_service`.
- PHPUnit tests and PHPCS (WordPress Coding Standards) in CI.

### Testing performed

- Functional: ordering by last update, draft visibility, empty state, zero price, decimal and large totals.
- Security: XSS payload in a title rendered as text, invalid and negative input, nonce tampering, direct file access.
- Accessibility: keyboard-only navigation, Windows Narrator, 200% zoom, Lighthouse.
- Responsive: 320px to 1440px. Chrome, Firefox and Edge.
- Query Monitor: no PHP notices, assets absent on pages without the calculator.

---

## Part 2: Shopify, Bundle Builder Section

**Live demo:** <https://ropstam-bundle-demo.myshopify.com/> (storefront password: `ropstam2026`)
The section is on the home page, below the hero.

### Features

- Online Store 2.0 section `bundle-builder.liquid`, built and tested with Shopify CLI on the Horizon theme.
- Merchants add up to 3 product groups. Each group uses a collection or a hand-picked product list.
- Customers pick one product (and variant) from each group. The summary and bundle total update live.
- All items are added in one `/cart/add.js` request, linked with line item properties.
- Sold-out products and variants are disabled. Loading, success and error states work without a page reload.
- The theme's cart icon and cart drawer refresh through Shopify's standard `shopify:cart:lines-update` event.

### Files

Only the files written for this task are tracked. The rest of the Horizon theme is pulled locally with Shopify CLI and ignored by `shopify/.gitignore`.

```
shopify/
├── .gitignore                       Tracks only the files below
├── sections/bundle-builder.liquid   Markup, schema and section locales
└── assets/
    ├── bundle-builder.js            Custom element: selection, total, Ajax cart
    └── bundle-builder.css           Section styles
```

### Requirements

- Any Online Store 2.0 theme (tested on Horizon)
- Shopify CLI for local development (tested with 4.9.3, Node.js 24)
- No npm packages and no build step

### Installation

**With Shopify CLI**

```
shopify theme pull --store <store>.myshopify.com
# copy the three files into the same folders, then:
shopify theme push --store <store>.myshopify.com --only sections/bundle-builder.liquid --only assets/bundle-builder.js --only assets/bundle-builder.css
```

**Without CLI:** in **Online Store > Themes > Edit code**, create the three files with the contents from this repository.

**Local development**

```
cd shopify
shopify theme dev --store <store>.myshopify.com --store-password <password>
```

### Setup in the theme editor

1. **Customize** the theme, open a template (for example Home page) and choose **Add section > Bundle builder**.
2. The preset adds 3 product groups. For each one, set a **Step title** and choose a **Collection**, or pick specific products (these take priority over the collection).
3. Optional settings: heading, description, bundle name (shown on cart lines), button label, products per step, padding.

### How each requirement is met

| Brief | Implementation |
|---|---|
| **a.** Section with schema settings for up to 3 collections or product lists | Groups are section blocks with `max_blocks: 3`, each with a `collection` and a `product_list` setting. Merchants can reorder them in the editor. A preset adds 3 groups. |
| **b.** Customers pick 1 item from each group | Each group is a `fieldset` of native radio inputs, so only one item per group can be selected and arrow-key navigation works without JavaScript. Products with variants get a variant `select`. |
| **c.** Live bundle price, all 3 items added with `/cart/add.js` and linked by line item properties | The custom element sums the integer cent prices from Liquid and formats them with `Intl.NumberFormat` in the store's currency and locale. All items go in one `items` array, each with a visible `Bundle` property and hidden `_bundle_id` and `_bundle_item` properties. |
| **d.** Stock validation and loading states without reload, vanilla JS only | `product.available` and `variant.available` disable sold-out options in Liquid. The button shows a spinner through `aria-busy`, the choices are frozen with `inert`, and Shopify's own message is shown when the cart declines the request. No libraries. |

### Platform conventions

- Liquid filters: `image_url` with `image_tag` (responsive `srcset`, width and height, lazy loading), `placeholder_svg_tag`, `money`, `t` and `escape`.
- `routes.cart_add_url` and `routes.cart_url` instead of hardcoded URLs, so Markets and language prefixes keep working.
- `block.shopify_attributes` for block selection in the editor, `request.design_mode` for editor-only hints.
- Customer-facing text lives in the schema `locales` object and is read with `'sections.bundle-builder.<key>' | t`, the documented format for self-contained sections. Theme Check's `TranslationKeyExists` does not resolve section-level locales, so that single check is disabled in this file with a comment explaining why. No other Theme Check offences.
- Cart changes are announced with Shopify's standard storefront events (`shopify:cart:lines-update`, `shopify:cart:error`) instead of theme-specific code. Horizon's cart icon and drawer listen for them, and so can apps.

### Edge cases handled

- Sold-out products show a badge, a greyed image and a disabled radio. Sold-out variants are disabled in the dropdown.
- Products not available on the Online Store (drafts, or stock held by an inactive fulfilment service) are left out or shown as sold out by Shopify itself.
- Products without an image use Shopify's placeholder SVG.
- Groups without products are skipped on the storefront and flagged in the theme editor. With no groups, the section renders nothing and loads no assets.
- An item that sells out after the page loaded returns a 422. Shopify's message is shown and the cart count re-syncs. Shopify rejected the whole request, so a partial bundle was never added.
- Network failure or a request slower than 15 seconds shows a friendly error, the button works again and `shopify:cart:error` is dispatched.
- Adding the same bundle twice creates a new bundle id, so the bundles stay as separate cart lines.
- Form state restored by the browser on reload is re-synced on load.
- If the standard events library can't load from Shopify's CDN, adding to cart still works. Only the automatic cart icon refresh is skipped.

### Accessibility

- Semantic `section`, `fieldset` and `legend`, `ol` and `output` elements with visible text labels.
- Native radios are hidden visually only, so keyboard use (Tab, arrows, Space, Enter) and screen readers work. The focus ring is drawn on the tile with `:focus-visible`.
- Hidden labels for variant selects, and decorative `alt=""` on images that sit next to the product title.
- `aria-live` total, `role="status"` for success and `role="alert"` for errors. The button keeps focus while busy.
- Text keeps full contrast on sold-out items, 44px touch targets, `prefers-reduced-motion` and `forced-colors` support.

### Performance

- CSS and JS load only on pages that use the section, with `defer`.
- Zero dependencies. The standard events module is imported lazily on the first add to cart.
- One delegated listener per section, removed with an `AbortController` when the editor re-renders the section.
- Text is only written when it changes. Selected and focus states are pure CSS (`:checked`, `:has()`).

### Assumptions and limitations

- No bundle discount is applied. A real discount needs an automatic discount or Shopify Functions; showing one in the section without that would not match the cart.
- One item per group and quantity 1 per item.
- Bundle lines are linked by properties, not merged into a single cart line, which would need a bundles app or Shopify Functions.
- Up to 12 products per group (merchant setting).
- Built and tested on Horizon. Styles inherit the theme's fonts and colours, so the section should work in other OS 2.0 themes, but the automatic cart drawer refresh depends on the theme supporting standard storefront events.

### Testing performed

- Selection, variant change, live total, add to cart, and cart icon and drawer refresh without a reload.
- Line item properties in the cart, and repeated bundles as separate lines.
- Sold-out product and variant, an item sold out after the page loaded (422, nothing added), and an offline request.
- Keyboard-only flow, mobile and desktop layouts, and section re-render in the theme editor.
- No standard events payload warnings from `shopify theme dev`.

---

## Commit convention

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) with a scope per platform, e.g. `feat(wp): ...`, `fix(shopify): ...`, `docs: ...`.

## Author

Muhammad Salman Atique · salmanatique36@gmail.com
