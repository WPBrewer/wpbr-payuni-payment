/**
 * Poll PAYUNi order status on the order-received page while awaiting payment.
 * Keeps thankyou countdown in sync and reloads once payment is confirmed.
 *
 * @package payuni
 */
( function () {
	'use strict';

	var cfg = window.payuniOrderStatusPoll;

	if ( ! cfg || ! cfg.ajaxUrl || ! cfg.orderId || ! cfg.orderKey ) {
		return;
	}

	var attempts      = 0;
	var maxAttempts   = parseInt( cfg.maxAttempts, 10 ) || 20;
	var interval      = parseInt( cfg.interval, 10 ) || 3000;
	var totalSeconds  = parseInt( cfg.totalSeconds, 10 ) || Math.ceil( ( maxAttempts * interval ) / 1000 );
	var deadline      = Date.now() + ( totalSeconds * 1000 );
	var countdownEl   = document.querySelector( '[data-payuni-countdown]' );
	var noticeEl      = document.getElementById( 'payuni-confirming-payment' );
	var stopped       = false;
	var countdownTimer;

	function showTimeoutMessage() {
		if ( noticeEl && cfg.timeoutText ) {
			noticeEl.textContent = cfg.timeoutText;
		}
	}

	function updateCountdown() {
		if ( ! countdownEl || stopped ) {
			return;
		}

		var left = Math.max( 0, Math.ceil( ( deadline - Date.now() ) / 1000 ) );
		countdownEl.textContent = String( left );

		if ( left <= 0 ) {
			stopped = true;
			window.clearInterval( countdownTimer );
			showTimeoutMessage();
		}
	}

	function stopPolling() {
		stopped = true;
		window.clearInterval( countdownTimer );
	}

	function poll() {
		if ( stopped || attempts >= maxAttempts ) {
			stopPolling();
			showTimeoutMessage();
			return;
		}
		attempts++;

		var url = cfg.ajaxUrl +
			'?action=payuni_order_status' +
			'&order_id=' + encodeURIComponent( cfg.orderId ) +
			'&order_key=' + encodeURIComponent( cfg.orderKey );

		window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( stopped ) {
					return;
				}
				if ( json && json.success && json.data && false === json.data.needs_payment ) {
					stopPolling();
					window.location.reload();
					return;
				}
				if ( attempts >= maxAttempts ) {
					stopPolling();
					showTimeoutMessage();
					return;
				}
				window.setTimeout( poll, interval );
			} )
			.catch( function () {
				if ( stopped ) {
					return;
				}
				if ( attempts >= maxAttempts ) {
					stopPolling();
					showTimeoutMessage();
					return;
				}
				window.setTimeout( poll, interval );
			} );
	}

	updateCountdown();
	countdownTimer = window.setInterval( updateCountdown, 250 );
	window.setTimeout( poll, interval );
} )();
