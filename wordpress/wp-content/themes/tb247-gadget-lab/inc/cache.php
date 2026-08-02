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

// $accepted_args = 0: do_action() bên plugin gọi kèm 3 tham số nhưng hàm này
// không cần dùng tham số nào (luôn purge cả 2 trang cho đơn giản, vì mỗi lần
// đổi cờ đều rẻ) — khai báo accepted_args=0 để không phụ thuộc đúng số lượng/
// thứ tự tham số phía gọi, tránh lỗi ArgumentCountError nếu phía plugin đổi sau này.
add_action( 'tb247_deal_flags_changed', 'tb247_purge_deal_listing_cache', 10, 0 );

/**
 * Purge cache cho cả 2 trang listing — không nhận tham số nào.
 */
function tb247_purge_deal_listing_cache() {
	$recommended_page = get_page_by_path( 'recommended' );
	$sale_page        = get_page_by_path( 'sale-info' );

	if ( $recommended_page ) {
		do_action( 'litespeed_purge_url', get_permalink( $recommended_page ) );
	}

	if ( $sale_page ) {
		do_action( 'litespeed_purge_url', get_permalink( $sale_page ) );
	}
}
