<?php
/**
 * Thiết lập cơ bản của theme: theme support, menu location.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'tb247_theme_setup' );

/**
 * Đăng ký theme support + vị trí menu chính.
 *
 * Nội dung menu (ホーム, 随時セール情報, おすすめ商品, お問い合わせ) được tạo trong
 * wp-admin (Trang + Menu), không hardcode trong theme.
 */
function tb247_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );

	register_nav_menus(
		array(
			'primary' => __( 'Menu chính (Header)', 'tb247-gadget-lab' ),
			'footer'  => __( 'Menu Footer', 'tb247-gadget-lab' ),
		)
	);
}
