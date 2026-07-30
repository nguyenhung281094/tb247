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
