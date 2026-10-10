/**
 * Services Calculator: live price estimate.
 *
 * Vanilla JS, no dependencies. Loaded with `defer`, so the DOM is
 * already parsed when this runs. One delegated `change` listener per
 * calculator keeps listeners to a minimum, and only the total's text
 * is updated on each change.
 */
( function () {
	'use strict';

	const formatters = {};

	/**
	 * Return a cached Intl.NumberFormat for a locale and currency.
	 * Falls back to en-US / USD if the given values are invalid.
	 *
	 * @param {string} locale   BCP 47 locale, e.g. "en-US".
	 * @param {string} currency ISO 4217 code, e.g. "USD".
	 * @return {Intl.NumberFormat} Formatter.
	 */
	function getFormatter( locale, currency ) {
		const key = locale + '|' + currency;

		if ( ! formatters[ key ] ) {
			try {
				formatters[ key ] = new Intl.NumberFormat( locale, {
					style: 'currency',
					currency,
				} );
			} catch ( error ) {
				formatters[ key ] = new Intl.NumberFormat( 'en-US', {
					style: 'currency',
					currency: 'USD',
				} );
			}
		}

		return formatters[ key ];
	}

	/**
	 * Convert a price string to integer cents to avoid floating point errors.
	 *
	 * @param {string|undefined} value Raw price, e.g. "299.99".
	 * @return {number} Price in cents, never negative.
	 */
	function toCents( value ) {
		const number = parseFloat( value );

		return Number.isFinite( number ) && number > 0 ? Math.round( number * 100 ) : 0;
	}

	/**
	 * Recalculate and display the total for one service card.
	 *
	 * @param {HTMLElement}       card      Card element with data-sac-card.
	 * @param {Intl.NumberFormat} formatter Currency formatter.
	 */
	function updateCard( card, formatter ) {
		const output = card.querySelector( '[data-sac-total]' );

		if ( ! output ) {
			return;
		}

		let totalCents = toCents( card.dataset.sacBase );

		card.querySelectorAll( '.sac-addon__input:checked' ).forEach( ( input ) => {
			totalCents += toCents( input.dataset.sacPrice );
		} );

		const formatted = formatter.format( totalCents / 100 );

		// Only touch the DOM when the value actually changed.
		if ( output.textContent !== formatted ) {
			output.textContent = formatted;
		}
	}

	/**
	 * Set up one calculator section.
	 *
	 * @param {HTMLElement} calculator Section element with the .sac-calculator class.
	 */
	function initCalculator( calculator ) {
		const formatter = getFormatter(
			calculator.dataset.sacLocale || 'en-US',
			calculator.dataset.sacCurrency || 'USD'
		);

		// Event delegation: one listener handles every checkbox in this section.
		calculator.addEventListener( 'change', ( event ) => {
			if ( ! event.target.matches( '.sac-addon__input' ) ) {
				return;
			}

			const card = event.target.closest( '[data-sac-card]' );

			if ( card ) {
				updateCard( card, formatter );
			}
		} );

		// Some browsers (e.g. Firefox) restore checkbox state on reload,
		// so sync every total once on load.
		calculator.querySelectorAll( '[data-sac-card]' ).forEach( ( card ) => {
			updateCard( card, formatter );
		} );
	}

	document.querySelectorAll( '.sac-calculator' ).forEach( initCalculator );
} )();