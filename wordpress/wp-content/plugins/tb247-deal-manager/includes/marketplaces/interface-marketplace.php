<?php
/**
 * Hợp đồng chung cho mỗi sàn thương mại điện tử (Amazon, và sau này Rakuten/Yahoo).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

interface TB247_DM_Marketplace {

	/**
	 * Slug định danh sàn, khớp với field "marketplace" trong payload REST API.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Nhãn nút mua hàng hiển thị trên Landing Page.
	 *
	 * @return string
	 */
	public function get_buy_button_label();

	/**
	 * Kiểm tra payload có hợp lệ cho sàn này không.
	 *
	 * @param array $payload Dữ liệu gửi từ bot.
	 * @return true|WP_Error
	 */
	public function validate( array $payload );

	/**
	 * Lấy mã định danh sản phẩm duy nhất (vd: ASIN) từ payload, đã chuẩn hoá.
	 *
	 * @param array $payload Dữ liệu gửi từ bot.
	 * @return string
	 */
	public function get_code( array $payload );
}
