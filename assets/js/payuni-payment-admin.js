jQuery(function ($) {
	'use strict';

	const { __, _x, _n, _nx } = wp.i18n;
	/**
	 * Object to handle LINE Pay admin functions.
	 */
	var payuni_admin = {
		/**
		 * Initialize.
		 */
		init: function () {
			$(document.body).on('change', '#linepay_tw_sandboxmode_enabled', function () {
				var sandbox_channel_id = $('#linepay_tw_sandbox_channel_id').parents('tr').eq(0),
					sandbox_channel_secret = $('#linepay_tw_sandbox_channel_secret').parents('tr').eq(0),

					channel_id = $('#linepay_tw_channel_id').parents('tr').eq(0),
					channel_secret = $('#linepay_tw_channel_secret').parents('tr').eq(0);


				if ($(this).is(':checked')) {
					sandbox_channel_id.show();
					sandbox_channel_secret.show();

					channel_id.hide();
					channel_secret.hide();

				} else {
					sandbox_channel_id.hide();
					sandbox_channel_secret.hide();

					channel_id.show();
					channel_secret.show();

				}
			});

			$('#linepay_tw_sandboxmode_enabled').trigger('change');

			$( document ).on( 'click', '#payuni-query-btn', function( event ){
				event.preventDefault();
				var order_id = $(this).data('id');
				// $('.linepay-notice').remove();
				if ($.blockUI) {
					$('#payuni-order-meta-boxes').block({
						message: null,
					});
				}
			$.ajax({
				url: payuni_object.ajax_url,
				data: {
					action: 'payuni_query',
					order_id: order_id,
					security: payuni_object.query_nonce,
				},
				dataType: "json",
				type: 'post',
				success: function (data) {
					console.log(data);

					if (data.success) {
						alert(data.message);
						window.location.reload();
					} else {
						alert(data.message);
					}
					if ($.blockUI) {
						$('#payuni-order-meta-boxes').unblock();
					}
				},
				always: function () {
					if ($.blockUI) {
						$('#payuni-order-meta-boxes').unblock();
					}
				}
			});

	});

		}
	};

	payuni_admin.init();
});
