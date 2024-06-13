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
}
