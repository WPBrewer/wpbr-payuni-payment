=== Pay with PAYUNi ===
Contributors: wpbrewer, bluestan
Tags: WooCommerce, PAYUNi, taiwan, payment, payment gateway
Requires at least: 5.9
Tested up to: 6.5.3
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv2 or later.
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Accept payments via PAYUNi payment for your WooCommerce store.

== Description ==

Pay with PAYUNi allows you to provide PAYUNi payment gateway for the customers.
This plugin integrates with PAYUNi's UNiPaypage (UPP) service, which redirects the customer to the payment page when the customer places an order.

Major features include:

* Integrate with PAYUNi's UNiPaypage(UPP) service
* Credit Card payment
* Credit Card installment payment (Could enable/disable for different installments)
* Apple Pay、Google Pay、Samsung Pay
* LINE Pay
* AFTEE payment
* ATM Virtual Account payment
* CVS payment
* Support Language setting for UNiPaypage
* Support refund on backend (Credit Payment、Installment payment、Apple Pay、Google Pay、Samsung Pay)
* Compatible with High-Performance Order Storage (HPOS)

== Get started with PAYUNi ==

1. [Apply PAYUNi's seller member](https://www.payuni.com.tw/signup). If you want to test the payment service in sandbox mode, please register seller member in [Sandbox](https://sandbox.payuni.com.tw/signup)
2. Install & Activate Pay with PAYUNi plugin on your WordPress website.
3. Setup the Merchant ID、Hash Key and Hash IV
4. Go to the WordPress Admin Panel. Open WooCommerce > Settings > Payments to enable your preferred payment methods.

Once your PAYUNi seller member account and your store has been approved, you can start accepting payments.
The pricing is always per transaction. No startup fees, no monthly fees.


== About PAYUNi ==

PAYUNi (統一金流) is a third-party payment service provider based in Taiwan. 
It offers a wide range of secure and efficient payment solutions for businesses of all sizes. 

PAYUNi's services include credit card payment, ATM virtual account payment, CVS payment, and mobile payment options, 
making it easy for merchants to accept payments from their customers. 

Additionally, PAYUNi provides shipping services such as 7-11 C2C shipping and TCat Home Delivery. 

For more information, please refer to the PAYUNi website at [https://www.payuni.com.tw/](https://www.payuni.com.tw/).
Terms of Service : [https://www.payuni.com.tw/terms](https://www.payuni.com.tw/terms).

== Changelog ==

= 1.5.1 - 2024/07/08 =

* UPDATE - Refactor code for WordPress.org plugin review

= 1.5.0 - 2024/06/14 =

* ADD    - Allow to switch production and test API credentials 
* UPDATE - Refactor code and some minor update for better maintenance
* FIX    - Fix conflicts with WOOMP Credit card payment
* FIX    - Allow to refund order on different CloseStatus


= 1.4.0 - 2024/05/30 =

* ADD - Language setting for UNiPaypage

= 1.3.0 - 2024/05/15 =

* ADD - Samsung Pay support

= 1.2.0 - 2023/12/08 =

* UPDATE - rename plugin folder
* ADD - PAYUNi payment - shipping module support

= 1.1.1 – 2023/10/30 =

* FIX: Order status not changed when payment completed

= 1.1.0 - 2023-10-27 =

* ADD - Google Pay support
* ADD - HPOS Support
* ADD - Order Notify for WooCommerce Support
* FIX - Don't change order status when payment failed   

= 1.0.0 - 2023-05-27 =

* Initial release

== Frequently Asked Questions ==

= How do I ask for support if something goes wrong?

You could ask for support by sending email to service@wpbrewer.com or sending message via the facebook fanpage https://www.facebook.com/wpbrewer
