<?php
/**
 * Referrer-Policy: strict-origin-when-cross-origin cho toàn site — khi rời
 * sang domain khác (Amazon/Rakuten/Yahoo...) chỉ gửi origin
 * (https://tb247deal.com), không gửi path /d/{code} hay query string nội bộ;
 * không gửi referrer khi HTTPS chuyển xuống HTTP.
 *
 * Chỉ dùng HTTP header (đủ hỗ trợ trên mọi trình duyệt hiện hành) — không
 * thêm <meta name="referrer"> để tránh 2 policy cùng tồn tại/mâu thuẫn.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

add_action( 'send_headers', 'tb247_send_referrer_policy_header' );

/**
 * Gửi header Referrer-Policy nếu chưa gửi header nào (an toàn khi gọi nhiều
 * lần trong cùng request).
 */
function tb247_send_referrer_policy_header() {
	if ( ! headers_sent() ) {
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
}
