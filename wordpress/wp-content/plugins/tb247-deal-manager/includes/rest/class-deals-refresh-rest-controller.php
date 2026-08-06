<?php
/**
 * REST endpoint dành riêng cho Quick Check update-only (§7) và Scheduler tự
 * động refresh (§10): PUT /deals/refresh cập nhật title/price/image/stock cho
 * 1 deal ĐÃ TỒN TẠI (không tạo mới, không đổi flags, không đổi affiliate_url),
 * GET /deals/due liệt kê deal recommended đang "due" cho scheduler.
 *
 * Endpoint MỚI, KHÔNG thay thế /products hay /deals/find, /deals/flags — dùng
 * chung TB247_DM_Auth::check_bearer() (cùng Bearer token TB247_API_TOKEN) và
 * TB247_DM_Deal_Service, không viết lại logic tìm/ghi deal.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deals_Refresh_Rest_Controller {

	const NAMESPACE_ = 'tb247/v1';

	/**
	 * Giới hạn cứng cho tham số limit của /deals/due — không cho client yêu cầu
	 * vượt quá, tránh 1 request kéo cả bảng deal.
	 */
	const MAX_DUE_LIMIT = 100;

	/**
	 * Đăng ký route trên hook rest_api_init.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/deals/refresh',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'handle_refresh' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/deals/due',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_due' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * PUT/PATCH /deals/refresh — update-only. Tìm deal qua
	 * TB247_DM_Deal_Service::locate_deal_safe() — unique key CHÍNH XÁC theo
	 * marketplace trước (asin cho Amazon, shop_code+item_code cho Rakuten/
	 * Yahoo), JAN chỉ dùng như fallback cuối và luôn SCOPED theo marketplace
	 * (không bao giờ cập nhật nhầm deal khác marketplace/shop chỉ vì trùng
	 * JAN — xem docblock locate_deal_safe()). KHÔNG BAO GIỜ tạo deal mới,
	 * KHÔNG BAO GIỜ đụng is_recommended/is_sale/affiliate_url — payload có
	 * gửi các field đó cũng bị bỏ qua hoàn toàn ở tầng Deal_Service (không đọc
	 * tới), không phải chỉ "không cố ý dùng".
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_refresh( WP_REST_Request $request ) {
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

		// ambiguous (§10/§12): nhiều deal cùng JAN trong phạm vi tìm — refresh
		// TUYỆT ĐỐI không được đoán/update nhầm deal khác marketplace/shop.
		if ( 'ambiguous' === $locate_result['status'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'ambiguous',
					'message' => __( 'Multiple deals share this JAN. Specify the exact shop/product identity.', 'tb247-deal-manager' ),
				),
				409
			);
		}

		$deal = $locate_result['deal'];

		if ( ! $deal ) {
			// KHÔNG tạo deal — đúng hành vi update-only §7/§11.2.
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'not_found',
					'message' => __( 'Deal not found. Refresh is update-only and never creates a new deal.', 'tb247-deal-manager' ),
				),
				404
			);
		}

		$result = TB247_DM_Deal_Service::refresh_recommended_data( $deal->ID, $payload );

		$deal = get_post( $deal->ID );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'data_changed' => $result['data_changed'],
				'date_updated' => $result['date_updated'],
				'deal'         => self::deal_to_array( $deal ),
			),
			200
		);
	}

	/**
	 * GET /deals/due?limit=N — danh sách deal recommended đang due cho
	 * scheduler (§10/§11.3). Chỉ authenticated mới gọi được (cùng Bearer token
	 * Bot đang dùng) — không public dữ liệu nội bộ. KHÔNG trả affiliate_url.
	 *
	 * @param WP_REST_Request $request Request thô, chưa qua xác thực.
	 * @return WP_REST_Response
	 */
	public static function handle_due( WP_REST_Request $request ) {
		$auth = TB247_DM_Auth::check_bearer( $request );

		if ( is_wp_error( $auth ) ) {
			return self::error_response_from_wp_error( $auth );
		}

		$raw_limit = $request->get_param( 'limit' );
		$limit     = ( null !== $raw_limit && is_numeric( $raw_limit ) ) ? (int) $raw_limit : 30;
		$limit     = max( 1, min( self::MAX_DUE_LIMIT, $limit ) );

		$deals = TB247_DM_Deal_Service::find_due_recommended_deals( $limit );

		$items = array();

		foreach ( $deals as $deal ) {
			$items[] = self::due_deal_to_array( $deal );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => count( $items ),
				'deals'   => $items,
			),
			200
		);
	}

	/**
	 * Chuyển 1 deal post thành mảng dữ liệu trả về cho /deals/refresh — cùng
	 * shape với /deals/flags để Bot dùng chung 1 kiểu parse response.
	 *
	 * @param WP_Post $deal Deal post.
	 * @return array
	 */
	private static function deal_to_array( WP_Post $deal ) {
		return array(
			'post_id'                => $deal->ID,
			'code'                   => get_post_meta( $deal->ID, '_tb247_asin', true ),
			'jan'                    => get_post_meta( $deal->ID, '_tb247_jan', true ),
			'shop_code'              => get_post_meta( $deal->ID, '_tb247_shop_code', true ),
			'item_code'              => get_post_meta( $deal->ID, '_tb247_item_code', true ),
			'source_url'             => get_post_meta( $deal->ID, '_tb247_product_url', true ),
			'product_name'           => $deal->post_title,
			'marketplace'            => get_post_meta( $deal->ID, '_tb247_marketplace', true ),
			'is_recommended'         => '1' === get_post_meta( $deal->ID, '_tb247_is_recommended', true ),
			'is_sale'                => '1' === get_post_meta( $deal->ID, '_tb247_is_sale', true ),
			'recommended_updated_at' => get_post_meta( $deal->ID, '_tb247_recommended_updated_at', true ),
			'url'                    => home_url( '/d/' . rawurlencode( get_post_meta( $deal->ID, '_tb247_asin', true ) ) ),
		);
	}

	/**
	 * Chuyển 1 deal post thành mảng dữ liệu cho /deals/due — đủ để Bot tự re-scrape
	 * (JAN/ASIN/shop_code/item_code/product_url/marketplace), KHÔNG trả
	 * affiliate_url (§11.3: không public dữ liệu nội bộ hơn mức cần).
	 *
	 * @param WP_Post $deal Deal post.
	 * @return array
	 */
	private static function due_deal_to_array( WP_Post $deal ) {
		return array(
			'post_id'                => $deal->ID,
			'marketplace'            => get_post_meta( $deal->ID, '_tb247_marketplace', true ),
			'asin'                   => get_post_meta( $deal->ID, '_tb247_asin', true ),
			'jan'                    => get_post_meta( $deal->ID, '_tb247_jan', true ),
			'shop_code'              => get_post_meta( $deal->ID, '_tb247_shop_code', true ),
			'item_code'              => get_post_meta( $deal->ID, '_tb247_item_code', true ),
			'product_url'            => get_post_meta( $deal->ID, '_tb247_product_url', true ),
			'recommended_updated_at' => get_post_meta( $deal->ID, '_tb247_recommended_updated_at', true ),
		);
	}

	/**
	 * Chuyển WP_Error từ bước xác thực thành đúng JSON shape, giống /products
	 * và /deals/find — không lộ nội dung message kỹ thuật nội bộ ra response.
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
