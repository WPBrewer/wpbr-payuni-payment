<?php
/**
 * Settings for PAYUNi AFTEE payment.
 *
 * @package payuni
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for PAYUNi AFTEE payment gateway
 */
return array(

	'enabled'                    => array(
		'title'   => __( 'Enable/Disable', 'woo-payuni-payment' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable', 'woo-payuni-payment' ),
		'default' => 'no',
	),
	'title'                      => array(
		'title'       => __( 'Title', 'woo-payuni-payment' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woo-payuni-payment' ),
		'default'     => __( 'PAYUNi AFTEE Payment', 'woo-payuni-payment' ),
		'desc_tip'    => true,
	),
	'description'                => array(
		'title'       => __( 'Description', 'woo-payuni-payment' ),
		'type'        => 'textarea',
		'description' => __( 'This controls the description which the user sees during checkout.', 'woo-payuni-payment' ),
		'desc_tip'    => true,
	),
	'min_amount'      => array(
		'title'       => __( 'Minimum Amount', 'cathaybk-payment' ),
		'type'        => 'number',
		'description' => __('Minimum amount to use this payment gateway.', 'woo-payuni-payment' ),
	),
	'incomplete_payment_message' => array(
		'title'       => __( 'Incomplete Payment Message', 'woo-payuni-payment' ),
		'type'        => 'textarea',
		'description' => __( 'This controls the message displayed on thank you page when the payment is incomplated.', 'woo-payuni-payment' ),
		'desc_tip'    => true,
	),

);
