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
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
