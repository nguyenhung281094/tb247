<?php
/**
 * Bootstrap chính: đăng ký toàn bộ hook của plugin.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Plugin {

	/**
	 * Gắn toàn bộ hook cần thiết. Gọi 1 lần từ file chính của plugin.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_marketplaces' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_cpt' ), 10 );

		TB247_DM_Deal_Rewrite::register_hooks();
		TB247_DM_Deal_SEO::register_hooks();

		add_action( 'rest_api_init', array( 'TB247_DM_Rest_Controller', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'TB247_DM_Products_Rest_Controller', 'register_routes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_permalink_notice' ) );
	}

	/**
	 * Đăng ký các marketplace được hỗ trợ. Rakuten/Yahoo sau này chỉ cần
	 * thêm 1 dòng register() ở đây.
	 */
	public static function register_marketplaces() {
		TB247_DM_Marketplace_Registry::register( new TB247_DM_Amazon_Marketplace() );
	}

	/**
	 * Đăng ký Custom Post Type "deal".
	 */
	public static function register_cpt() {
		TB247_DM_Deal_Post_Type::register();
	}

	/**
	 * Nhắc admin bật Permalinks dạng đẹp (không phải "Plain") vì /d/{code}
	 * cần rewrite rule hoạt động.
	 */
	public static function maybe_permalink_notice() {
		if ( '' === get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'TB247 Deal Manager cần bật Permalinks dạng "Post name" (Settings → Permalinks) để Landing Page /d/{ASIN} hoạt động.', 'tb247-deal-manager' ) .
				'</p></div>';
		}
	}
}
