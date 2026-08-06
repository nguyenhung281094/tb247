<?php
/**
 * Test standalone cho helper CTA marketplace trong inc/template-tags.php —
 * chạy độc lập ngoài WordPress (chỉ stub sanitize_key(), hàm WP duy nhất mà
 * 2 helper dưới đây gọi tới). require() cả file chỉ khai báo function, không
 * có code chạy ở top-level ngoài `defined('ABSPATH') || exit;` nên an toàn
 * dù các hàm KHÁC trong file (dùng get_post_meta/home_url/WP_Query...)
 * không được stub — chúng không được gọi trong test này.
 *
 * Chạy: php tests/cta-label-test.php
 *
 * @package TB247_Gadget_Lab
 */

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
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
echo "# CTA buy-button label\n";
echo "########################################\n";

check( 'Rakuten CTA text = RAKUTEN', 'RAKUTEN' === tb247_get_marketplace_buy_button_label( 'rakuten' ) );
check( 'Rakuten CTA text KHÔNG còn là 楽天で購入', '楽天で購入' !== tb247_get_marketplace_buy_button_label( 'rakuten' ) );
check( 'Amazon CTA text không đổi = Amazon', 'Amazon' === tb247_get_marketplace_buy_button_label( 'amazon' ) );
check( 'marketplace rỗng -> fallback Amazon (giữ hành vi cũ)', 'Amazon' === tb247_get_marketplace_buy_button_label( '' ) );
check( 'marketplace lạ -> fallback Amazon (không render text rỗng/sai)', 'Amazon' === tb247_get_marketplace_buy_button_label( 'ebay' ) );
check( 'gọi không tham số -> mặc định Amazon', 'Amazon' === tb247_get_marketplace_buy_button_label() );
check( 'Yahoo CTA text = Yahoo!ショッピング (§14 task Yahoo parity)', 'Yahoo!ショッピング' === tb247_get_marketplace_buy_button_label( 'yahoo' ) );

echo "\n########################################\n";
echo "# CTA rel/target (regression — không đổi so với các task trước)\n";
echo "########################################\n";

$rakuten_attrs = tb247_get_marketplace_link_attributes( 'rakuten' );
check( 'Rakuten rel = sponsored noopener noreferrer', 'sponsored noopener noreferrer' === $rakuten_attrs['rel'] );
check( 'Rakuten target = _blank', '_blank' === $rakuten_attrs['target'] );

$amazon_attrs = tb247_get_marketplace_link_attributes( 'amazon' );
check( 'Amazon rel không đổi = sponsored noopener noreferrer nofollow', 'sponsored noopener noreferrer nofollow' === $amazon_attrs['rel'] );
check( 'Amazon target không đổi = _blank', '_blank' === $amazon_attrs['target'] );

echo "\n########################################\n";
echo "# CTA rel/target — §6 fallback source_url khi KHÔNG có affiliate (task /recommend url+aff)\n";
echo "########################################\n";

$rakuten_no_aff_attrs = tb247_get_marketplace_link_attributes( 'rakuten', false );
check( 'Rakuten KHÔNG có affiliate -> rel KHÔNG có sponsored', false === strpos( $rakuten_no_aff_attrs['rel'], 'sponsored' ) );
check( 'Rakuten KHÔNG có affiliate -> rel có nofollow', false !== strpos( $rakuten_no_aff_attrs['rel'], 'nofollow' ) );
check( 'Rakuten KHÔNG có affiliate -> rel có noopener noreferrer', false !== strpos( $rakuten_no_aff_attrs['rel'], 'noopener' ) && false !== strpos( $rakuten_no_aff_attrs['rel'], 'noreferrer' ) );
check( 'Rakuten KHÔNG có affiliate -> target vẫn _blank', '_blank' === $rakuten_no_aff_attrs['target'] );

$rakuten_explicit_aff_attrs = tb247_get_marketplace_link_attributes( 'rakuten', true );
check( 'Rakuten CÓ affiliate (truyền tường minh true) -> rel giữ nguyên sponsored noopener noreferrer', 'sponsored noopener noreferrer' === $rakuten_explicit_aff_attrs['rel'] );

check( 'gọi không truyền $is_affiliate -> mặc định true (KHÔNG đổi hành vi cũ/regression)', 'sponsored noopener noreferrer' === tb247_get_marketplace_link_attributes( 'rakuten' )['rel'] );

$amazon_no_aff_attrs = tb247_get_marketplace_link_attributes( 'amazon', false );
check( 'Amazon $is_affiliate=false KHÔNG ảnh hưởng (Amazon luôn bắt buộc affiliate ở tầng validate, giữ rel cũ)', 'sponsored noopener noreferrer nofollow' === $amazon_no_aff_attrs['rel'] );

echo "\n########################################\n";
echo "# CTA rel/target Yahoo — §14 task Yahoo parity (cùng quy tắc generic non-Amazon với Rakuten)\n";
echo "########################################\n";

$yahoo_aff_attrs = tb247_get_marketplace_link_attributes( 'yahoo', true );
check( 'Yahoo CÓ affiliate -> rel = sponsored noopener noreferrer', 'sponsored noopener noreferrer' === $yahoo_aff_attrs['rel'] );
check( 'Yahoo CÓ affiliate -> target = _blank', '_blank' === $yahoo_aff_attrs['target'] );
check( 'Yahoo gọi không truyền $is_affiliate -> mặc định true (sponsored)', 'sponsored noopener noreferrer' === tb247_get_marketplace_link_attributes( 'yahoo' )['rel'] );

$yahoo_no_aff_attrs = tb247_get_marketplace_link_attributes( 'yahoo', false );
check( 'Yahoo KHÔNG có affiliate -> rel KHÔNG có sponsored', false === strpos( $yahoo_no_aff_attrs['rel'], 'sponsored' ) );
check( 'Yahoo KHÔNG có affiliate -> rel có nofollow', false !== strpos( $yahoo_no_aff_attrs['rel'], 'nofollow' ) );
check( 'Yahoo KHÔNG có affiliate -> rel có noopener noreferrer', false !== strpos( $yahoo_no_aff_attrs['rel'], 'noopener' ) && false !== strpos( $yahoo_no_aff_attrs['rel'], 'noreferrer' ) );
check( 'Yahoo KHÔNG có affiliate -> target vẫn _blank', '_blank' === $yahoo_no_aff_attrs['target'] );
check( 'Yahoo no-aff và Rakuten no-aff cho CÙNG rel (logic generic non-Amazon dùng chung, không hardcode riêng Rakuten)', $yahoo_no_aff_attrs['rel'] === $rakuten_no_aff_attrs['rel'] );

echo "\n########################################\n";
echo "# Structural check: single-deal.php — CTA fallback source_url khi không có affiliate (§6)\n";
echo "########################################\n";

$single_deal_source = file_get_contents( __DIR__ . '/../single-deal.php' );

check( 'đọc _tb247_product_url làm nguồn fallback', strpos( $single_deal_source, "get_post_meta( \$deal_id, '_tb247_product_url', true )" ) !== false );
check( '$cta_url = có affiliate ? affiliate_url : source_url (không hardcode ẩn CTA khi thiếu affiliate)', strpos( $single_deal_source, '$cta_url = $has_affiliate ? $affiliate_url : $source_url;' ) !== false );
check( 'nút mua (buy-button-mini) dùng $cta_url, không còn dùng thẳng $affiliate_url (đã fallback)', strpos( $single_deal_source, 'href="<?php echo esc_url( $cta_url ); ?>"' ) !== false );
check( 'rel/target tính theo $has_affiliate (truyền vào tb247_get_marketplace_link_attributes)', strpos( $single_deal_source, 'tb247_get_marketplace_link_attributes( $marketplace, $has_affiliate )' ) !== false );
check( 'KHÔNG còn điều kiện hiển thị CTA chỉ dựa vào $affiliate_url đơn thuần (đã đổi sang $cta_url — Test: CTA không bị ẩn khi thiếu affiliate)', strpos( $single_deal_source, 'if ( $affiliate_url ) :' ) === false );
check( 'source_url KHÔNG bao giờ được gán ngược vào $affiliate_url (2 biến độc lập)', strpos( $single_deal_source, '$affiliate_url = $source_url' ) === false && strpos( $single_deal_source, '$affiliate_url = $cta_url' ) === false );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
