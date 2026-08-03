<?php
/**
 * Test standalone cho TB247_DM_Products_Validator + TB247_DM_Rakuten_Marketplace
 * — chạy độc lập ngoài WordPress, chỉ stub các hàm WP tối thiểu cần thiết.
 *
 * Chạy: php tests/products-validator-test.php
 *
 * @package TB247_Deal_Manager
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = 'default' ) { // phpcs:ignore
	return $text;
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $value ) {
	$value = (string) $value;
	$value = preg_replace( '/<[^>]*>/', '', $value );
	return trim( preg_replace( '/[\r\n\t]+/', ' ', $value ) );
}

function esc_url_raw( $url ) {
	return $url;
}

function wp_http_validate_url( $url ) {
	$parts = parse_url( $url );

	if ( false === $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
		return false;
	}

	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}

	return $url;
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

require __DIR__ . '/../includes/marketplaces/class-url-guard.php';
require __DIR__ . '/../includes/marketplaces/interface-marketplace.php';
require __DIR__ . '/../includes/marketplaces/class-amazon-marketplace.php';
require __DIR__ . '/../includes/marketplaces/class-rakuten-marketplace.php';
require __DIR__ . '/../includes/rest/class-products-validator.php';

$pass = 0;
$fail = 0;

function vcheck( $label, $ok ) {
	global $pass, $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - $label\n";
	$ok ? $pass++ : $fail++;
}

$valid_amazon_payload = array(
	'asin'          => 'B0GWHBFNGG',
	'jan'           => '4988602180305',
	'title'         => 'Sample Amazon Product',
	'price'         => 6631,
	'image'         => 'https://m.media-amazon.com/images/I/example.jpg',
	'affiliate_url' => 'https://www.amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22',
	'brand'         => 'Konami',
	'in_stock'      => true,
);

$valid_rakuten_payload = array(
	'shop_code'     => 'example-shop',
	'item_code'     => 'example-item',
	'jan'           => '4988602180305',
	'title'         => 'Sample Rakuten Product',
	'price'         => 49800,
	'image'         => 'https://thumbnail.image.rakuten.co.jp/example.jpg',
	'affiliate_url' => 'https://hb.afl.rakuten.co.jp/hgc/example',
	'source_url'    => 'https://item.rakuten.co.jp/example-shop/example-item/',
	'in_stock'      => true,
);

echo "########################################\n";
echo "# A. AMAZON — regression, validate() không truyền marketplace (mặc định)\n";
echo "########################################\n";
$r = TB247_DM_Products_Validator::validate( $valid_amazon_payload );
vcheck( 'payload Amazon hợp lệ vẫn valid=true khi gọi validate() không tham số', $r['valid'] );
vcheck( 'data.asin đúng (không đổi field name)', 'B0GWHBFNGG' === $r['data']['asin'] );
vcheck( 'data.affiliate_url giữ nguyên y hệt (không qua allowlist mới)', $r['data']['affiliate_url'] === $valid_amazon_payload['affiliate_url'] );

$r2 = TB247_DM_Products_Validator::validate( $valid_amazon_payload, 'amazon' );
vcheck( 'payload Amazon hợp lệ, gọi tường minh marketplace=amazon, kết quả giống hệt', $r2 === $r );

echo "\n########################################\n";
echo "# B. RAKUTEN — payload hợp lệ phải PASS\n";
echo "########################################\n";
$rr = TB247_DM_Products_Validator::validate( $valid_rakuten_payload, 'rakuten' );
vcheck( 'payload Rakuten hợp lệ -> valid=true', $rr['valid'] );
vcheck( 'shop_code/item_code giữ nguyên', 'example-shop' === $rr['data']['shop_code'] && 'example-item' === $rr['data']['item_code'] );
vcheck( 'affiliate_url qua allowlist, giữ nguyên (hb.afl.rakuten.co.jp)', $rr['data']['affiliate_url'] === $valid_rakuten_payload['affiliate_url'] );
vcheck( 'source_url qua allowlist, giữ nguyên (item.rakuten.co.jp)', $rr['data']['source_url'] === $valid_rakuten_payload['source_url'] );

echo "\n########################################\n";
echo "# C. RAKUTEN — payload không hợp lệ phải REJECT (valid=false)\n";
echo "########################################\n";

$missing_shop = $valid_rakuten_payload;
unset( $missing_shop['shop_code'] );
vcheck( 'thiếu shop_code -> invalid', false === TB247_DM_Products_Validator::validate( $missing_shop, 'rakuten' )['valid'] );

$price_zero = $valid_rakuten_payload;
$price_zero['price'] = 0;
vcheck( 'price = 0 -> invalid (Rakuten yêu cầu > 0, chặt hơn Amazon)', false === TB247_DM_Products_Validator::validate( $price_zero, 'rakuten' )['valid'] );

$price_negative = $valid_rakuten_payload;
$price_negative['price'] = -100;
vcheck( 'price âm -> invalid', false === TB247_DM_Products_Validator::validate( $price_negative, 'rakuten' )['valid'] );

$price_html = $valid_rakuten_payload;
$price_html['price'] = '<script>1</script>';
vcheck( 'price dạng HTML/non-numeric -> invalid', false === TB247_DM_Products_Validator::validate( $price_html, 'rakuten' )['valid'] );

$jan_letters = $valid_rakuten_payload;
$jan_letters['jan'] = 'ABC1234567890';
$r_jan_letters = TB247_DM_Products_Validator::validate( $jan_letters, 'rakuten' );
vcheck( 'JAN chứa chữ -> invalid (regex \D bị strip hết còn lại sai độ dài)', false === $r_jan_letters['valid'] );

$jan_bad_length = $valid_rakuten_payload;
$jan_bad_length['jan'] = '12345';
vcheck( 'JAN sai độ dài -> invalid', false === TB247_DM_Products_Validator::validate( $jan_bad_length, 'rakuten' )['valid'] );

$title_script = $valid_rakuten_payload;
$title_script['title'] = '<script>alert(1)</script>Real Title';
$r_title = TB247_DM_Products_Validator::validate( $title_script, 'rakuten' );
vcheck( 'title chứa script -> script bị strip qua sanitize_text_field, vẫn valid vì còn lại text', $r_title['valid'] && false === strpos( $r_title['data']['title'], '<script>' ) );

$amazon_affiliate_in_rakuten = $valid_rakuten_payload;
$amazon_affiliate_in_rakuten['affiliate_url'] = 'https://www.amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22';
vcheck( 'affiliate_url Amazon trong payload Rakuten -> invalid (sai allowlist)', false === TB247_DM_Products_Validator::validate( $amazon_affiliate_in_rakuten, 'rakuten' )['valid'] );

$source_outside_rakuten = $valid_rakuten_payload;
$source_outside_rakuten['source_url'] = 'https://evil.example/product';
vcheck( 'source_url ngoài Rakuten -> invalid', false === TB247_DM_Products_Validator::validate( $source_outside_rakuten, 'rakuten' )['valid'] );

$missing_affiliate = $valid_rakuten_payload;
unset( $missing_affiliate['affiliate_url'] );
vcheck( 'thiếu affiliate_url (bắt buộc) -> invalid', false === TB247_DM_Products_Validator::validate( $missing_affiliate, 'rakuten' )['valid'] );

echo "\n########################################\n";
echo "# D. RAKUTEN — point field bị ignore hoàn toàn (không có trong output)\n";
echo "########################################\n";
$with_points = $valid_rakuten_payload;
$with_points['point']            = 500;
$with_points['point_rate']       = 10;
$with_points['estimated_points'] = 4980;
$with_points['campaign_points']  = 100;
$r_points = TB247_DM_Products_Validator::validate( $with_points, 'rakuten' );
vcheck( 'point/point_rate/estimated_points/campaign_points không xuất hiện trong data output', $r_points['valid']
	&& ! array_key_exists( 'point', $r_points['data'] )
	&& ! array_key_exists( 'point_rate', $r_points['data'] )
	&& ! array_key_exists( 'estimated_points', $r_points['data'] )
	&& ! array_key_exists( 'campaign_points', $r_points['data'] )
);

echo "\n########################################\n";
echo "# E. TB247_DM_Rakuten_Marketplace\n";
echo "########################################\n";
$rakuten_marketplace = new TB247_DM_Rakuten_Marketplace();
vcheck( 'get_slug() = rakuten', 'rakuten' === $rakuten_marketplace->get_slug() );
vcheck( 'get_buy_button_label() = 楽天で購入', '楽天で購入' === $rakuten_marketplace->get_buy_button_label() );
vcheck(
	'get_code() ghép shop_code-item_code, viết hoa',
	'EXAMPLE-SHOP-EXAMPLE-ITEM' === $rakuten_marketplace->get_code( array( 'shop_code' => 'example-shop', 'item_code' => 'example-item' ) )
);
vcheck( 'validate() reject khi thiếu shop_code/item_code', is_wp_error( $rakuten_marketplace->validate( array( 'title' => 'x' ) ) ) );
vcheck( 'validate() accept khi đủ shop_code/item_code/title', true === $rakuten_marketplace->validate( array( 'shop_code' => 'a', 'item_code' => 'b', 'title' => 'x' ) ) );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
