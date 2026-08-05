<?php
/**
 * REST endpoint dành cho Slash Command của Bot: tìm deal theo JAN/ASIN/source URL
 * và cập nhật cờ is_recommended/is_sale cho deal đã tồn tại.
 *
 * Endpoint MỚI, KHÔNG thay thế hay đụng vào /products (Bot production đang
 * gọi /products để tạo/cập nhật deal — giữ nguyên 100%). Dùng chung
 * TB247_DM_Auth::check_bearer() (cùng Bearer token TB247_API_TOKEN) và
 * TB247_DM_Deal_Service — không viết lại logic tìm/ghi deal.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deals_Lookup_Rest_Controller {

	const NAMESPACE_ = 'tb247/v1';

	/**
	 * Đăng ký route trên hook rest_api_init.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/deals/find',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_find' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/deals/flags',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'handle_update_flags' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /deals/find?jan=&asin=&source_url= — tìm deal theo thứ tự ưu tiên
	 * JAN -> ASIN -> source_url. Không tạo deal mới.
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_find( WP_REST_Request $request ) {
		$auth = TB247_DM_Auth::check_bearer( $request );

		if ( is_wp_error( $auth ) ) {
			return self::error_response_from_wp_error( $auth );
		}

		$deal = self::locate_deal(
			(string) $request->get_param( 'jan' ),
			(string) $request->get_param( 'asin' ),
			(string) $request->get_param( 'source_url' )
		);

		if ( ! $deal ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'found'   => false,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'found'   => true,
				'deal'    => self::deal_to_array( $deal ),
			),
			200
		);
	}

	/**
	 * PUT/PATCH /deals/flags — tìm deal theo JAN/ASIN/source_url rồi cập nhật
	 * is_recommended/is_sale. Chỉ ghi field nào có mặt trong payload (dạng
	 * boolean rõ ràng); field vắng mặt giữ nguyên giá trị cũ. KHÔNG tạo deal mới.
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_update_flags( WP_REST_Request $request ) {
		$auth = TB247_DM_Auth::check_bearer( $request );

		if ( is_wp_error( $auth ) ) {
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

		$deal = self::locate_deal(
			isset( $payload['jan'] ) ? (string) $payload['jan'] : '',
			isset( $payload['asin'] ) ? (string) $payload['asin'] : '',
			isset( $payload['source_url'] ) ? (string) $payload['source_url'] : ''
		);

		if ( ! $deal ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'not_found',
					'message' => __( 'Deal not found. It must be created via /products first.', 'tb247-deal-manager' ),
				),
				404
			);
		}

		// affiliate_url optional (§4.4/§12: /recommend Rakuten trên deal ĐÃ tồn
		// tại — có aff mới hợp lệ thì cập nhật, aff trống/thiếu thì giữ nguyên).
		// Validate lại NGHIÊM NGẶT qua đúng allowlist marketplace của deal thay vì
		// tin payload — Bot đã validate 1 lần (§4) nhưng endpoint này vẫn không
		// tin tuyệt đối, cùng tinh thần defense-in-depth với /products. KHÔNG
		// BAO GIỜ ghi affiliate_url rỗng ở đây (chỉ ghi khi có giá trị hợp lệ).
		if ( isset( $payload['affiliate_url'] ) && '' !== trim( (string) $payload['affiliate_url'] ) ) {
			$marketplace     = get_post_meta( $deal->ID, '_tb247_marketplace', true );
			$raw_affiliate   = trim( (string) $payload['affiliate_url'] );
			$validated_value = null;

			if ( function_exists( 'tb247_validate_affiliate_url' ) ) {
				$validation      = tb247_validate_affiliate_url( $raw_affiliate, $marketplace );
				$validated_value = $validation['url'];
			}

			if ( null === $validated_value ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'code'    => 'invalid_affiliate_url',
						'message' => __( 'affiliate_url must be a valid, approved marketplace affiliate URL.', 'tb247-deal-manager' ),
					),
					400
				);
			}

			update_post_meta( $deal->ID, '_tb247_affiliate_url', esc_url_raw( $validated_value ) );
		}

		$is_recommended = self::to_bool_or_null( $payload['is_recommended'] ?? null );
		$is_sale        = self::to_bool_or_null( $payload['is_sale'] ?? null );

		TB247_DM_Deal_Service::update_flags( $deal->ID, $is_recommended, $is_sale );

		// Đọc lại để trả đúng giá trị hiện tại (kể cả field không được truyền vào).
		$deal = get_post( $deal->ID );

		return new WP_REST_Response(
			array(
				'success' => true,
				'deal'    => self::deal_to_array( $deal ),
			),
			200
		);
	}

	/**
	 * Tìm deal theo thứ tự ưu tiên: JAN -> ASIN -> source_url (chuẩn hoá).
	 *
	 * @param string $jan        JAN thô (có thể rỗng).
	 * @param string $asin       ASIN thô (có thể rỗng).
	 * @param string $source_url URL sản phẩm gốc thô (có thể rỗng).
	 * @return WP_Post|null
	 */
	private static function locate_deal( $jan, $asin, $source_url ) {
		$jan = trim( $jan );

		if ( '' !== $jan ) {
			$deal = TB247_DM_Deal_Service::find_by_jan( $jan );

			if ( $deal ) {
				return $deal;
			}
		}

		$asin = trim( $asin );

		if ( '' !== $asin ) {
			$deal = TB247_DM_Deal_Service::find_by_code( $asin );

			if ( $deal ) {
				return $deal;
			}
		}

		$source_url = trim( $source_url );

		if ( '' !== $source_url ) {
			$deal = TB247_DM_Deal_Service::find_by_source_url( $source_url );

			if ( $deal ) {
				return $deal;
			}
		}

		return null;
	}

	/**
	 * Chuẩn hoá payload field is_recommended/is_sale: chỉ chấp nhận true/false
	 * rõ ràng, mọi giá trị khác (thiếu, null, sai kiểu) coi là "giữ nguyên".
	 *
	 * @param mixed $value Giá trị thô từ payload.
	 * @return bool|null
	 */
	private static function to_bool_or_null( $value ) {
		if ( true === $value || false === $value ) {
			return $value;
		}

		return null;
	}

	/**
	 * Chuyển 1 deal post thành mảng dữ liệu trả về qua REST — chỉ field cần
	 * thiết cho Bot, không lộ field nội bộ khác.
	 *
	 * @param WP_Post $deal Deal post.
	 * @return array
	 */
	private static function deal_to_array( WP_Post $deal ) {
		return array(
			'post_id'        => $deal->ID,
			'code'           => get_post_meta( $deal->ID, '_tb247_asin', true ),
			'jan'            => get_post_meta( $deal->ID, '_tb247_jan', true ),
			'product_name'   => $deal->post_title,
			'marketplace'    => get_post_meta( $deal->ID, '_tb247_marketplace', true ),
			'is_recommended' => '1' === get_post_meta( $deal->ID, '_tb247_is_recommended', true ),
			'is_sale'        => '1' === get_post_meta( $deal->ID, '_tb247_is_sale', true ),
			'url'            => home_url( '/d/' . rawurlencode( get_post_meta( $deal->ID, '_tb247_asin', true ) ) ),
		);
	}

	/**
	 * Chuyển WP_Error từ bước xác thực thành đúng JSON shape, giống /products
	 * — không lộ nội dung message kỹ thuật nội bộ ra response.
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

		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => 'server_error',
				'message' => __( 'Server not configured.', 'tb247-deal-manager' ),
			),
			500
		);
	}
}
