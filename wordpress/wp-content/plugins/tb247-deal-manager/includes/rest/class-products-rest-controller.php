<?php
/**
 * REST endpoint POST /wp-json/tb247/v1/products.
 *
 * Endpoint mới, xác thực bằng Bearer token, chuẩn bị cho lần cập nhật Bot kế
 * tiếp — KHÔNG thay thế endpoint /deals hiện có (Bot production đang gọi
 * /deals, giữ nguyên để không ảnh hưởng). Cả 2 endpoint dùng chung
 * TB247_DM_Deal_Service / CPT "deal" / rewrite /d/{ASIN} — không viết lại
 * logic tạo/cập nhật, chỉ khác lớp xác thực + validate + tên field ở tầng REST.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Products_Rest_Controller {

	const NAMESPACE_ = 'tb247/v1';

	/**
	 * Đăng ký route trên hook rest_api_init.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/products',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_create_or_update' ),
				// Không dùng permission_callback mặc định: response 401/500 của
				// /products phải đúng JSON shape riêng theo spec, khác shape mặc
				// định của WordPress khi permission_callback trả WP_Error. Auth
				// được kiểm tra thủ công ngay đầu handler thay vào đó.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Xử lý tạo mới hoặc cập nhật sản phẩm.
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_create_or_update( WP_REST_Request $request ) {
		$auth = TB247_DM_Auth::check_bearer( $request );

		if ( is_wp_error( $auth ) ) {
			self::log( 'auth_failed', '', $auth->get_error_code() );
			return self::error_response_from_wp_error( $auth );
		}

		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'invalid_request',
					'message' => __( 'Request body must be valid JSON.', 'tb247-deal-manager' ),
				),
				400
			);
		}

		$validation = TB247_DM_Products_Validator::validate( $payload );

		if ( ! $validation['valid'] ) {
			self::log( 'validation_failed', isset( $payload['asin'] ) ? sanitize_text_field( (string) $payload['asin'] ) : '' );

			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'invalid_request',
					'message' => __( 'Request data is invalid.', 'tb247-deal-manager' ),
					'errors'  => $validation['errors'],
				),
				400
			);
		}

		$product     = $validation['data'];
		$marketplace = TB247_DM_Marketplace_Registry::get( 'amazon' );

		if ( ! $marketplace ) {
			self::log( 'server_error', $product['asin'], 'marketplace_not_registered' );
			return self::generic_server_error_response();
		}

		// Ánh xạ sang field name nội bộ mà Deal_Service/Amazon_Marketplace đang
		// dùng (product_name/sale_price) — Deal_Service không đổi gì cả.
		$deal_payload = array(
			'asin'          => $product['asin'],
			'product_name'  => $product['title'],
			'jan'           => $product['jan'],
			'sale_price'    => $product['price'],
			'image'         => $product['image'],
			'affiliate_url' => $product['affiliate_url'],
			'product_url'   => '',
			'brand'         => $product['brand'],
			'in_stock'      => $product['in_stock'],
		);

		$result = TB247_DM_Deal_Service::create_or_update( $marketplace, $deal_payload );

		if ( is_wp_error( $result ) ) {
			self::log( 'server_error', $product['asin'], $result->get_error_code(), $result->get_error_message() );
			return self::generic_server_error_response();
		}

		$is_created = ( 'created' === $result['action'] );

		self::log( $result['action'], $result['code'], '', '', $result['post_id'] );

		return new WP_REST_Response(
			array(
				'success' => true,
				'action'  => $result['action'],
				'message' => $is_created
					? __( 'Product created successfully.', 'tb247-deal-manager' )
					: __( 'Product updated successfully.', 'tb247-deal-manager' ),
				'post_id' => $result['post_id'],
				'asin'    => $result['code'],
				'url'     => home_url( '/d/' . rawurlencode( $result['code'] ) ),
			),
			$is_created ? 201 : 200
		);
	}

	/**
	 * Chuyển WP_Error từ bước xác thực thành đúng JSON shape của /products
	 * (401 cho token thiếu/sai, 500 cho server chưa cấu hình token) — không
	 * lộ nội dung message kỹ thuật nội bộ ra response.
	 *
	 * @param WP_Error $error Lỗi từ TB247_DM_Auth::check_bearer().
	 * @return WP_REST_Response
	 */
	private static function error_response_from_wp_error( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 500;

		if ( 401 === $status ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'unauthorized',
					'message' => __( 'Invalid or missing API token.', 'tb247-deal-manager' ),
				),
				401
			);
		}

		return self::generic_server_error_response();
	}

	/**
	 * Response 500 chuẩn, không lộ chi tiết lỗi nội bộ.
	 *
	 * @return WP_REST_Response
	 */
	private static function generic_server_error_response() {
		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => 'server_error',
				'message' => __( 'Unable to create or update product.', 'tb247-deal-manager' ),
			),
			500
		);
	}

	/**
	 * Log tối thiểu để debug qua WP_DEBUG_LOG (error_log() core của WordPress).
	 * KHÔNG log token/Authorization header/dữ liệu bí mật. Không ghi gì nếu
	 * WP_DEBUG_LOG đang tắt, để không phát sinh log ngoài ý muốn trên production.
	 *
	 * @param string $event         created|updated|auth_failed|validation_failed|server_error.
	 * @param string $asin          ASIN liên quan (nếu có).
	 * @param string $error_code    Mã lỗi nội bộ (nếu có).
	 * @param string $error_message Thông báo lỗi kỹ thuật đã làm sạch (nếu có).
	 * @param int    $post_id       ID bài viết deal liên quan (nếu có).
	 */
	private static function log( $event, $asin = '', $error_code = '', $error_message = '', $post_id = 0 ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$line = sprintf(
			'[TB247 Products API] %s | event=%s asin=%s post_id=%d error_code=%s error_message=%s',
			gmdate( 'Y-m-d H:i:s' ),
			$event,
			$asin,
			$post_id,
			$error_code,
			$error_message
		);

		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
