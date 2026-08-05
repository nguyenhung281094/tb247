<?php
/**
 * Test standalone cho 更新日 trên card /recommended/ (§9 — task /recommend
 * url+aff + Quick Check update-only + scheduler). Chỉ test hàm thuần
 * tb247_get_recommended_updated_display() (không phụ thuộc get_post_meta thật
 * — stub tối thiểu bên dưới) + structural check markup tb247_the_deal_card().
 *
 * Chạy: php tests/updated-date-test.php
 *
 * @package TB247_Gadget_Lab
 */

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Stub get_post_meta() tối thiểu: đọc từ $GLOBALS['__test_post_meta'][$post_id][$key].
 * Chỉ dùng để test tb247_get_recommended_updated_display() độc lập, KHÔNG chạy
 * qua toàn bộ tb247_the_deal_card() (hàm đó còn cần WP_Query/home_url/esc_*
 * thật — được verify qua structural check bên dưới thay vì gọi thật).
 */
function get_post_meta( $post_id, $key, $single = false ) { // phpcs:ignore
	return $GLOBALS['__test_post_meta'][ $post_id ][ $key ] ?? '';
}

/**
 * Stub date_i18n() — đủ cho định dạng 'Y年n月j日' dùng trong hàm test, không
 * cần i18n thật (test không phụ thuộc locale server).
 */
function date_i18n( $format, $timestamp ) { // phpcs:ignore
	return date( $format, $timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
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
echo "# tb247_get_recommended_updated_display() — §9: không bịa ngày giả\n";
echo "########################################\n";

$GLOBALS['__test_post_meta'] = array(
	1 => array( '_tb247_recommended_updated_at' => '2026-08-05 10:00:00' ),
	2 => array( '_tb247_recommended_updated_at' => '' ),
	3 => array(), // hoàn toàn chưa có meta (deal legacy chưa từng refresh).
	4 => array( '_tb247_recommended_updated_at' => 'not-a-real-date' ),
);

check( 'có ngày hợp lệ -> format Y年n月j日 đúng', '2026年8月5日' === tb247_get_recommended_updated_display( 1 ) );
check( 'meta rỗng ("") -> trả về rỗng, KHÔNG bịa ngày', '' === tb247_get_recommended_updated_display( 2 ) );
check( 'meta hoàn toàn chưa tồn tại (legacy) -> trả về rỗng', '' === tb247_get_recommended_updated_display( 3 ) );
check( 'giá trị hỏng/không parse được -> trả về rỗng, không Fatal/warning che giấu', '' === tb247_get_recommended_updated_display( 4 ) );

echo "\n########################################\n";
echo "# Structural check: tb247_the_deal_card() — JAN trái + 更新日 phải, cùng hàng (§9)\n";
echo "########################################\n";

$template_tags_source = file_get_contents( __DIR__ . '/../inc/template-tags.php' );
$fn_start             = strpos( $template_tags_source, 'function tb247_the_deal_card( $deal ) {' );
$fn_end                = strpos( $template_tags_source, "\n}\n", $fn_start );
$fn_source             = substr( $template_tags_source, $fn_start, $fn_end - $fn_start );

check( 'card gọi tb247_get_recommended_updated_display()', strpos( $fn_source, 'tb247_get_recommended_updated_display( $deal_id )' ) !== false );
check( 'có wrapper .tb247-deal-grid-meta-row bọc chung JAN + 更新日', strpos( $fn_source, 'tb247-deal-grid-meta-row' ) !== false );
check( 'JAN vẫn còn class .tb247-deal-grid-jan (không bị xoá/đổi tên — regression)', strpos( $fn_source, 'tb247-deal-grid-jan' ) !== false );
check( '更新日 dùng class riêng .tb247-deal-grid-updated', strpos( $fn_source, 'tb247-deal-grid-updated' ) !== false );
check( 'label hiển thị đúng "更新日：" (không phải 掲載日)', strpos( $fn_source, '更新日：' ) !== false );
check( 'KHÔNG hiển thị 掲載日 ở bất kỳ đâu trong card', strpos( $fn_source, '掲載日' ) === false );
check( '更新日 chỉ render trong block có điều kiện ($jan || $updated_at) — không luôn hiện dù rỗng', strpos( $fn_source, 'if ( $jan || $updated_at ) :' ) !== false );
check( 'badge/price/CTA-wrapper giữ nguyên (regression — không viết lại card)', strpos( $fn_source, 'tb247-deal-grid-badge' ) !== false && strpos( $fn_source, 'tb247-deal-grid-price' ) !== false );

echo "\n########################################\n";
echo "# Structural check: CSS — .tb247-deal-grid-meta-row flex space-between, JAN trái/更新日 phải\n";
echo "########################################\n";

$css_source = file_get_contents( __DIR__ . '/../style.css' );
$css_start  = strpos( $css_source, '.tb247-deal-grid-meta-row {' );
$css_end    = strpos( $css_source, '}', $css_start );
$css_block  = substr( $css_source, $css_start, $css_end - $css_start );

check( '.tb247-deal-grid-meta-row tồn tại', false !== $css_start );
check( 'dùng display:flex', strpos( $css_block, 'display: flex;' ) !== false );
check( 'dùng justify-content: space-between (JAN trái/更新日 phải)', strpos( $css_block, 'justify-content: space-between;' ) !== false );
check( 'cho phép wrap trên màn hình hẹp (không tràn/đè chữ)', strpos( $css_block, 'flex-wrap: wrap;' ) !== false );
check( '.tb247-deal-grid-updated dùng font nhỏ + màu muted (không lấn át JAN)', strpos( $css_source, '.tb247-deal-grid-updated {' ) !== false && strpos( $css_source, 'var(--tb-muted)' ) !== false );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
