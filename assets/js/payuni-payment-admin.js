jQuery(function ($) {
	'use strict';

	const { __, _x, _n, _nx } = wp.i18n;
	/**
	 * Object to handle LINE Pay admin functions.
	 */
	var wpbr_payuni_upp_admin = {
		/**
		 * Initialize.
		 */
		init: function () {

			$(document.body).on('change', '#payuni_payment_testmode_enabled', function () {
				var sandbox_merchant_id = $('#payuni_payment_merchant_id_test').parents('tr').eq(0),
					sandbox_hashkey = $('#payuni_payment_hashkey_test').parents('tr').eq(0),
                    sandbox_ivkey = $('#payuni_payment_hashiv_test').parents('tr').eq(0),

					merchant_id = $('#payuni_payment_merchant_id').parents('tr').eq(0),
					hashkey = $('#payuni_payment_hashkey').parents('tr').eq(0),
                    ivkey = $('#payuni_payment_hashiv').parents('tr').eq(0);


				if ($(this).is(':checked')) {
					sandbox_merchant_id.show();
					sandbox_hashkey.show();
                    sandbox_ivkey.show();

					merchant_id.hide();
					hashkey.hide();
                    ivkey.hide();

				} else {
					sandbox_merchant_id.hide();
					sandbox_hashkey.hide();
                    sandbox_ivkey.hide();

					merchant_id.show();
					hashkey.show();
                    ivkey.show();

				}
			});

			$('#payuni_payment_testmode_enabled').trigger('change');

        }
    };

	wpbr_payuni_upp_admin.init();
});
