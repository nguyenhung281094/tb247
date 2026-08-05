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
		$changed = false;

		if ( null !== $is_recommended ) {
			update_post_meta( $post_id, '_tb247_is_recommended', $is_recommended ? '1' : '0' );
			$changed = true;

			// 更新日 policy §8.1: lần đầu sản phẩm được đưa lên おすすめ商品 (bật
			// is_recommended=true) -> set 更新日 ngay. Chỉ set khi CHƯA có ngày —
			// không backfill/ghi đè ngày đã tồn tại (deal recommend lại sau khi
			// unrecommend giữ nguyên ngày cũ, không phải "lần đầu" theo nghĩa data).
			if ( $is_recommended && '' === trim( (string) get_post_meta( $post_id, '_tb247_recommended_updated_at', true ) ) ) {
				update_post_meta( $post_id, '_tb247_recommended_updated_at', current_time( 'mysql' ) );
			}
		}

		if ( null !== $is_sale ) {
			update_post_meta( $post_id, '_tb247_is_sale', $is_sale ? '1' : '0' );
			$changed = true;
		}

		if ( $changed ) {
			// Hook chung, không biết gì về LiteSpeed Cache hay slug trang cụ thể —
			// theme (nơi định nghĩa page-recommended.php/page-sale-info.php) tự
			// lắng nghe hook này để purge đúng URL bị ảnh hưởng.
			do_action( 'tb247_deal_flags_changed', $post_id, $is_recommended, $is_sale );
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
		update_post_meta( $post_id, '_tb247_shop_code', sanitize_text_field( $payload['shop_code'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_item_code', sanitize_text_field( $payload['item_code'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_jan', sanitize_text_field( $payload['jan'] ?? '' ) );
		update_post_meta( $post_id, '_tb247_sale_price', isset( $payload['sale_price'] ) ? absint( $payload['sale_price'] ) : 0 );
		update_post_meta( $post_id, '_tb247_image', esc_url_raw( $payload['image'] ?? '' ) );

		// affiliate_url: KHÔNG BAO GIỜ ghi đè bằng rỗng lên 1 deal ĐÃ tồn tại
		// (Rakuten find-or-create không có `aff` mới phải giữ nguyên affiliate
		// URL cũ — §4/§7). Chỉ ghi khi (a) deal vừa tạo mới (không có gì để giữ,
		// kể cả rỗng) hoặc (b) payload mang 1 affiliate_url không rỗng thật sự.
		$affiliate_provided = isset( $payload['affiliate_url'] ) && '' !== trim( (string) $payload['affiliate_url'] );
		$is_new_affiliate    = ! metadata_exists( 'post', $post_id, '_tb247_affiliate_url' );

		if ( $affiliate_provided || $is_new_affiliate ) {
			update_post_meta( $post_id, '_tb247_affiliate_url', esc_url_raw( $payload['affiliate_url'] ?? '' ) );
		}

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

	/**
	 * Quyết định thuần (không phụ thuộc WordPress runtime, test standalone được)
	 * có nên đổi 更新日 (_tb247_recommended_updated_at) hay không, theo đúng quy
	 * tắc 7 ngày (§8):
	 *   - dữ liệu không đổi -> không bao giờ đổi ngày.
	 *   - dữ liệu đổi + chưa từng có ngày -> đổi (set lần đầu sau refresh thành
	 *     công đầu tiên, KHÔNG backfill hàng loạt — hàm này chỉ được gọi khi đã
	 *     có 1 lần refresh/scrape thành công thật sự).
	 *   - dữ liệu đổi + đã có ngày nhưng CHƯA đủ 7 ngày -> giữ nguyên.
	 *   - dữ liệu đổi + đã có ngày và ĐÃ đủ 7 ngày -> đổi.
	 *
	 * @param bool        $data_changed       Dữ liệu (title/price/image/stock) có thay đổi thật không.
	 * @param string|null $existing_date_mysql Giá trị _tb247_recommended_updated_at hiện tại (chuỗi mysql datetime, hoặc rỗng/null nếu chưa có).
	 * @param int         $now_timestamp      Unix timestamp hiện tại (site-local), để test được xác định (không dùng time() trực tiếp bên trong).
	 * @return bool
	 */
	public static function should_update_recommended_date( $data_changed, $existing_date_mysql, $now_timestamp ) {
		if ( ! $data_changed ) {
			return false;
		}

		$existing_date_mysql = trim( (string) $existing_date_mysql );

		if ( '' === $existing_date_mysql ) {
			return true;
		}

		$existing_timestamp = strtotime( $existing_date_mysql );

		if ( false === $existing_timestamp ) {
			// Giá trị cũ hỏng/không parse được — coi như chưa có ngày hợp lệ, set lại.
			return true;
		}

		return ( $now_timestamp - $existing_timestamp ) >= ( 7 * DAY_IN_SECONDS );
	}

	/**
	 * Update-only refresh cho Quick Check/scheduler (§7/§8/§10/§11.2): cập nhật
	 * title/price/image/in_stock + _tb247_last_checked_at nếu dữ liệu hợp lệ
	 * (field nào KHÔNG có trong $payload giữ nguyên giá trị cũ — cùng convention
	 * với write_meta()/in_stock). KHÔNG BAO GIỜ đụng is_recommended/is_sale/
	 * affiliate_url/marketplace/asin/shop_code/item_code/product_url — hàm này
	 * chỉ đọc các field đó để so sánh, không ghi. 更新日 chỉ đổi theo đúng quy tắc
	 * 7 ngày (should_update_recommended_date()).
	 *
	 * @param int   $post_id ID deal ĐÃ xác nhận tồn tại (không tạo mới ở đây).
	 * @param array $payload Dữ liệu scrape mới: title/price/image/in_stock (field nào thiếu giữ nguyên).
	 * @return array{data_changed: bool, date_updated: bool}
	 */
	public static function refresh_recommended_data( $post_id, array $payload ) {
		$old_title = get_the_title( $post_id );
		$old_price = (int) get_post_meta( $post_id, '_tb247_sale_price', true );
		$old_image = get_post_meta( $post_id, '_tb247_image', true );
		$old_stock = get_post_meta( $post_id, '_tb247_in_stock', true );

		$has_new_title = isset( $payload['title'] ) && '' !== trim( (string) $payload['title'] );
		$has_new_price = isset( $payload['price'] ) && is_numeric( $payload['price'] ) && (float) $payload['price'] > 0;
		$has_new_image = isset( $payload['image'] ) && '' !== trim( (string) $payload['image'] );
		$has_new_stock = array_key_exists( 'in_stock', $payload ) && null !== $payload['in_stock'];

		$new_title = $has_new_title ? sanitize_text_field( (string) $payload['title'] ) : $old_title;
		$new_price = $has_new_price ? (int) round( (float) $payload['price'] ) : $old_price;
		$new_image = $has_new_image ? esc_url_raw( (string) $payload['image'] ) : $old_image;
		$new_stock = $has_new_stock ? ( $payload['in_stock'] ? '1' : '0' ) : $old_stock;

		$data_changed = ( $new_title !== $old_title )
			|| ( $new_price !== $old_price )
			|| ( $new_image !== $old_image )
			|| ( $new_stock !== $old_stock );

		if ( $has_new_title ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $new_title,
				)
			);
		}

		if ( $has_new_price ) {
			update_post_meta( $post_id, '_tb247_sale_price', $new_price );
		}

		if ( $has_new_image ) {
			update_post_meta( $post_id, '_tb247_image', $new_image );
		}

		if ( $has_new_stock ) {
			update_post_meta( $post_id, '_tb247_in_stock', $new_stock );
		}

		// last_checked_at: LUÔN cập nhật khi refresh chạy thành công tới đây
		// (kể cả không đổi gì) — khác 更新日, đây chỉ là dấu vết "đã kiểm tra lúc
		// nào", không phải "đã đổi nội dung lúc nào". last_updated dùng chung cho
		// orderby của lưới /recommended/ nên cũng cập nhật theo, giữ đúng hành vi
		// sắp xếp gốc (mới nhất lên trước) đã có từ create_or_update().
		$now = current_time( 'mysql' );
		update_post_meta( $post_id, '_tb247_last_checked_at', $now );
		update_post_meta( $post_id, '_tb247_last_updated', $now );

		$existing_date = get_post_meta( $post_id, '_tb247_recommended_updated_at', true );
		$date_updated  = self::should_update_recommended_date( $data_changed, $existing_date, current_time( 'timestamp' ) );

		if ( $date_updated ) {
			update_post_meta( $post_id, '_tb247_recommended_updated_at', $now );
		}

		return array(
			'data_changed' => $data_changed,
			'date_updated' => $date_updated,
		);
	}

	/**
	 * Lấy danh sách deal đang recommended "due" cho scheduler (§10/§11.3): chưa
	 * từng có 更新日 HOẶC đã >=7 ngày, sắp xếp lâu-chưa-cập-nhật-nhất trước (deal
	 * chưa từng có ngày coi là lâu nhất — ưu tiên đầu danh sách). CHỈ đọc, không
	 * ghi gì — endpoint gọi hàm này không được tạo/xoá/đổi flags/đổi affiliate.
	 *
	 * @param int $limit Số lượng tối đa trả về (đã clamp ở caller).
	 * @return WP_Post[]
	 */
	public static function find_due_recommended_deals( $limit ) {
		$limit = max( 1, (int) $limit );

		// Lấy 1 pool rộng hơn giới hạn thật (nhưng có trần cứng) rồi tự sort bằng
		// PHP theo epoch (deal chưa có ngày = 0, luôn lâu nhất) — tránh phụ thuộc
		// hành vi orderby meta_value của WP_Query khi meta_query trộn nhánh
		// NOT EXISTS/rỗng/so sánh ngày (không đảm bảo thứ tự nhất quán giữa các
		// nhánh khác nhau của cùng 1 OR clause).
		$pool_size = min( 200, max( $limit * 5, 60 ) );
		$now       = current_time( 'timestamp' );
		$threshold = gmdate( 'Y-m-d H:i:s', $now - ( 7 * DAY_IN_SECONDS ) );

		$query = new WP_Query(
			array(
				'post_type'      => TB247_DM_Deal_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $pool_size,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_tb247_is_recommended',
						'value' => '1',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_tb247_recommended_updated_at',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_tb247_recommended_updated_at',
							'value'   => '',
							'compare' => '=',
						),
						array(
							'key'     => '_tb247_recommended_updated_at',
							'value'   => $threshold,
							'compare' => '<=',
							'type'    => 'DATETIME',
						),
					),
				),
			)
		);

		$posts = $query->posts;

		usort(
			$posts,
			static function ( $a, $b ) {
				$a_date = trim( (string) get_post_meta( $a->ID, '_tb247_recommended_updated_at', true ) );
				$b_date = trim( (string) get_post_meta( $b->ID, '_tb247_recommended_updated_at', true ) );

				$a_ts = ( '' === $a_date ) ? 0 : strtotime( $a_date );
				$b_ts = ( '' === $b_date ) ? 0 : strtotime( $b_date );

				if ( false === $a_ts ) {
					$a_ts = 0;
				}
				if ( false === $b_ts ) {
					$b_ts = 0;
				}

				return $a_ts <=> $b_ts;
			}
		);

		return array_slice( $posts, 0, $limit );
	}
}
