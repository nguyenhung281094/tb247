<?php
/**
 * Đăng ký Custom Post Type "deal" và các meta field đi kèm.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deal_Post_Type {

	const POST_TYPE = 'deal';

	/**
	 * Danh sách meta field và sanitize callback tương ứng.
	 * Dùng chung cho register_post_meta() và Deal Service khi ghi dữ liệu.
	 *
	 * @return array<string, string> meta_key => sanitize callback
	 */
	public static function get_meta_schema() {
		return array(
			'_tb247_marketplace'    => 'sanitize_key',
			'_tb247_asin'           => 'sanitize_text_field',
			'_tb247_jan'            => 'sanitize_text_field',
			'_tb247_sale_price'     => 'absint',
			'_tb247_image'          => 'esc_url_raw',
			'_tb247_affiliate_url'  => 'esc_url_raw',
			'_tb247_product_url'    => 'esc_url_raw',
			'_tb247_brand'          => 'sanitize_text_field',
			'_tb247_in_stock'       => 'sanitize_text_field',
			'_tb247_is_recommended' => 'sanitize_text_field',
			'_tb247_is_sale'        => 'sanitize_text_field',
			'_tb247_last_updated'   => 'sanitize_text_field',
		);
	}

	/**
	 * Đăng ký CPT + meta field trên hook init.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Deals', 'tb247-deal-manager' ),
					'singular_name' => __( 'Deal', 'tb247-deal-manager' ),
					'add_new_item'  => __( 'Thêm Deal', 'tb247-deal-manager' ),
					'edit_item'     => __( 'Sửa Deal', 'tb247-deal-manager' ),
					'search_items'  => __( 'Tìm Deal', 'tb247-deal-manager' ),
					'not_found'     => __( 'Không tìm thấy Deal.', 'tb247-deal-manager' ),
				),
				// Deal không phải nội dung blog: không vào trang chủ/search/feed mặc định.
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'has_archive'         => false,
				// URL /d/{code} do TB247_DM_Deal_Rewrite tự quản lý hoàn toàn.
				'rewrite'             => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-cart',
				'supports'            => array( 'title', 'thumbnail' ),
				// Không dùng REST endpoint mặc định /wp/v2/deal — bot chỉ nói chuyện
				// qua REST Controller riêng của plugin (có auth bằng API key).
				'show_in_rest'        => false,
				'capability_type'     => 'post',
			)
		);

		self::register_meta();
	}

	/**
	 * Đăng ký từng meta field với sanitize callback đúng kiểu dữ liệu.
	 */
	private static function register_meta() {
		foreach ( self::get_meta_schema() as $meta_key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'absint' === $sanitize_callback ? 'integer' : 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize_callback,
				)
			);
		}
	}
}
