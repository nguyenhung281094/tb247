<?php
/**
 * Marketplace Amazon (duy nhất được hỗ trợ ở giai đoạn v1).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Amazon_Marketplace implements TB247_DM_Marketplace {

	public function get_slug() {
		return 'amazon';
	}

	public function get_buy_button_label() {
		return __( 'Amazonで購入', 'tb247-deal-manager' );
	}

	public function validate( array $payload ) {
		if ( empty( $payload['asin'] ) || ! preg_match( '/^[A-Z0-9]{10}$/', strtoupper( $payload['asin'] ) ) ) {
			return new WP_Error(
				'tb247_invalid_asin',
				__( 'ASIN thiếu hoặc sai định dạng (phải gồm đúng 10 ký tự chữ/số).', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $payload['product_name'] ) ) {
			return new WP_Error(
				'tb247_missing_product_name',
				__( 'Thiếu product_name.', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	public function get_code( array $payload ) {
		return strtoupper( sanitize_text_field( $payload['asin'] ) );
	}
}
