<?php
/**
 * PayType class file.
 *
 * @package payuni
 */

namespace WPBrewer\Payuni\Payment\Utils;

use WPBrewer\Payuni\Payment\Gateways\Aftee;
use WPBrewer\Payuni\Payment\Gateways\ApplePay;
use WPBrewer\Payuni\Payment\Gateways\Atm;
use WPBrewer\Payuni\Payment\Gateways\Credit;
use WPBrewer\Payuni\Payment\Gateways\CreditUnionPay;
use WPBrewer\Payuni\Payment\Gateways\Cvs;
use WPBrewer\Payuni\Payment\Gateways\GooglePay;
use WPBrewer\Payuni\Payment\Gateways\LinePay;
use WPBrewer\Payuni\Payment\Gateways\SamsungPay;

/**
 * Class PayType
 */
class PayType {

	const CREDIT_CARD = 1; // 信用卡.
	const ATM         = 2; // ATM轉帳.
	const CVS_CODE    = 3; // 代碼.
	const C2C         = 5; // 貨到付款(超商取貨付款).
	const ICASH       = 6; // 愛金卡 (ICash).
	const AFTEE       = 7; // 後支付 (Aftee).
	const LINEPAY     = 9; // LinePay.
	const DELIVERY    = 10; // 宅配到付.

	/**
	 * Get the payment type name.
	 *
	 * @param mixed $pay_type Payment type code.
	 *
	 * @return string
	 */
	public static function get_name( $pay_type ) {
		if ( null === $pay_type || '' === $pay_type ) {
			return '';
		}

		switch ( (int) $pay_type ) {
			case self::CREDIT_CARD:
				return __( 'Credit Card', 'wpbr-payuni-payment' );
			case self::ATM:
				return __( 'ATM', 'wpbr-payuni-payment' );
			case self::CVS_CODE:
				return __( 'CVS Code', 'wpbr-payuni-payment' );
			case self::C2C:
				return __( 'C2C', 'wpbr-payuni-payment' );
			case self::ICASH:
				return __( 'ICash', 'wpbr-payuni-payment' );
			case self::AFTEE:
				return __( 'AFTEE', 'wpbr-payuni-payment' );
			case self::LINEPAY:
				return __( 'LINE Pay', 'wpbr-payuni-payment' );
			case self::DELIVERY:
				return __( 'Delivery', 'wpbr-payuni-payment' );
			default:
				return __( 'Unknown Payment Type', 'wpbr-payuni-payment' );
		}
	}

	/**
	 * Whether PAYUNi PaymentType (and optional AuthType) matches the order gateway.
	 *
	 * @param \WC_Order   $order     The order.
	 * @param mixed       $pay_type  PAYUNi PaymentType.
	 * @param mixed|null  $auth_type Optional AuthType for credit-family refinement.
	 *
	 * @return bool
	 */
	public static function matches_order_payment_method( $order, $pay_type, $auth_type = null ) {
		$gateway_id = (string) $order->get_payment_method();
		if ( '' === $gateway_id || null === $pay_type || '' === $pay_type ) {
			return false;
		}

		$pay_type  = (string) $pay_type;
		$auth_type = ( null === $auth_type || '' === $auth_type ) ? null : (string) $auth_type;

		switch ( (int) $pay_type ) {
			case self::ATM:
				return Atm::GATEWAY_ID === $gateway_id;
			case self::CVS_CODE:
				return Cvs::GATEWAY_ID === $gateway_id;
			case self::AFTEE:
				return Aftee::GATEWAY_ID === $gateway_id;
			case self::LINEPAY:
				return LinePay::GATEWAY_ID === $gateway_id;
			case self::CREDIT_CARD:
				return self::matches_credit_family_gateway( $gateway_id, $auth_type );
			default:
				return false;
		}
	}

	/**
	 * Match PaymentType=1 gateways, optionally refined by AuthType.
	 *
	 * @param string      $gateway_id Gateway id.
	 * @param string|null $auth_type  AuthType when known.
	 *
	 * @return bool
	 */
	private static function matches_credit_family_gateway( $gateway_id, $auth_type ) {
		if ( null === $auth_type ) {
			return self::is_credit_family_gateway( $gateway_id );
		}

		switch ( $auth_type ) {
			case AuthType::INSTALLMENT:
				return 0 === strpos( $gateway_id, 'payuni-upp-installment-' );
			case AuthType::APPLEPAY:
				return ApplePay::GATEWAY_ID === $gateway_id;
			case AuthType::GOOGLEPAY:
				return GooglePay::GATEWAY_ID === $gateway_id;
			case AuthType::SAMSUNGPAY:
				return SamsungPay::GATEWAY_ID === $gateway_id;
			case AuthType::UNIPAY:
				return CreditUnionPay::GATEWAY_ID === $gateway_id;
			case AuthType::ONE:
			case AuthType::POINT:
				return Credit::GATEWAY_ID === $gateway_id;
			default:
				return self::is_credit_family_gateway( $gateway_id );
		}
	}

	/**
	 * Whether gateway is any credit-family UPP method.
	 *
	 * @param string $gateway_id Gateway id.
	 *
	 * @return bool
	 */
	public static function is_credit_family_gateway( $gateway_id ) {
		if ( in_array(
			$gateway_id,
			array(
				Credit::GATEWAY_ID,
				ApplePay::GATEWAY_ID,
				GooglePay::GATEWAY_ID,
				SamsungPay::GATEWAY_ID,
				CreditUnionPay::GATEWAY_ID,
			),
			true
		) ) {
			return true;
		}

		return 0 === strpos( $gateway_id, 'payuni-upp-installment-' );
	}

	/**
	 * Instant payment gateways (thankyou poll / confirming notice).
	 * ATM and CVS code are offline and stay on the unpaid message.
	 *
	 * @param string $gateway_id Gateway id.
	 *
	 * @return bool
	 */
	public static function is_instant_payment_gateway( $gateway_id ) {
		if ( Atm::GATEWAY_ID === $gateway_id || Cvs::GATEWAY_ID === $gateway_id ) {
			return false;
		}

		if ( self::is_credit_family_gateway( $gateway_id ) ) {
			return true;
		}

		return in_array(
			$gateway_id,
			array(
				Aftee::GATEWAY_ID,
				LinePay::GATEWAY_ID,
			),
			true
		);
	}
}
