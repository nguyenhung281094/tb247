<?php
/**
 * Test standalone cho filter marketplace + pagination trên /recommended/ —
 * chạy độc lập ngoài WordPress, chỉ stub sanitize_key()/__()/add_query_arg()
 * (3 hàm WP duy nhất mà các helper thuần trong inc/template-tags.php cần).
 * Không test WP_Query thật (cần DB) — phần đó xác nhận qua PHP lint +
 * structural check (đọc source) + production test (curl) sau deploy.
 *
 * Chạy: php tests/recommended-filter-test.php
 *
 * @package TB247_Gadget_Lab
 */

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function __( $text, $domain = 'default' ) { // phpcs:ignore
	return $text;
}

/**
 * Stub tối thiểu của add_query_arg() — hỗ trợ cả 2 chữ ký thật của WordPress:
 * add_query_arg( $key, $value, $url ) và add_query_arg( array, $url ). Đủ cho
 * tb247_build_recommended_url()/tb247_purge_deal_listing_cache(); không xử
 * lý merge với query string cũ vì mọi URL test ở đây chưa có query string.
 */
function add_query_arg( ...$args ) {
	if ( 2 === count( $args ) && is_array( $args[0] ) ) {
		list( $params, $url ) = $args;
	} else {
		list( $key, $value, $url ) = $args;
		$params                    = array( $key => $value );
	}

	if ( empty( $params ) ) {
		return $url;
	}

	$pairs = array();

	foreach ( $params as $key => $value ) {
		$pairs[] = $key . '=' . $value;
	}

	$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';

	return $url . $separator . implode( '&', $pairs );
}

/**
 * Stub của trailingslashit() — WP core, thêm "/" cuối chuỗi nếu chưa có.
 */
function trailingslashit( $string ) {
	return rtrim( (string) $string, '/' ) . '/';
}

/**
 * Stub của absint() — WP core, abs(intval()).
 */
function absint( $value ) {
	return abs( (int) $value );
}

require __DIR__ . '/../inc/template-tags.php';

$pass = 0;
$fail = 0;

function check( $label, $ok ) {
	global $pass, $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - $label\n";
	$ok ? $pass++ : $fail++;
}

echo "########################################\n";
echo "# A. tb247_get_recommended_marketplace_slugs() — allowlist đúng, không wildcard\n";
echo "########################################\n";

$slugs = tb247_get_recommended_marketplace_slugs();
check( 'allowlist đúng 3 phần tử: amazon, rakuten, yahoo', array( 'amazon', 'rakuten', 'yahoo' ) === $slugs );

echo "\n########################################\n";
echo "# B. tb247_sanitize_recommended_marketplace() — Test 1, 6\n";
echo "########################################\n";

check( 'amazon hợp lệ -> giữ nguyên', 'amazon' === tb247_sanitize_recommended_marketplace( 'amazon' ) );
check( 'rakuten hợp lệ -> giữ nguyên', 'rakuten' === tb247_sanitize_recommended_marketplace( 'rakuten' ) );
check( 'yahoo hợp lệ -> giữ nguyên', 'yahoo' === tb247_sanitize_recommended_marketplace( 'yahoo' ) );
check( 'rỗng -> すべて (chuỗi rỗng)', '' === tb247_sanitize_recommended_marketplace( '' ) );
check( 'giá trị lạ (ebay) -> fallback すべて', '' === tb247_sanitize_recommended_marketplace( 'ebay' ) );
check( 'giá trị có ký tự nguy hiểm -> fallback すべて (không Fatal)', '' === tb247_sanitize_recommended_marketplace( '<script>alert(1)</script>' ) );
check( 'giá trị lồng meta key khác -> fallback すべて (không nhận meta key từ GET)', '' === tb247_sanitize_recommended_marketplace( '_tb247_is_recommended' ) );
check( 'giá trị mixed-case Amazon -> vẫn map đúng nhờ sanitize_key lowercase', 'amazon' === tb247_sanitize_recommended_marketplace( 'AMAZON' ) );
check( 'null không throw, fallback すべて', '' === tb247_sanitize_recommended_marketplace( null ) );

echo "\n########################################\n";
echo "# C. tb247_get_recommended_filter_options() — đúng thứ tự + nhãn, mặc định すべて đầu tiên\n";
echo "########################################\n";

$options = tb247_get_recommended_filter_options();
check( 'có đúng 4 lựa chọn (すべて + 3 marketplace)', 4 === count( $options ) );
check( 'lựa chọn đầu tiên là すべて, slug rỗng', '' === $options[0]['slug'] && 'すべて' === $options[0]['label'] );
check( 'lựa chọn thứ 2 = Amazon', 'amazon' === $options[1]['slug'] && 'Amazon' === $options[1]['label'] );
check( 'lựa chọn thứ 3 = 楽天市場', 'rakuten' === $options[2]['slug'] && '楽天市場' === $options[2]['label'] );
check( 'lựa chọn thứ 4 = Yahoo!ショッピング (có ngay từ bây giờ dù chưa có data)', 'yahoo' === $options[3]['slug'] && 'Yahoo!ショッピング' === $options[3]['label'] );

echo "\n########################################\n";
echo "# D. tb247_build_pagination_window() — Test 15-19\n";
echo "########################################\n";

check( 'total_pages=0 -> rỗng (không pagination)', array() === tb247_build_pagination_window( 1, 0 ) );
check( 'total_pages=1 -> rỗng (Test 19: không pagination khi 1 page)', array() === tb247_build_pagination_window( 1, 1 ) );
check( 'total_pages=2, current=1 -> [1,2] (không cần ellipsis)', array( 1, 2 ) === tb247_build_pagination_window( 1, 2 ) );
check( 'total_pages=2, current=2 -> [1,2], trang cuối active đúng vị trí', array( 1, 2 ) === tb247_build_pagination_window( 2, 2 ) );

$window_9_current_1 = tb247_build_pagination_window( 1, 9 );
check( 'total_pages=9, current=1 -> có ellipsis + trang cuối 9 (Test 18)', in_array( '...', $window_9_current_1, true ) && in_array( 9, $window_9_current_1, true ) );
check( 'total_pages=9, current=1 -> trang 1 (current) nằm trong danh sách (Test 15)', in_array( 1, $window_9_current_1, true ) );

$window_9_current_5 = tb247_build_pagination_window( 5, 9 );
check( 'total_pages=9, current=5 (giữa) -> có cả 2 ellipsis 2 bên', 2 === count( array_filter( $window_9_current_5, fn( $p ) => '...' === $p ) ) );
check( 'total_pages=9, current=5 -> vẫn hiện trang 1 và trang 9 (edge)', in_array( 1, $window_9_current_5, true ) && in_array( 9, $window_9_current_5, true ) );
check( 'total_pages=9, current=5 -> không hiển thị quá nhiều số (ít hơn total_pages)', count( $window_9_current_5 ) < 9 );

check( 'total_pages=3, current=2 -> [1,2,3] không ellipsis thừa', array( 1, 2, 3 ) === tb247_build_pagination_window( 2, 3 ) );

echo "\n########################################\n";
echo "# E. tb247_build_recommended_url() — giữ marketplace khi đổi trang, reset trang khi đổi marketplace\n";
echo "########################################\n";

$base = 'https://tb247deal.com/recommended/';

// Dùng /page/N/ (pretty permalink) cho trang >1 — KHÔNG dùng ?paged=N: xác
// nhận thực tế trên production WordPress tự 301-redirect ?paged=N sang
// /page/N/ trên static Page (redirect_canonical()), và $_GET['paged'] không
// còn tồn tại ở URL sau redirect đó -> nếu build URL kiểu ?paged=N, trang 2
// sẽ round-trip qua redirect rồi ÂM THẦM hiển thị lại trang 1.
check( 'すべて + page 1 -> URL trần, không query string thừa', $base === tb247_build_recommended_url( $base, '', 1 ) );
check( 'amazon + page 1 -> chỉ có marketplace, KHÔNG có /page/1/ (Test 14: đổi marketplace luôn về page 1)', $base . '?marketplace=amazon' === tb247_build_recommended_url( $base, 'amazon', 1 ) );
check( 'すべて + page 2 -> dùng /page/2/ pretty permalink, không phải ?paged=2', $base . 'page/2/' === tb247_build_recommended_url( $base, '', 2 ) );
check(
	'amazon + page 3 -> giữ CẢ marketplace VÀ /page/3/ (Test 13: pagination giữ marketplace)',
	$base . 'page/3/?marketplace=amazon' === tb247_build_recommended_url( $base, 'amazon', 3 )
);
check( 'rakuten + page 2 -> đúng /page/2/ + marketplace', $base . 'page/2/?marketplace=rakuten' === tb247_build_recommended_url( $base, 'rakuten', 2 ) );

echo "\n########################################\n";
echo "# E2. tb247_resolve_recommended_paged() — ưu tiên get_query_var('paged'), tránh bug redirect_canonical()\n";
echo "########################################\n";

check( 'get_query_var("page")=2, $_GET rỗng -> trang 2 (đúng khi WordPress đã redirect sang /page/2/)', 2 === tb247_resolve_recommended_paged( 0, '2' ) );
check( 'get_query_var("page") rỗng, $_GET[paged]=2 -> vẫn nhận trang 2 (fallback plain permalink)', 2 === tb247_resolve_recommended_paged( 2, '' ) );
check( 'cả 2 đều rỗng -> mặc định trang 1', 1 === tb247_resolve_recommended_paged( 0, '' ) );
check( 'get_query_var("page") ưu tiên hơn $_GET[paged] khi cả 2 cùng có giá trị khác nhau', 3 === tb247_resolve_recommended_paged( 99, '3' ) );
check( 'get_query_var("page") dạng string "0" -> coi như rỗng, dùng fallback', 5 === tb247_resolve_recommended_paged( 5, '0' ) );

echo "\n########################################\n";
echo "# F. Structural check: page-recommended.php — query/security/markup\n";
echo "########################################\n";

$template_source = file_get_contents( __DIR__ . '/../page-recommended.php' );

check( '$_GET[\'marketplace\'] luôn qua wp_unslash() + tb247_sanitize_recommended_marketplace(), không dùng thẳng', strpos( $template_source, 'tb247_sanitize_recommended_marketplace( $raw_marketplace )' ) !== false );
check( 'paged luôn qua absint()', strpos( $template_source, 'absint( $_GET[\'paged\'] )' ) !== false );
check( 'trang hiện tại ưu tiên get_query_var(\'paged\') qua tb247_resolve_recommended_paged() (tránh bug redirect_canonical + đúng query var thật của rewrite rule)', strpos( $template_source, "tb247_resolve_recommended_paged( \$get_paged, get_query_var( 'paged' ) )" ) !== false );
check( 'dùng tb247_query_recommended_deals() (tách riêng khỏi tb247_query_deals_by_flag dùng cho sale-info)', strpos( $template_source, 'tb247_query_recommended_deals( $active_marketplace, $paged )' ) !== false );
check( 'KHÔNG query toàn bộ rồi slice bằng PHP (không array_slice trên $deals)', strpos( $template_source, 'array_slice' ) === false );
check( 'href marketplace/pagination đều qua esc_url()', substr_count( $template_source, 'esc_url(' ) >= 4 );
check( 'label marketplace/pagination đều qua esc_html()', substr_count( $template_source, 'esc_html(' ) >= 3 );
check( 'sidebar CÓ tiêu đề プラットフォーム', strpos( $template_source, 'プラットフォーム' ) !== false );
check( 'KHÔNG hiển thị "複数選択可"', strpos( $template_source, '複数選択可' ) === false );
check( 'KHÔNG dùng input checkbox cho marketplace filter', strpos( $template_source, 'type="checkbox"' ) === false && strpos( $template_source, "type='checkbox'" ) === false );
check( 'KHÔNG dùng input radio giả', strpos( $template_source, 'type="radio"' ) === false );
check( 'active marketplace có aria-current="page"', strpos( $template_source, 'aria-current="page"' ) !== false );
check( 'pagination có aria-label おすすめ商品のページネーション', strpos( $template_source, 'おすすめ商品のページネーション' ) !== false );
check( 'sidebar nav có aria-label riêng (プラットフォームで絞り込み)', strpos( $template_source, 'プラットフォームで絞り込み' ) !== false );
check( 'dùng <nav>/<ul>/<a> (semantic), không phải <div> giả nav', strpos( $template_source, '<nav class="tb247-marketplace-sidebar"' ) !== false && strpos( $template_source, '<ul class="tb247-marketplace-filter">' ) !== false );
check( 'pagination dùng <nav>...aria-label (Test: pagination phải có aria-label)', preg_match( '/<nav class="tb247-pagination" aria-label="[^"]+">/', $template_source ) === 1 );
check( 'previous disabled khi $paged > 1 là false (Test 16: previous disabled ở page 1)', strpos( $template_source, 'tb247-pagination__edge--disabled' ) !== false );
check( 'không dùng JavaScript để filter (không thấy addEventListener/fetch cho marketplace)', strpos( $template_source, 'addEventListener' ) === false && strpos( $template_source, 'fetch(' ) === false );
check( 'không dùng hash-only filter (#amazon)', ! preg_match( '/href="#(amazon|rakuten|yahoo)"/', $template_source ) );
check( 'empty state phân biệt すべて (chung) vs marketplace cụ thể (Test 22: Yahoo empty state)', strpos( $template_source, '現在おすすめ商品はまだ登録されていません。' ) !== false && strpos( $template_source, '現在、このショップのおすすめ商品はありません。' ) !== false );
check( 'card vẫn dùng tb247_the_deal_card() — KHÔNG viết lại card (Test 24: card HTML regression)', strpos( $template_source, 'tb247_the_deal_card( $deal )' ) !== false );
check( 'reset page về 1 khi build URL đổi marketplace (item_url dùng paged=1 cố định cho sidebar)', strpos( $template_source, "tb247_build_recommended_url( \$base_url, \$option['slug'], 1 )" ) !== false );

echo "\n########################################\n";
echo "# G. Structural check: tb247_query_recommended_deals() — Test 7, 20, 21\n";
echo "########################################\n";

$template_tags_source = file_get_contents( __DIR__ . '/../inc/template-tags.php' );
$query_fn_start       = strpos( $template_tags_source, 'function tb247_query_recommended_deals' );
$query_fn_end         = strpos( $template_tags_source, "\n}\n", $query_fn_start );
$query_fn_source      = substr( $template_tags_source, $query_fn_start, $query_fn_end - $query_fn_start );

check( "posts_per_page = 12 (Test 7)", strpos( $query_fn_source, "'posts_per_page' => 12" ) !== false );
check( "post_type = deal, post_status = publish (chỉ deal thật đã publish)", strpos( $query_fn_source, "'post_type'      => 'deal'" ) !== false && strpos( $query_fn_source, "'post_status'    => 'publish'" ) !== false );
check( "LUÔN filter theo _tb247_is_recommended (Test 20/21: chỉ recommended, non-recommended không xuất hiện)", strpos( $query_fn_source, "'key'   => '_tb247_is_recommended'" ) !== false );
check( "marketplace meta_query chỉ thêm khi marketplace khác rỗng (すべて lấy tất cả marketplace)", strpos( $query_fn_source, "if ( '' !== \$marketplace ) {" ) !== false );
check( "marketplace filter dùng field chuẩn _tb247_marketplace (không suy đoán từ title/badge/URL)", strpos( $query_fn_source, "'key'   => '_tb247_marketplace'" ) !== false );
check( "dùng WP_Query (không viết SQL thủ công)", strpos( $query_fn_source, 'new WP_Query(' ) !== false && strpos( $query_fn_source, '$wpdb' ) === false );
check( "paged truyền vào WP_Query (Test: pagination chính xác)", strpos( $query_fn_source, "'paged'          => \$paged" ) !== false );

echo "\n########################################\n";
echo "# H. Regression — /sale-info/ và tb247_query_deals_by_flag() không đổi\n";
echo "########################################\n";

check(
	'tb247_query_deals_by_flag() vẫn còn nguyên (posts_per_page 60, không marketplace filter) — /sale-info/ không bị ảnh hưởng',
	strpos( $template_tags_source, "function tb247_query_deals_by_flag( \$meta_key ) {" ) !== false &&
		strpos( $template_tags_source, "'posts_per_page' => 60," ) !== false
);

$sale_info_source = file_get_contents( __DIR__ . '/../page-sale-info.php' );
check(
	'/sale-info/ vẫn gọi tb247_query_deals_by_flag() y hệt cũ, không đổi sang query mới',
	strpos( $sale_info_source, "tb247_query_deals_by_flag( '_tb247_is_sale' )" ) !== false
);
check( '/sale-info/ KHÔNG có marketplace filter/sidebar mới (ngoài phạm vi task)', strpos( $sale_info_source, 'tb247-marketplace-sidebar' ) === false );

check( 'tb247_the_deal_card() không bị sửa (CTA/badge/ảnh/giá/JAN giữ nguyên — Test 23/24/29)', (function () use ( $template_tags_source ) {
	$fn_start = strpos( $template_tags_source, 'function tb247_the_deal_card( $deal ) {' );
	$fn_body  = substr( $template_tags_source, $fn_start, 1600 );
	return strpos( $fn_body, 'tb247-deal-grid-badge' ) !== false
		&& strpos( $fn_body, 'tb247-deal-grid-price' ) !== false
		&& strpos( $fn_body, 'tb247-deal-grid-jan' ) !== false
		&& strpos( $fn_body, "home_url( '/d/' . rawurlencode( \$code ) . '/' )" ) !== false;
} )() );

echo "\n########################################\n";
echo "# I. Structural check: CSS — prefix riêng, không selector quá rộng, 4 cột desktop\n";
echo "########################################\n";

$css_source = file_get_contents( __DIR__ . '/../style.css' );

check( 'có class .tb247-recommended-layout', strpos( $css_source, '.tb247-recommended-layout' ) !== false );
check( 'có class .tb247-marketplace-sidebar', strpos( $css_source, '.tb247-marketplace-sidebar' ) !== false );
check( 'có class .tb247-marketplace-filter__item--active', strpos( $css_source, '.tb247-marketplace-filter__item--active' ) !== false );
check( 'có class .tb247-recommended-grid', strpos( $css_source, '.tb247-recommended-grid' ) !== false );
check( 'có class .tb247-pagination', strpos( $css_source, '.tb247-pagination' ) !== false );
check( 'desktop grid 4 cột (Test 25)', strpos( $css_source, 'grid-template-columns: repeat( 4, minmax( 0, 1fr ) );' ) !== false );
$recommended_css_start = strpos( $css_source, '.tb247-recommended-section {' );
$recommended_css_end   = strpos( $css_source, "   Footer\n" );
$recommended_css_block = substr( $css_source, $recommended_css_start, $recommended_css_end - $recommended_css_start );

check(
	'KHÔNG dùng selector quá rộng "button {" / "a {" / ".card {" trong phần CSS mới thêm cho recommended',
	! preg_match( '/\n(button|a|\.card)\s*\{/', $recommended_css_block )
);
check( '.tb247-deal-grid (dùng cho sale-info) KHÔNG bị đổi cấu trúc (vẫn còn nguyên, không xoá)', strpos( $css_source, '.tb247-deal-grid {' ) !== false );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
