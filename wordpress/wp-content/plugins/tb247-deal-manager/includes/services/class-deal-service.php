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

		update_post_meta( $post_id, '_tb247_last_updated', current_time( 'mysql' ) );
	}
}
