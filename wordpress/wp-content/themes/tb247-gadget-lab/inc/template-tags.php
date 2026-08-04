<?php
/**
 * Helper hiển thị dùng chung giữa các template.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lấy các mục trong Menu chính (trừ mục trỏ về Trang chủ) để hiển thị dạng
 * card dẫn hướng trên trang chủ. Tiêu đề + mô tả lấy trực tiếp từ Menu item
 * trong wp-admin (Appearance → Menus → bật "Description" ở Screen Options)
 * — không hardcode tên trang hay slug ở đây, admin đổi menu là card tự đổi theo.
 *
 * @return WP_Post[] Danh sách nav menu item (rỗng nếu chưa có Menu chính).
 */
function tb247_get_home_link_cards() {
	$locations = get_nav_menu_locations();

	if ( empty( $locations['primary'] ) ) {
		return array();
	}

	$menu_items = wp_get_nav_menu_items( $locations['primary'] );

	if ( ! $menu_items ) {
		return array();
	}

	$home_url = untrailingslashit( home_url( '/' ) );
	$cards    = array();

	foreach ( $menu_items as $item ) {
		if ( untrailingslashit( $item->url ) === $home_url ) {
			continue;
		}

		$cards[] = $item;
	}

	return $cards;
}

/**
 * Marketplace slug hợp lệ cho filter /recommended/ — allowlist chính xác
 * khớp với giá trị thật lưu trong _tb247_marketplace (xem
 * TB247_DM_Deal_Service::write_meta() — sanitize_key($marketplace) của
 * marketplace slug thật: amazon/rakuten/yahoo). KHÔNG dùng wildcard, không
 * nhận taxonomy/meta key từ GET.
 *
 * @return string[]
 */
function tb247_get_recommended_marketplace_slugs() {
	return array( 'amazon', 'rakuten', 'yahoo' );
}

/**
 * Nhãn hiển thị cho từng lựa chọn filter marketplace trên /recommended/,
 * bao gồm cả "すべて" (không phải marketplace thật, chỉ là lựa chọn UI) —
 * dùng chung cho cả sidebar desktop và tab ngang mobile để không lặp danh
 * sách 2 nơi. Amazon/Rakuten/Yahoo tái sử dụng đúng tb247_marketplace_label().
 *
 * @return array<int, array{slug: string, label: string}> slug rỗng = すべて.
 */
function tb247_get_recommended_filter_options() {
	$options = array(
		array(
			'slug'  => '',
			'label' => __( 'すべて', 'tb247-gadget-lab' ),
		),
	);

	foreach ( tb247_get_recommended_marketplace_slugs() as $slug ) {
		$options[] = array(
			'slug'  => $slug,
			'label' => tb247_marketplace_label( $slug ),
		);
	}

	return $options;
}

/**
 * Sanitize + validate giá trị marketplace filter thô (vd từ $_GET['marketplace'])
 * — chỉ chấp nhận đúng 1 trong allowlist qua so khớp exact (in_array strict),
 * không dùng trực tiếp trong SQL/meta_query nếu không qua bước này trước.
 * Mọi giá trị không hợp lệ (kể cả rỗng/thiếu) fallback về '' (すべて) — không
 * bao giờ Fatal, không tạo meta_query tuỳ ý.
 *
 * @param string $raw Giá trị thô, chưa qua xử lý.
 * @return string '' (すべて) hoặc 1 trong tb247_get_recommended_marketplace_slugs().
 */
function tb247_sanitize_recommended_marketplace( $raw ) {
	$slug = sanitize_key( (string) $raw );

	return in_array( $slug, tb247_get_recommended_marketplace_slugs(), true ) ? $slug : '';
}

/**
 * Query deal đang bật is_recommended, lọc theo marketplace (nếu có) + phân
 * trang 12 sản phẩm/trang — TÁCH RIÊNG khỏi tb247_query_deals_by_flag() (vẫn
 * dùng nguyên cho /sale-info/, không marketplace filter/pagination) để không
 * đổi hành vi trang đó. $marketplace PHẢI đã qua
 * tb247_sanitize_recommended_marketplace() trước khi gọi hàm này.
 *
 * @param string $marketplace '' (すべて) hoặc 1 trong allowlist marketplace.
 * @param int    $paged       Trang hiện tại, >=1.
 * @return WP_Query
 */
function tb247_query_recommended_deals( $marketplace, $paged ) {
	$paged = max( 1, (int) $paged );

	$meta_query = array(
		array(
			'key'   => '_tb247_is_recommended',
			'value' => '1',
		),
	);

	if ( '' !== $marketplace ) {
		$meta_query[] = array(
			'key'   => '_tb247_marketplace',
			'value' => $marketplace,
		);
	}

	return new WP_Query(
		array(
			'post_type'      => 'deal',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'paged'          => $paged,
			'orderby'        => 'meta_value',
			'meta_key'       => '_tb247_last_updated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'DESC',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
}

/**
 * Tính danh sách "trang cần hiển thị" cho pagination kiểu
 * ‹ 前へ 1 2 … 9 次へ › — luôn hiện $edge trang đầu/cuối + $around trang lân
 * cận trang hiện tại, chèn chuỗi '...' (không phải số) khi có khoảng trống.
 * Thuần, không phụ thuộc WP_Query/WordPress — test độc lập được.
 *
 * @param int $current     Trang hiện tại (>=1).
 * @param int $total_pages Tổng số trang (>=0; 0/1 nghĩa là không cần pagination).
 * @param int $edge        Số trang luôn hiện ở đầu và cuối.
 * @param int $around      Số trang lân cận current mỗi bên.
 * @return array<int, int|string> Rỗng nếu total_pages <= 1.
 */
function tb247_build_pagination_window( $current, $total_pages, $edge = 1, $around = 1 ) {
	$current     = max( 1, (int) $current );
	$total_pages = max( 0, (int) $total_pages );

	if ( $total_pages <= 1 ) {
		return array();
	}

	$pages = array();

	for ( $i = 1; $i <= $total_pages; $i++ ) {
		$is_edge   = ( $i <= $edge ) || ( $i > $total_pages - $edge );
		$is_around = ( $i >= $current - $around ) && ( $i <= $current + $around );

		if ( $is_edge || $is_around ) {
			$pages[] = $i;
		} elseif ( empty( $pages ) || '...' !== end( $pages ) ) {
			$pages[] = '...';
		}
	}

	return $pages;
}

/**
 * Ghép URL cho 1 lựa chọn filter/trang trên /recommended/ — query string
 * thuần (?marketplace=...&paged=...), không phụ thuộc rewrite rule riêng,
 * tránh nhập nhằng $paged vs get_query_var('page') vốn chỉ dành cho URL dạng
 * /page/N/ trên static Page. paged=1 hoặc marketplace='' không được thêm
 * vào query string (giữ URL sạch, /recommended/ trần cho "tất cả trang 1").
 *
 * @param string $base_url    Permalink gốc của trang /recommended/.
 * @param string $marketplace '' hoặc 1 trong allowlist marketplace.
 * @param int    $paged       Trang đích, >=1.
 * @return string URL đã qua add_query_arg() (cần esc_url() khi echo).
 */
function tb247_build_recommended_url( $base_url, $marketplace, $paged ) {
	$args = array();

	if ( '' !== $marketplace ) {
		$args['marketplace'] = $marketplace;
	}

	if ( (int) $paged > 1 ) {
		$args['paged'] = (int) $paged;
	}

	return empty( $args ) ? $base_url : add_query_arg( $args, $base_url );
}

/**
 * Lấy danh sách deal đang bật 1 cờ (is_recommended/is_sale) để hiển thị trên
 * trang おすすめ商品 / 随時セール情報. Không đụng tới Landing Page /d/{code}.
 *
 * @param string $meta_key '_tb247_is_recommended' hoặc '_tb247_is_sale'.
 * @return WP_Post[] Danh sách deal (rỗng nếu chưa có deal nào được đánh dấu).
 */
function tb247_query_deals_by_flag( $meta_key ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'deal',
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'no_found_rows'  => true,
			'orderby'        => 'meta_value',
			'meta_key'       => '_tb247_last_updated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => $meta_key,
					'value' => '1',
				),
			),
		)
	);

	return $query->posts;
}

/**
 * Nhãn hiển thị cho marketplace — chỉ Amazon đang hoạt động ở v1, nhưng để
 * sẵn cho Rakuten/Yahoo sau này không cần sửa template.
 *
 * @param string $marketplace Slug sàn lưu trong _tb247_marketplace.
 * @return string
 */
function tb247_marketplace_label( $marketplace ) {
	$labels = array(
		'amazon'  => 'Amazon',
		'rakuten' => '楽天市場',
		'yahoo'   => 'Yahoo!ショッピング',
	);

	return $labels[ $marketplace ] ?? ucfirst( (string) $marketplace );
}

/**
 * Thuộc tính chuẩn cho mọi link CTA dẫn ra sàn thương mại điện tử ngoài site
 * (Amazon hiện tại; Rakuten/Yahoo sau này) — dùng chung để không hardcode
 * rel/target rải rác nhiều nơi.
 *
 * Amazon (mặc định, kể cả khi gọi không truyền $marketplace hoặc meta rỗng —
 * giữ đúng hành vi production hiện tại) giữ "nofollow" — không đổi để không
 * ảnh hưởng CTA Amazon đang chạy. noreferrer không ảnh hưởng attribution vì
 * Amazon Associates track qua tham số ?tag= gắn thẳng trong URL, không dựa
 * vào HTTP Referer header. Sàn khác dùng rel mặc định không có nofollow.
 *
 * @param string $marketplace Slug sàn (vd: "amazon", "rakuten"). Rỗng = amazon.
 * @return array{target: string, rel: string}
 */
function tb247_get_marketplace_link_attributes( $marketplace = 'amazon' ) {
	$marketplace = sanitize_key( (string) $marketplace );

	if ( '' === $marketplace || 'amazon' === $marketplace ) {
		return array(
			'target' => '_blank',
			'rel'    => 'sponsored noopener noreferrer nofollow',
		);
	}

	return array(
		'target' => '_blank',
		'rel'    => 'sponsored noopener noreferrer',
	);
}

/**
 * Text nút mua theo đúng sàn. Amazon (mặc định, kể cả marketplace rỗng/legacy
 * — giữ đúng hành vi hiện tại) giữ nguyên "Amazon". Rakuten hiện "RAKUTEN"
 * (chuẩn hoá CTA — không dùng "楽天で購入" nữa).
 *
 * @param string $marketplace Slug sàn (vd: "amazon", "rakuten").
 * @return string
 */
function tb247_get_marketplace_buy_button_label( $marketplace = 'amazon' ) {
	$marketplace = sanitize_key( (string) $marketplace );

	$labels = array(
		'amazon'  => 'Amazon',
		'rakuten' => 'RAKUTEN',
	);

	return isset( $labels[ $marketplace ] ) ? $labels[ $marketplace ] : 'Amazon';
}

/**
 * In 1 card sản phẩm cho trang danh sách (おすすめ商品/随時セール情報). Dùng chung
 * quy tắc ẩn giá khi hết hàng như single-deal.php: chỉ '0' (_tb247_in_stock)
 * mới ép ẩn giá, mọi giá trị khác giữ hành vi cũ (theo giá > 0).
 *
 * @param WP_Post $deal Deal post.
 */
function tb247_the_deal_card( $deal ) {
	$deal_id       = $deal->ID;
	$code          = get_post_meta( $deal_id, '_tb247_asin', true );
	$jan           = get_post_meta( $deal_id, '_tb247_jan', true );
	$price         = (int) get_post_meta( $deal_id, '_tb247_sale_price', true );
	$image         = get_post_meta( $deal_id, '_tb247_image', true );
	$marketplace   = get_post_meta( $deal_id, '_tb247_marketplace', true );
	$in_stock_meta = get_post_meta( $deal_id, '_tb247_in_stock', true );
	$show_price    = ( '0' !== $in_stock_meta ) && ( $price > 0 );
	$landing_url   = home_url( '/d/' . rawurlencode( $code ) . '/' );
	?>
	<a class="tb247-deal-grid-card" href="<?php echo esc_url( $landing_url ); ?>">
		<div class="tb247-deal-grid-media">
			<span class="tb247-deal-grid-badge"><?php echo esc_html( tb247_marketplace_label( $marketplace ) ); ?></span>
			<?php if ( $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $deal_id ) ); ?>" loading="lazy" />
			<?php endif; ?>
		</div>

		<div class="tb247-deal-grid-body">
			<p class="tb247-deal-grid-title"><?php echo esc_html( get_the_title( $deal_id ) ); ?></p>

			<?php if ( $show_price ) : ?>
				<p class="tb247-deal-grid-price">&yen;<?php echo esc_html( number_format_i18n( $price ) ); ?></p>
			<?php endif; ?>

			<?php if ( $jan ) : ?>
				<p class="tb247-deal-grid-jan">JAN: <?php echo esc_html( $jan ); ?></p>
			<?php endif; ?>
		</div>
	</a>
	<?php
}

/**
 * In thẻ <picture> logo (WebP + PNG fallback), dùng chung cho Header/Footer.
 * File gốc giữ nguyên 100% (không crop/đổi màu/đổi chi tiết) — kích thước
 * hiển thị chỉnh hoàn toàn bằng CSS qua class truyền vào, ảnh luôn co theo
 * đúng tỉ lệ gốc (width/height khai báo đúng tỉ lệ vuông của file nguồn).
 *
 * @param string $class   Class CSS bọc ngoài để CSS định kích thước hiển thị.
 * @param string $loading Giá trị thuộc tính loading ("eager" cho Header vì
 *                         nằm ngay đầu trang, "lazy" cho Footer).
 */
function tb247_the_logo_mark( $class, $loading = 'eager' ) {
	$png  = TB247_THEME_URI . '/assets/images/logo.png';
	$webp = TB247_THEME_URI . '/assets/images/logo.webp';
	?>
	<picture class="<?php echo esc_attr( $class ); ?>">
		<source srcset="<?php echo esc_url( $webp ); ?>" type="image/webp" />
		<img
			src="<?php echo esc_url( $png ); ?>"
			alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
			width="512"
			height="512"
			loading="<?php echo esc_attr( $loading ); ?>"
			decoding="async"
		/>
	</picture>
	<?php
}
