<?php
/**
 * REST endpoint POST /wp-json/tb247/v1/deals — nơi bot Discord gửi dữ liệu sản phẩm sang.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Rest_Controller {

	const NAMESPACE_ = 'tb247/v1';

	/**
	 * Đăng ký route trên hook rest_api_init.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/deals',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_create_or_update' ),
				'permission_callback' => array( 'TB247_DM_Auth', 'check' ),
				'args'                => self::get_args_schema(),
			)
		);
	}

	/**
	 * Khai báo schema tham số để WP REST tự validate kiểu dữ liệu cơ bản.
	 *
	 * @return array
	 */
	private static function get_args_schema() {
		return array(
			'marketplace'   => array(
				'required' => true,
				'type'     => 'string',
			),
			'asin'          => array(
				'required' => true,
				'type'     => 'string',
			),
			'product_name'  => array(
				'required' => true,
				'type'     => 'string',
			),
			'jan'           => array(
				'required' => false,
				'type'     => 'string',
			),
			'sale_price'    => array(
				'required' => false,
				'type'     => 'integer',
			),
			'image'         => array(
				'required' => false,
				'type'     => 'string',
			),
			'product_url'   => array(
				'required' => false,
				'type'     => 'string',
			),
			'affiliate_url' => array(
				'required' => false,
				'type'     => 'string',
			),
		);
	}

	/**
	 * Xử lý tạo mới hoặc cập nhật deal, trả về landing_url cho bot dùng thay Amazon URL.
	 *
	 * @param WP_REST_Request $request Request đã qua xác thực API key.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_or_update( WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) || empty( $payload['marketplace'] ) ) {
			return new WP_Error(
				'tb247_invalid_payload',
				__( 'Payload không hợp lệ hoặc thiếu marketplace.', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		$marketplace = TB247_DM_Marketplace_Registry::get( sanitize_key( $payload['marketplace'] ) );

		if ( ! $marketplace ) {
			return new WP_Error(
				'tb247_unsupported_marketplace',
				__( 'Marketplace không được hỗ trợ.', 'tb247-deal-manager' ),
				array( 'status' => 400 )
			);
		}

		$validation = $marketplace->validate( $payload );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = TB247_DM_Deal_Service::create_or_update( $marketplace, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'action'      => $result['action'],
				'asin'        => $result['code'],
				'landing_url' => home_url( '/d/' . rawurlencode( $result['code'] ) ),
			),
			200
		);
	}
}
