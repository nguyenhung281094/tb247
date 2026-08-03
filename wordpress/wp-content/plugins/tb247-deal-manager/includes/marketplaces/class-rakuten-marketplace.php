<?php
/**
 * Marketplace Rakuten — không có ASIN như Amazon; mã định danh duy nhất là
 * cặp shop_code + item_code (giống cấu trúc URL thật của Rakuten Ichiba
 * item.rakuten.co.jp/{shop_code}/{item_code}/).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Rakuten_Marketplace implements TB247_DM_Marketplace {

	public function get_slug() {
		return 'rakuten';
	}

	public function get_buy_button_label() {
		return __( '楽天で購入', 'tb247-deal-manager' );
	}

	/**
	 * Payload dùng field shop_code/item_code (không có asin). JAN không bắt
	 * buộc ở tầng marketplace — Products_Validator xử lý JAN optional riêng,
	 * giống Amazon.
	 *
	 * @param array $payload Dữ liệu gửi từ bot.
	 * @return true|WP_Error
	 */
	public function validate( array $payload ) {
		$shop_code = isset( $payload['shop_code'] ) ? trim( (string) $payload['shop_code'] ) : '';
		$item_code = isset( $payload['item_code'] ) ? trim( (string) $payload['item_code'] ) : '';

		if ( '' === $shop_code || '' === $item_code ) {
			return new WP_Error(
				'tb247_missing_rakuten_code',
				__( 'Thiếu shop_code hoặc item_code cho deal Rakuten.', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $payload['product_name'] ) && empty( $payload['title'] ) ) {
			return new WP_Error(
				'tb247_missing_product_name',
				__( 'Thiếu tên sản phẩm.', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Mã định danh duy nhất dùng cho _tb247_asin (field lưu trữ chung mọi
	 * sàn) và URL /d/{code} — ghép shop_code-item_code, viết hoa để nhất
	 * quán với cách find_by_code()/find_by_marketplace_and_code() so khớp
	 * (strtoupper), tránh dấu ":" trong URL công khai dù rewrite cho phép.
	 *
	 * @param array $payload Dữ liệu gửi từ bot.
	 * @return string
	 */
	public function get_code( array $payload ) {
		$shop_code = sanitize_key( $payload['shop_code'] ?? '' );
		$item_code = sanitize_key( $payload['item_code'] ?? '' );

		return strtoupper( $shop_code . '-' . $item_code );
	}
}
