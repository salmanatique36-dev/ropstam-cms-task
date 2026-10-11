/**
 * Bundle Builder
 *
 * Powers sections/bundle-builder.liquid:
 * - keeps the summary, total and button in sync with the selected options
 * - adds every selected item to the cart in one /cart/add.js request,
 *   linked as a bundle with line item properties
 * - announces the change with Shopify's standard `shopify:cart:lines-update`
 *   event, so the theme's cart icon and drawer (and any app) refresh
 *   without a page reload
 *
 * Vanilla JS, no dependencies. As a custom element it initialises itself
 * whenever the theme editor re-renders the section.
 */
(() => {
  'use strict';

  const STANDARD_EVENTS_URL = 'https://cdn.shopify.com/storefront/standard-events.js';
  const REQUEST_TIMEOUT_MS = 15000;
  // Scoped to radios: a selected <option> inside a variant <select> also matches :checked.
  const CHECKED_RADIO = 'input[type="radio"]:checked';

  /** @type {Promise<any> | undefined} */
  let standardEventsPromise;

  /**
   * Load Shopify's standard events library once, only when it is first needed.
   * Resolves to null if it can't load, so adding to cart still works without it.
   *
   * @return {Promise<any>}
   */
  function loadStandardEvents() {
    if (!standardEventsPromise) {
      standardEventsPromise = import(STANDARD_EVENTS_URL).catch(() => null);
    }

    return standardEventsPromise;
  }

  /**
   * Short unique id that links the bundle's cart lines together.
   *
   * @return {string}
   */
  function createBundleId() {
    const source = window.crypto?.randomUUID?.() ?? `${Date.now()}${Math.random()}`;

    return source.replace(/[^a-z0-9]/gi, '').slice(-8).toUpperCase();
  }

  /**
   * @param {string | undefined} locale
   * @param {string | undefined} currency
   * @return {Intl.NumberFormat}
   */
  function createMoneyFormatter(locale, currency) {
    try {
      return new Intl.NumberFormat(locale, { style: 'currency', currency });
    } catch (error) {
      return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
    }
  }

  /**
   * Write text only when it changed, to avoid needless DOM updates.
   *
   * @param {Element | null | undefined} element
   * @param {string} text
   */
  function setText(element, text) {
    if (element && element.textContent !== text) {
      element.textContent = text;
    }
  }

  class BundleBuilder extends HTMLElement {
    connectedCallback() {
      this.form = this.querySelector('[data-bundle-form]');
      this.groups = Array.from(this.querySelectorAll('[data-bundle-group]'));

      // Nothing to wire up while the section has no product groups (theme editor).
      if (!this.form || this.groups.length === 0) return;

      this.steps = this.querySelector('.bundle-builder__steps');
      this.picks = Array.from(this.querySelectorAll('[data-bundle-pick]'));
      this.total = this.querySelector('[data-bundle-total]');
      this.submitButton = this.querySelector('[data-bundle-submit]');
      this.submitLabel = this.querySelector('[data-submit-label]');
      this.hint = this.querySelector('[data-bundle-hint]');
      this.successMessage = this.querySelector('[data-bundle-success]');
      this.errorMessage = this.querySelector('[data-bundle-error]');
      this.defaultLabel = this.submitLabel?.textContent ?? '';
      this.formatter = createMoneyFormatter(this.dataset.locale, this.dataset.currency);
      this.isBusy = false;

      // One controller removes every listener when the element leaves the page.
      this.abortController = new AbortController();
      const { signal } = this.abortController;

      this.form.addEventListener('change', this.handleChange, { signal });
      this.form.addEventListener('submit', this.handleSubmit, { signal });

      // Browsers can restore form state on reload, so sync everything once.
      this.querySelectorAll('[data-variant-select]').forEach((select) => {
        this.applyVariant(/** @type {HTMLSelectElement} */ (select), false);
      });
      this.update();
    }

    disconnectedCallback() {
      this.abortController?.abort();
    }

    /** @param {Event} event */
    handleChange = (event) => {
      const target = /** @type {HTMLElement} */ (event.target);

      if (target.matches('[data-variant-select]')) {
        this.applyVariant(/** @type {HTMLSelectElement} */ (target), true);
      }

      this.clearMessages();
      this.update();
    };

    /**
     * Copy the chosen variant onto its product's radio and refresh the card price.
     *
     * @param {HTMLSelectElement} select
     * @param {boolean} selectProduct Also select the product (when the customer picked a variant).
     */
    applyVariant(select, selectProduct) {
      const option = select.closest('[data-bundle-option]');
      const radio = /** @type {HTMLInputElement | null} */ (option?.querySelector('input[type="radio"]'));
      const variant = select.selectedOptions[0];

      if (!option || !radio || !variant || variant.disabled) return;

      radio.value = variant.value;
      radio.dataset.price = variant.dataset.price;
      radio.dataset.compareAt = variant.dataset.compareAt;

      if (selectProduct) radio.checked = true;

      this.renderOptionPrice(option, Number(variant.dataset.price), Number(variant.dataset.compareAt));
    }

    /**
     * @param {Element} option
     * @param {number} price Price in cents.
     * @param {number} compareAt Compare-at price in cents (0 when not on sale).
     */
    renderOptionPrice(option, price, compareAt) {
      setText(option.querySelector('[data-option-price]'), this.formatMoney(price));

      const compare = /** @type {HTMLElement | null} */ (option.querySelector('[data-option-compare]'));
      if (!compare) return;

      const isOnSale = compareAt > price;
      compare.hidden = !isOnSale;
      if (isOnSale) setText(compare, this.formatMoney(compareAt));
    }

    /** Recalculate the summary, total and button state from the current selection. */
    update() {
      let totalCents = 0;
      let selectedCount = 0;

      this.groups.forEach((group, index) => {
        const radio = /** @type {HTMLInputElement | null} */ (group.querySelector(CHECKED_RADIO));
        const pick = this.picks[index];

        if (radio) {
          // Liquid prices are integers in cents, so the sum has no floating point drift.
          totalCents += Number(radio.dataset.price) || 0;
          selectedCount += 1;
        }

        setText(pick, radio ? radio.dataset.title ?? '' : this.dataset.textNotSelected ?? '');
        pick?.closest('li')?.classList.toggle('is-selected', Boolean(radio));
      });

      const isComplete = selectedCount === this.groups.length;

      setText(this.total, this.formatMoney(totalCents));
      if (this.hint) this.hint.hidden = isComplete;
      if (this.submitButton && !this.isBusy) this.submitButton.disabled = !isComplete;
    }

    /** @return {HTMLInputElement[]} The checked radio of each group, in order. */
    getSelectedRadios() {
      return this.groups
        .map((group) => /** @type {HTMLInputElement | null} */ (group.querySelector(CHECKED_RADIO)))
        .filter((radio) => radio !== null);
    }

    /** @param {SubmitEvent} event */
    handleSubmit = async (event) => {
      event.preventDefault();
      if (this.isBusy) return;

      const radios = this.getSelectedRadios();
      if (radios.length !== this.groups.length) return;

      const bundleId = createBundleId();
      const bundleLabel = `${this.dataset.bundleName || 'Bundle'} #${bundleId}`;
      const items = radios.map((radio, index) => ({
        id: Number(radio.value),
        quantity: 1,
        properties: {
          // Visible in the cart and at checkout, so the customer sees the items belong together.
          Bundle: bundleLabel,
          // Keys starting with "_" are hidden from customers but kept on the order.
          _bundle_id: bundleId,
          _bundle_item: `${index + 1} of ${radios.length}`,
        },
      }));

      this.clearMessages();
      this.setBusy(true);

      // Announce the add before the request, as the standard expects, so the
      // theme can show its own loading state. Skipped if the library failed to load.
      const events = await loadStandardEvents();
      const deferred = events?.CartLinesUpdateEvent.createPromise();

      if (events && deferred) {
        // Listeners attach their own handlers; this only prevents an unhandled rejection warning.
        deferred.promise.catch(() => {});

        this.dispatchEvent(
          new events.CartLinesUpdateEvent({
            action: 'add',
            context: 'product',
            lines: items.map((item) => ({ merchandiseId: String(item.id), quantity: item.quantity })),
            promise: deferred.promise,
          })
        );
      }

      /** @type {Response} */
      let response;
      /** @type {any} */
      let result;

      try {
        response = await fetch(`${this.dataset.cartAddUrl}.js`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ items }),
          signal: typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(REQUEST_TIMEOUT_MS) : undefined,
        });
        result = await response.json().catch(() => ({}));
      } catch (error) {
        // Network failure or timeout: the request itself failed.
        deferred?.reject(error);
        if (events) {
          this.dispatchEvent(
            new events.CartErrorEvent({ error: this.dataset.textError ?? '', code: 'SERVICE_UNAVAILABLE' })
          );
        }

        this.showError(this.dataset.textError ?? '');
        this.setBusy(false);
        return;
      }

      // A 422 means the cart declined the request, for example because an item
      // sold out after the page loaded. Shopify's message explains which one.
      const errorMessage = response.ok ? '' : result.description || result.message || this.dataset.textError || '';
      const userErrors = response.ok ? [] : [{ code: 'INVALID', field: ['lines'], message: errorMessage }];

      if (response.ok) {
        this.showSuccess();
      } else {
        this.showError(errorMessage);
      }

      this.setBusy(false);

      // Give the theme the cart as it now stands, so the cart count and drawer
      // refresh. This runs after the customer already has their result.
      if (events && deferred) {
        this.fetchCart()
          .then((cart) =>
            deferred.resolve({
              cart: events.CartLinesUpdateEvent.createCartFromAjaxResponse(cart),
              userErrors,
              warnings: [],
            })
          )
          .catch((error) => deferred.reject(error));
      }
    };

    /** @return {Promise<any>} The current cart from the Ajax API. */
    fetchCart() {
      return fetch(`${this.dataset.cartUrl}.js`, { headers: { Accept: 'application/json' } }).then((response) => {
        if (!response.ok) throw new Error(`Cart request failed with status ${response.status}`);
        return response.json();
      });
    }

    /** @param {boolean} isBusy */
    setBusy(isBusy) {
      this.isBusy = isBusy;

      // Freeze the choices while the request runs, without restyling them as disabled.
      if (this.steps) this.steps.inert = isBusy;

      if (this.submitButton) {
        // aria-busy shows the spinner (CSS) and tells assistive tech the button is working.
        // The button keeps focus; repeat clicks are ignored by the isBusy guard.
        this.submitButton.setAttribute('aria-busy', String(isBusy));
        this.submitButton.setAttribute('aria-disabled', String(isBusy));
      }

      setText(this.submitLabel, isBusy ? this.dataset.textAdding ?? '' : this.defaultLabel);

      if (!isBusy) this.update();
    }

    showSuccess() {
      if (!this.successMessage) return;

      const link = document.createElement('a');
      link.href = this.dataset.cartUrl ?? '/cart';
      link.textContent = this.dataset.textViewCart ?? '';

      // Built with DOM methods, never innerHTML, so text can never inject markup.
      this.successMessage.replaceChildren(`${this.dataset.textAdded ?? ''} `, link);
    }

    /** @param {string} message */
    showError(message) {
      setText(this.errorMessage, message);
    }

    clearMessages() {
      this.successMessage?.replaceChildren();
      setText(this.errorMessage, '');
    }

    /**
     * @param {number} cents
     * @return {string}
     */
    formatMoney(cents) {
      return this.formatter.format(cents / 100);
    }
  }

  if (!customElements.get('bundle-builder')) {
    customElements.define('bundle-builder', BundleBuilder);
  }
})();