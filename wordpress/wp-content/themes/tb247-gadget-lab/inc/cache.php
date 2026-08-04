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
 *
 * /recommended/ có thêm filter marketplace qua query string
 * (?marketplace=amazon|rakuten|yahoo) — LiteSpeed coi mỗi query string khác
 * nhau là 1 cache entry riêng, nên chỉ purge URL trần
 * (get_permalink($recommended_page)) KHÔNG đủ: 3 biến thể marketplace vẫn
 * giữ cache cũ. Purge thêm đúng 4 URL trang 1 (trần + 3 marketplace) — không
 * purge toàn site, không cần liệt kê hết các trang phân trang (sẽ tự hết hạn
 * theo TTL mặc định).
 */
function tb247_purge_deal_listing_cache() {
	$recommended_page = get_page_by_path( 'recommended' );
	$sale_page        = get_page_by_path( 'sale-info' );

	if ( $recommended_page ) {
		$recommended_url = get_permalink( $recommended_page );

		do_action( 'litespeed_purge_url', $recommended_url );

		foreach ( tb247_get_recommended_marketplace_slugs() as $marketplace_slug ) {
			do_action( 'litespeed_purge_url', add_query_arg( 'marketplace', $marketplace_slug, $recommended_url ) );
		}
	}

	if ( $sale_page ) {
		do_action( 'litespeed_purge_url', get_permalink( $sale_page ) );
	}
}
