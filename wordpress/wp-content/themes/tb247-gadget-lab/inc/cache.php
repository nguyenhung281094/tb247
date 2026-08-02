<?php
/**
 * Purge LiteSpeed Cache cho 2 trang おすすめ商品/随時セール情報 ngay khi cờ
 * is_recommended/is_sale của 1 deal thay đổi (qua slash command Discord) —
 * để trang public cập nhật ngay thay vì chờ cache hết hạn (mặc định 7 ngày).
 *
 * Chỉ purge đúng 2 URL bị ảnh hưởng, không purge toàn site. Nếu LiteSpeed
 * Cache không được cài/active, do_action() dưới đây là no-op an toàn.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'tb247_deal_flags_changed', 'tb247_purge_deal_listing_cache' );

/**
 * @param int       $post_id        ID deal vừa đổi cờ (không cần dùng ở đây).
 * @param bool|null $is_recommended Giá trị mới (không cần dùng — luôn purge cả 2
 *                                  trang cho đơn giản, vì mỗi lần đổi cờ đều rẻ).
 * @param bool|null $is_sale        Giá trị mới.
 */
function tb247_purge_deal_listing_cache( $post_id, $is_recommended, $is_sale ) {
	unset( $post_id, $is_recommended, $is_sale );

	$recommended_page = get_page_by_path( 'recommended' );
	$sale_page        = get_page_by_path( 'sale-info' );

	if ( $recommended_page ) {
		do_action( 'litespeed_purge_url', get_permalink( $recommended_page ) );
	}

	if ( $sale_page ) {
		do_action( 'litespeed_purge_url', get_permalink( $sale_page ) );
	}
}
