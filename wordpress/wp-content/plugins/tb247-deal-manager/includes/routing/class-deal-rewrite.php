<?php
/**
 * Routing cho Landing Page: /d/{code} — không dùng ?p=123, không phụ thuộc post_name/slug
 * (tránh WordPress tự hạ chữ thường làm sai lệch mã ASIN gốc).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deal_Rewrite {

	const QUERY_VAR = 'tb247_deal_code';

	/**
	 * Đăng ký toàn bộ hook liên quan routing.
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
	}

	/**
	 * /d/{code} -> index.php?tb247_deal_code={code}
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule( '^d/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * @param array $vars Danh sách query var hiện có.
	 * @return array
	 */
	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Nếu URL khớp /d/{code}: tìm deal, set làm post hiện tại, và chọn template.
	 * Theme được ưu tiên override qua single-deal.php; nếu không có, dùng
	 * template dự phòng của plugin để trang vẫn hoạt động dù đổi theme.
	 *
	 * @param string $template Template WordPress định dùng mặc định.
	 * @return string
	 */
	public static function template_include( $template ) {
		$code = get_query_var( self::QUERY_VAR );

		if ( empty( $code ) ) {
			return $template;
		}

		$deal = TB247_DM_Deal_Service::find_by_code( $code );

		if ( ! $deal ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_query_template( '404' );
		}

		self::prime_main_query( $deal );

		$theme_template = locate_template( 'single-deal.php' );

		return $theme_template ? $theme_template : TB247_DM_PLUGIN_DIR . 'templates/single-deal-fallback.php';
	}

	/**
	 * Ghi đè main $wp_query để trỏ đúng vào deal tìm được, để the_post()/have_posts()/
	 * is_singular() trong template hoạt động đúng như một trang single thật sự
	 * (thay vì chỉ set global $post, vốn không đủ để have_posts() nhận diện).
	 *
	 * @param WP_Post $deal Deal đã tìm thấy theo code trong URL.
	 */
	private static function prime_main_query( $deal ) {
		global $wp_query, $post;

		$post = $deal; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$wp_query->post              = $deal;
		$wp_query->posts             = array( $deal );
		$wp_query->queried_object    = $deal;
		$wp_query->queried_object_id = $deal->ID;
		$wp_query->found_posts       = 1;
		$wp_query->post_count        = 1;
		$wp_query->max_num_pages     = 0;
		$wp_query->current_post      = -1;

		$wp_query->is_single   = true;
		$wp_query->is_singular = true;
		$wp_query->is_home     = false;
		$wp_query->is_archive  = false;
		$wp_query->is_category = false;
		$wp_query->is_page     = false;
		$wp_query->is_404      = false;

		setup_postdata( $deal );
	}

	/**
	 * Chạy khi activate plugin: đăng ký rule rồi flush ngay để /d/{code} hoạt động
	 * mà không cần vào tay Settings → Permalinks.
	 */
	public static function activate() {
		self::add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * Dọn rewrite rule khi deactivate để không để lại rác trong .htaccess/rules cache.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
