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
	 * GET /deals/find?marketplace=&asin=&shop_code=&item_code=&jan=&source_url=
	 * — tìm deal qua TB247_DM_Deal_Service::locate_deal_safe() (unique key
	 * CHÍNH XÁC theo marketplace trước, JAN CHỈ dùng như fallback cuối, luôn
	 * SCOPED theo marketplace nếu biết — KHÔNG BAO GIỜ trả nhầm deal khác
	 * marketplace/shop chỉ vì trùng JAN, xem docblock locate_deal_safe()).
	 * Không tạo deal mới.
	 *
	 * `ambiguous` (nhiều deal cùng JAN trong phạm vi tìm) trả `found: false,
	 * ambiguous: true` — client CŨ chưa biết field này vẫn an toàn (đọc
	 * `found` như trước, coi như not_found, KHÔNG BAO GIỜ tự chọn nhầm).
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_find( WP_REST_Request $request ) {
		$auth = TB247_DM_Auth::check_bearer( $request );

		if ( is_wp_error( $auth ) ) {
			return self::error_response_from_wp_error( $auth );
		}

		$result = TB247_DM_Deal_Service::locate_deal_safe(
			array(
				'marketplace' => (string) $request->get_param( 'marketplace' ),
				'asin'        => (string) $request->get_param( 'asin' ),
				'shop_code'   => (string) $request->get_param( 'shop_code' ),
				'item_code'   => (string) $request->get_param( 'item_code' ),
				'jan'         => (string) $request->get_param( 'jan' ),
				'source_url'  => (string) $request->get_param( 'source_url' ),
			)
		);

		if ( 'ambiguous' === $result['status'] ) {
			return new WP_REST_Response(
				array(
					'success'   => true,
					'found'     => false,
					'ambiguous' => true,
				),
				200
			);
		}

		if ( 'found' !== $result['status'] || ! $result['deal'] ) {
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
				'deal'    => self::deal_to_array( $result['deal'] ),
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

		$locate_result = TB247_DM_Deal_Service::locate_deal_safe(
			array(
				'marketplace' => isset( $payload['marketplace'] ) ? (string) $payload['marketplace'] : '',
				'asin'        => isset( $payload['asin'] ) ? (string) $payload['asin'] : '',
				'shop_code'   => isset( $payload['shop_code'] ) ? (string) $payload['shop_code'] : '',
				'item_code'   => isset( $payload['item_code'] ) ? (string) $payload['item_code'] : '',
				'jan'         => isset( $payload['jan'] ) ? (string) $payload['jan'] : '',
				'source_url'  => isset( $payload['source_url'] ) ? (string) $payload['source_url'] : '',
			)
		);

		// ambiguous (§10/§13): nhiều deal cùng JAN trong phạm vi tìm — KHÔNG
		// BAO GIỜ tự chọn 1 deal để cập nhật flags. 409 Conflict (khác 404
		// not_found) để Bot phân biệt được "chưa có deal" và "có nhưng mơ hồ,
		// cần identity chính xác hơn" — message JP đề xuất theo đúng handoff.
		if ( 'ambiguous' === $locate_result['status'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'ambiguous',
					'message' => __( 'Multiple deals share this JAN. Specify the exact shop/product URL.', 'tb247-deal-manager' ),
				),
				409
			);
		}

		$deal = $locate_result['deal'];

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
			// shop_code/item_code (§7/§11: trả lại identity chính xác Bot cần
			// cho lần refresh/flags tiếp theo — không bắt Bot tự re-parse URL
			// lại để lấy 2 field này). Rỗng cho Amazon (chỉ có asin/code).
			'shop_code'      => get_post_meta( $deal->ID, '_tb247_shop_code', true ),
			'item_code'      => get_post_meta( $deal->ID, '_tb247_item_code', true ),
			'source_url'     => get_post_meta( $deal->ID, '_tb247_product_url', true ),
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
