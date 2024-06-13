<?php
/**
 * TradeStatus class file.
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Utils;

/**
 * Class TradeStatus
 */
class TradeStatus {

	const OK      = '0'; // 信用審查正常或取號成功.
	const PAID    = '1'; // 已付款.
	const FAIL    = '2'; // 付款失敗.
	const CANCEL  = '3'; // 付款取消.
	const EXPIRED = '4'; // 交易逾期.
	const UNPAID  = '9'; // 未付款.

	/**
	 * Get the status name by status code.
	 *
	 * @param string $status_code The status code.
	 */
	public static function get_name( $status_code ) {
		switch ( $status_code ) {
			case self::OK:
				return __( 'OK', 'wpbr-payuni-payment' );
			case self::PAID:
				return __( 'Paid', 'wpbr-payuni-payment' );
			case self::FAIL:
				return __( 'Payment Fail', 'wpbr-payuni-payment' );
			case self::CANCEL:
				return __( 'Payment Cancel', 'wpbr-payuni-payment' );
			case self::EXPIRED:
				return __( 'Transaction Expired', 'wpbr-payuni-payment' );
			case self::UNPAID:
				return __( 'Unpaid', 'wpbr-payuni-payment' );
			default:
				return __( 'Unknown Trade Status', 'wpbr-payuni-payment' );
		}
	}
}
