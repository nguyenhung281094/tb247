<?php
/**
 * Logic tạo/cập nhật deal (upsert theo marketplace + code), tách khỏi REST Controller
 * để tái sử dụng được ở nơi khác nếu cần (vd: admin tool, import thủ công...).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deal_Service {

	/**
	 * Tìm deal theo code, không phân biệt marketplace.
	 * Dùng cho routing /d/{code} vì URL không mang tiền tố sàn ở v1.
	 *
	 * @param string $code Mã sản phẩm (vd: ASIN).
	 * @return WP_Post|null
	 */
	public static function find_by_code( $code ) {
		$query = new WP_Query(
			array(
				'post_type'      => TB247_DM_Deal_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_tb247_asin',
						'value' => strtoupper( $code ),
					),
				),
			)
		);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Tìm deal theo JAN — dùng cho slash command và tra cứu Rakuten/Yahoo
	 * (2 sàn chưa có ASIN, JAN là định danh chính).
	 *
	 * @param string $jan Mã JAN 8/13 số.
	 * @return WP_Post|null
	 */
	public static function find_by_jan( $jan ) {
		$jan = trim( (string) $jan );

		if ( '' === $jan ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => TB247_DM_Deal_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_tb247_jan',
						'value' => $jan,
					),
				),
			)
		);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Tìm deal theo source URL đã chuẩn hoá (bỏ query string/hash) — dùng khi
	 * Rakuten/Yahoo chưa có JAN nhưng deal đã từng lưu product_url gốc.
	 *
	 * @param string $source_url URL sản phẩm gốc (chưa chuẩn hoá).
	 * @return WP_Post|null
	 */
	public static function find_by_source_url( $source_url ) {
		$normalized = self::normalize_url( $source_url );

		if ( '' === $normalized ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => TB247_DM_Deal_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_tb247_product_url',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		foreach ( $query->posts as $candidate ) {
			$candidate_url = get_post_meta( $candidate->ID, '_tb247_product_url', true );

			if ( self::normalize_url( $candidate_url ) === $normalized ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Chuẩn hoá URL để so khớp: bỏ query string, hash, dấu "/" cuối, hạ thường host.
	 *
	 * @param string $url URL gốc.
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';

		return strtolower( $parts['host'] ) . $path;
	}

	/**
	 * Cập nhật cờ is_recommended/is_sale cho 1 deal đã tồn tại. Chỉ ghi field
	 * nào được truyền vào (không null) — field còn lại giữ nguyên giá trị cũ.
	 *
	 * @param int        $post_id       ID deal.
	 * @param bool|null  $is_recommended Giá trị mới, hoặc null để giữ nguyên.
	 * @param bool|null  $is_sale        Giá trị mới, hoặc null để giữ nguyên.
	 */
	public static function update_flags( $post_id, $is_recommended, $is_sale ) {
		if ( null !== $is_recommended ) {
			update_post_meta( $post_id, '_tb247_is_recommended', $is_recommended ? '1' : '0' );
		}

		if ( null !== $is_sale ) {
			update_post_meta( $post_id, '_tb247_is_sale', $is_sale ? '1' : '0' );
		}
	}

	/**
	 * Tìm deal theo đúng marketplace + code (dùng khi upsert để dedupe).
	 *
	 * @param string $marketplace Slug sàn.
	 * @param string $code        Mã sản phẩm.
	 * @return WP_Post|null
	 */
	public static function find_by_marketplace_and_code( $marketplace, $code ) {
		$query = new WP_Query(
			array(
				'post_type'      => TB247_DM_Deal_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_tb247_marketplace',
						'value' => $marketplace,
					),
					array(
						'key'   => '_tb247_asin',
						'value' => strtoupper( $code ),
					),
				),
			)
		);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Tạo mới hoặc cập nhật deal từ payload REST API đã qua validate.
	 *
	 * @param TB247_DM_Marketplace $marketplace Marketplace tương ứng payload.
	 * @param array                $payload     Dữ liệu đã validate.
	 * @return array{action: string, post_id: int, code: string}|WP_Error
	 */
	public static function create_or_update( TB247_DM_Marketplace $marketplace, array $payload ) {
		$code     = $marketplace->get_code( $payload );
		$existing = self::find_by_marketplace_and_code( $marketplace->get_slug(), $code );

		$post_arr = array(
			'post_type'   => TB247_DM_Deal_Post_Type::POST_TYPE,
			'post_title'  => sanitize_text_field( $payload['product_name'] ),
			'post_status' => 'publish',
		);

		if ( $existing ) {
			$post_arr['ID'] = $existing->ID;
			$post_id        = wp_update_post( $post_arr, true );
			$action         = 'updated';
		} else {
			$post_id = wp_insert_post( $post_arr, true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::write_meta( $post_id, $marketplace->get_slug(), $code, $payload );

		return array(
			'action'  => $action,
			'post_id' => $post_id,
			'code'    => $code,
		);
	}

	/**
	 * Ghi toàn bộ meta field, mỗi field sanitize đúng kiểu theo schema của CPT.
	 *
	 * @param int    $post_id     ID bài viết deal.
	 * @param string $marketplace Slug sàn.
	 * @param string $code        Mã sản phẩm đã chuẩn hoá.
	 * @param array  $payload     Payload gốc từ bot.
	 */
	private static function write_meta( $post_id, $marketplace, $code, array $payload ) {
		update_post_meta( $post_id, '_tb247_marketplace', sanitize_key( $marketplace ) );
		update_post_meta( $post_id, '_tb247_asin', $code );
		update_post_meta( $post_id, '_tb247_jan', sanitize_text_field( $payload['jan'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_sale_price', isset( $payload['sale_price'] ) ? absint( $payload['sale_price'] ) : 0 );
		update_post_meta( $post_id, '_tb247_image', esc_url_raw( $payload['image'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_affiliate_url', esc_url_raw( $payload['affiliate_url'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_product_url', esc_url_raw( $payload['product_url'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_brand', sanitize_text_field( $payload['brand'] ?? '' ) );

		// in_stock: chỉ ghi khi payload có giá trị true/false rõ ràng. Thiếu
		// field (payload cũ từ /deals, hoặc chưa xác định được từ bot) hoặc
		// giá trị null đều bị bỏ qua ở đây — giữ nguyên meta đã lưu trước đó
		// (hoặc để trống nếu là deal mới), Landing Page sẽ tự fallback về
		// hành vi hiển thị giá cũ khi meta rỗng.
		if ( array_key_exists( 'in_stock', $payload ) && null !== $payload['in_stock'] ) {
			update_post_meta( $post_id, '_tb247_in_stock', $payload['in_stock'] ? '1' : '0' );
		}

		// is_recommended/is_sale: CHỈ set mặc định '0' khi deal chưa từng có field
		// này (deal mới tạo). Nếu đã có (deal cũ sync lại), không đụng vào — giữ
		// nguyên giá trị admin/slash command đã đặt trước đó.
		if ( ! metadata_exists( 'post', $post_id, '_tb247_is_recommended' ) ) {
			update_post_meta( $post_id, '_tb247_is_recommended', '0' );
		}

		if ( ! metadata_exists( 'post', $post_id, '_tb247_is_sale' ) ) {
			update_post_meta( $post_id, '_tb247_is_sale', '0' );
		}

		update_post_meta( $post_id, '_tb247_last_updated', current_time( 'mysql' ) );
	}
}
