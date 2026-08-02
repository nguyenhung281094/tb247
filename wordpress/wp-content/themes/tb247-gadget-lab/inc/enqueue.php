<?php
/**
 * Nạp CSS/JS. deal.css và copy-jan.js chỉ nạp khi đang xem trang Landing Page
 * /d/{code} để các trang khác tải nhanh, không dư tài nguyên.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tb247_enqueue_assets' );
add_filter( 'wp_resource_hints', 'tb247_font_resource_hints', 10, 2 );

/**
 * Nạp style.css toàn site, font Google (Noto Sans JP + Space Grotesk), và
 * asset riêng cho Landing Page khi cần.
 */
function tb247_enqueue_assets() {
	$theme = wp_get_theme();

	// Font dùng chung toàn site, kể cả Landing Page (đồng bộ giao diện).
	wp_enqueue_style( 'tb247-fonts', tb247_get_fonts_url(), array(), null );

	// filemtime() thay vì Version cố định trong header style.css: version
	// query string phải đổi mỗi lần sửa CSS, nếu không CDN edge (cache riêng,
	// tách biệt LiteSpeed page cache, max-age 30 ngày) tiếp tục phục vụ bản cũ
	// dưới cùng URL dù đã purge LiteSpeed.
	wp_enqueue_style( 'tb247-style', get_stylesheet_uri(), array( 'tb247-fonts' ), filemtime( get_stylesheet_directory() . '/style.css' ) );

	if ( tb247_is_deal_page() ) {
		wp_enqueue_style( 'tb247-deal', TB247_THEME_URI . '/assets/css/deal.css', array( 'tb247-style' ), $theme->get( 'Version' ) );
		wp_enqueue_script( 'tb247-copy-jan', TB247_THEME_URI . '/assets/js/copy-jan.js', array(), $theme->get( 'Version' ), true );
	}
}

/**
 * URL Google Fonts: Noto Sans JP (nội dung tiếng Nhật) + Space Grotesk
 * (logotype/nhãn tiếng Anh). display=swap để không chặn hiển thị chữ.
 *
 * @return string
 */
function tb247_get_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&family=Space+Grotesk:wght@500;700&display=swap';
}

/**
 * Preconnect tới Google Fonts để giảm độ trễ tải font (giữ tinh thần "tải nhanh").
 *
 * @param array  $urls          Danh sách resource hint hiện có.
 * @param string $relation_type Loại hint (dns-prefetch, preconnect...).
 * @return array
 */
function tb247_font_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => '',
		);
	}

	return $urls;
}

/**
 * Xác định trang hiện tại có phải Landing Page deal không. Đọc query var qua
 * hằng số của plugin nếu plugin đang active (tránh lặp magic string ở 2 nơi);
 * fallback về literal string nếu vì lý do gì đó plugin chưa cài.
 *
 * @return bool
 */
function tb247_is_deal_page() {
	$query_var = class_exists( 'TB247_DM_Deal_Rewrite' ) ? TB247_DM_Deal_Rewrite::QUERY_VAR : 'tb247_deal_code';

	return (bool) get_query_var( $query_var );
}
