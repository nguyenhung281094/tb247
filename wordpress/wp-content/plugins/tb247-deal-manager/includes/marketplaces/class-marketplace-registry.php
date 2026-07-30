<?php
/**
 * Nơi đăng ký các sàn thương mại điện tử được hỗ trợ.
 *
 * Thêm sàn mới (Rakuten, Yahoo...) chỉ cần tạo class implement
 * TB247_DM_Marketplace rồi register() ở đây — không đụng REST Controller
 * hay Deal Service.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Marketplace_Registry {

	/**
	 * @var TB247_DM_Marketplace[]
	 */
	private static $marketplaces = array();

	/**
	 * Đăng ký một sàn thương mại điện tử.
	 *
	 * @param TB247_DM_Marketplace $marketplace Instance của sàn.
	 */
	public static function register( TB247_DM_Marketplace $marketplace ) {
		self::$marketplaces[ $marketplace->get_slug() ] = $marketplace;
	}

	/**
	 * Lấy marketplace theo slug.
	 *
	 * @param string $slug Slug sàn (vd: "amazon").
	 * @return TB247_DM_Marketplace|null
	 */
	public static function get( $slug ) {
		return isset( self::$marketplaces[ $slug ] ) ? self::$marketplaces[ $slug ] : null;
	}

	/**
	 * Lấy toàn bộ marketplace đã đăng ký.
	 *
	 * @return TB247_DM_Marketplace[]
	 */
	public static function all() {
		return self::$marketplaces;
	}
}
