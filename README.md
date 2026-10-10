# Ropstam Solutions: Senior CMS Developer Task

Technical assessment with two parts in one repository:

1. **WordPress:** a lightweight plugin with a Services post type, a dynamic block and a live add-on price calculator.
2. **Shopify:** an Online Store 2.0 bundle builder section.

```
ropstam-cms-task/
├── wordpress/
│   └── service-addon-calculator/   WordPress plugin
└── shopify/                        Shopify theme section
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

- WordPress 6.3 or later (tested on WordPress <7.1.3>)
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

_In progress._

---

## Commit convention

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) with a scope per platform, e.g. `feat(wp): ...`, `fix(shopify): ...`, `docs: ...`.

## Author

<Muhammad Salman Atique> · <salmanatique36@gmail.com>