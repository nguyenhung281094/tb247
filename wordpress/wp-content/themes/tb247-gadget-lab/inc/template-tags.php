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
