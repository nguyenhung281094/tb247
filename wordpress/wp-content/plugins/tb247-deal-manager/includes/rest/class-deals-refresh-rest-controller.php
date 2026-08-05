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
	 * PUT/PATCH /deals/refresh — update-only. Tìm deal theo JAN -> ASIN ->
	 * source_url (đúng thứ tự locate như /deals/flags). KHÔNG BAO GIỜ tạo deal
	 * mới, KHÔNG BAO GIỜ đụng is_recommended/is_sale/affiliate_url — payload có
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

		$deal = self::locate_deal(
			isset( $payload['jan'] ) ? (string) $payload['jan'] : '',
			isset( $payload['asin'] ) ? (string) $payload['asin'] : '',
			isset( $payload['source_url'] ) ? (string) $payload['source_url'] : ''
		);

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
	 * Tìm deal theo thứ tự ưu tiên: JAN -> ASIN -> source_url (chuẩn hoá) —
	 * cùng logic locate_deal() private của Deals_Lookup_Rest_Controller,
	 * nhân bản tối thiểu ở đây vì cả 2 method nguồn (find_by_jan/find_by_code/
	 * find_by_source_url) đều PUBLIC trên Deal_Service — không cần đụng file kia.
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
	 * Chuyển 1 deal post thành mảng dữ liệu trả về cho /deals/refresh — cùng
	 * shape với /deals/flags để Bot dùng chung 1 kiểu parse response.
	 *
	 * @param WP_Post $deal Deal post.
	 * @return array
	 */
	private static function deal_to_array( WP_Post $deal ) {
		return array(
			'post_id'                    => $deal->ID,
			'code'                       => get_post_meta( $deal->ID, '_tb247_asin', true ),
			'jan'                        => get_post_meta( $deal->ID, '_tb247_jan', true ),
			'product_name'               => $deal->post_title,
			'marketplace'                => get_post_meta( $deal->ID, '_tb247_marketplace', true ),
			'is_recommended'             => '1' === get_post_meta( $deal->ID, '_tb247_is_recommended', true ),
			'is_sale'                    => '1' === get_post_meta( $deal->ID, '_tb247_is_sale', true ),
			'recommended_updated_at'     => get_post_meta( $deal->ID, '_tb247_recommended_updated_at', true ),
			'url'                        => home_url( '/d/' . rawurlencode( get_post_meta( $deal->ID, '_tb247_asin', true ) ) ),
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
