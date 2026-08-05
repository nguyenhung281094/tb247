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

// §4 (task /recommend url+aff): affiliate_url Rakuten KHÔNG còn bắt buộc ở
// tầng WordPress — /recommend cho phép tạo deal chưa có affiliate URL, giữ
// rỗng/null, KHÔNG lỗi validate. Nếu CÓ gửi lên vẫn phải hợp lệ (test khác ở
// trên/dưới đã phủ trường hợp sai host/thiếu scid-sc2id).
$missing_affiliate = $valid_rakuten_payload;
unset( $missing_affiliate['affiliate_url'] );
$missing_affiliate_result = TB247_DM_Products_Validator::validate( $missing_affiliate, 'rakuten' );
vcheck( 'thiếu affiliate_url (nay optional) -> vẫn valid', true === $missing_affiliate_result['valid'] );
vcheck( 'thiếu affiliate_url -> data.affiliate_url rỗng (KHÔNG bịa/dùng source_url thay thế)', '' === $missing_affiliate_result['data']['affiliate_url'] );

$empty_affiliate = $valid_rakuten_payload;
$empty_affiliate['affiliate_url'] = '';
vcheck( 'affiliate_url rỗng tường minh -> vẫn valid (optional, không phải lỗi)', true === TB247_DM_Products_Validator::validate( $empty_affiliate, 'rakuten' )['valid'] );

$invalid_affiliate_still_rejected = $valid_rakuten_payload;
$invalid_affiliate_still_rejected['affiliate_url'] = 'https://evil.example/track?scid=123';
vcheck( 'CÓ affiliate_url nhưng sai host -> vẫn invalid (optional không có nghĩa nới lỏng validate khi có giá trị)', false === TB247_DM_Products_Validator::validate( $invalid_affiliate_still_rejected, 'rakuten' )['valid'] );

echo "\n########################################\n";
echo "# C1b. Bug fix production (2026-08-06 01:28 JST): payload builder Bot <-> validator WordPress — integration thật\n";
echo "########################################\n";

// Payload dựng Y HỆT syncRakutenDealToWordPress() trong wordpress-sync.js
// (index.js/wordpress-sync.js — Bot repo, không thể require() PHP từ JS nên
// đối chiếu field-name/shape thủ công tại đây): marketplace, shop_code,
// item_code, title, jan, price, image, source_url, affiliate_url, in_stock —
// PHẢI khớp NGUYÊN VĂN tên field validate_rakuten() đang đọc. Đây chính là
// integration fixture chung §6 yêu cầu — không chỉ mock response 200 phía Bot.
$bot_payload_shape = array(
	'shop_code'     => 'superdeal',
	'item_code'     => '17436wzu10585',
	'title'         => 'SHARP シャープ 衣類乾燥除湿機 CV-SH150-W ホワイト',
	'jan'           => '4550556131353',
	'price'         => 51800,
	'image'         => 'https://thumbnail.image.rakuten.co.jp/example.jpg',
	'source_url'    => 'https://item.rakuten.co.jp/superdeal/17436wzu10585/',
	'affiliate_url' => '', // createRakutenDealForRecommend() gửi affiliateUrl || "" — KHÔNG BAO GIỜ undefined/null.
);
$bot_payload_result = TB247_DM_Products_Validator::validate( $bot_payload_shape, 'rakuten' );
vcheck( 'payload shape THẬT của syncRakutenDealToWordPress() (Rakuten, không aff) -> valid=true qua validator PHP thật', $bot_payload_result['valid'] );
vcheck( 'field name price/image/jan/title/shop_code/item_code/source_url khớp 1-1 giữa Bot payload và validator (không đoán tên field)', array_keys( $bot_payload_result['data'] ) === array( 'shop_code', 'item_code', 'jan', 'title', 'price', 'image', 'affiliate_url', 'source_url', 'in_stock' ) );

// BUG PRODUCTION THẬT (root cause đã audit từ log): image rỗng vì
// getKaitoriDataWithImageFallback() TRƯỚC KHI FIX không có bước fallback ảnh
// -> gửi image: "" -> validator reject với reason "invalid_image" (không phải
// lỗi field-name, mà là dữ liệu thật sự thiếu). Test này tái hiện CHÍNH XÁC
// request đã fail thật trên production, xác nhận validator trả đúng field.
$bot_payload_missing_image = $bot_payload_shape;
$bot_payload_missing_image['image'] = '';
$missing_image_result = TB247_DM_Products_Validator::validate( $bot_payload_missing_image, 'rakuten' );
vcheck( 'TÁI HIỆN bug production: image rỗng -> valid=false, errors.image tồn tại (đúng nguyên nhân log thật "reason=invalid_image")', false === $missing_image_result['valid'] && isset( $missing_image_result['errors']['image'] ) );

echo "\n########################################\n";
echo "# C2. RAKUTEN — /landing url+aff: source_url và affiliate_url độc lập, r10.to/hb.afl\n";
echo "########################################\n";

// r10.to làm affiliate_url (source_url vẫn item.rakuten.co.jp) -> PASS.
$r10_affiliate_payload = $valid_rakuten_payload;
$r10_affiliate_payload['affiliate_url'] = 'https://r10.to/abcde';
$r10_result = TB247_DM_Products_Validator::validate( $r10_affiliate_payload, 'rakuten' );
vcheck( 'affiliate_url=r10.to (source_url=item.rakuten.co.jp) -> valid=true', $r10_result['valid'] );
vcheck( 'affiliate_url r10.to giữ nguyên y hệt trong data output', $r10_result['data']['affiliate_url'] === 'https://r10.to/abcde' );
vcheck( 'source_url vẫn là item.rakuten.co.jp, KHÔNG bị thay bằng affiliate_url', $r10_result['data']['source_url'] === $valid_rakuten_payload['source_url'] );

// hb.afl.rakuten.co.jp làm affiliate_url (đã PASS sẵn ở valid_rakuten_payload) — xác nhận rõ ràng ở đây.
$hbafl_result = TB247_DM_Products_Validator::validate( $valid_rakuten_payload, 'rakuten' );
vcheck( 'affiliate_url=hb.afl.rakuten.co.jp -> valid=true', $hbafl_result['valid'] );
vcheck( 'affiliate_url hb.afl giữ nguyên y hệt trong data output', $hbafl_result['data']['affiliate_url'] === $valid_rakuten_payload['affiliate_url'] );

// source_url hợp lệ nhưng affiliate_url độc hại -> TOÀN BỘ request FAIL (không chỉ field đó).
$malicious_affiliate_valid_source = $valid_rakuten_payload;
$malicious_affiliate_valid_source['affiliate_url'] = 'https://r10.to.evil.example/abcde';
$malicious_result = TB247_DM_Products_Validator::validate( $malicious_affiliate_valid_source, 'rakuten' );
vcheck( 'source_url hợp lệ nhưng affiliate_url giả dạng r10.to -> valid=false (toàn request reject)', false === $malicious_result['valid'] );
vcheck( 'error chỉ rõ field affiliate_url', isset( $malicious_result['errors']['affiliate_url'] ) );

$fake_r10_affiliate = $valid_rakuten_payload;
$fake_r10_affiliate['affiliate_url'] = 'https://fake-r10.to/abcde';
vcheck( 'affiliate_url=fake-r10.to -> invalid (exact host, không phải suffix match)', false === TB247_DM_Products_Validator::validate( $fake_r10_affiliate, 'rakuten' )['valid'] );

// Không fallback affiliate_url về source_url khi affiliate_url invalid — data['affiliate_url'] phải rỗng, không lặng lẽ dùng source_url.
vcheck(
	'affiliate_url invalid -> data output rỗng, KHÔNG tự fallback về source_url',
	'' === $malicious_result['data']['affiliate_url'] && $malicious_result['data']['affiliate_url'] !== $malicious_result['data']['source_url']
);

// Query string + hash của affiliate_url giữ nguyên y hệt (không đổi thứ tự/encoding).
$affiliate_with_query_hash = $valid_rakuten_payload;
$affiliate_with_query_hash['affiliate_url'] = 'https://hb.afl.rakuten.co.jp/hgc/example?pc=xyz&m=abc&scid=af_pc_etc#reviews';
$query_hash_result = TB247_DM_Products_Validator::validate( $affiliate_with_query_hash, 'rakuten' );
vcheck(
	'affiliate_url có query string + hash -> giữ nguyên y hệt (không đổi thứ tự/encoding)',
	$query_hash_result['valid'] && $query_hash_result['data']['affiliate_url'] === 'https://hb.afl.rakuten.co.jp/hgc/example?pc=xyz&m=abc&scid=af_pc_etc#reviews'
);

echo "\n########################################\n";
echo "# C3. RAKUTEN — full affiliate product URL (scid/sc2id) qua /products validator\n";
echo "########################################\n";

$full_scid_payload = $valid_rakuten_payload;
$full_scid_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=af_pc_ich_pcweb_item_copy';
$full_scid_result = TB247_DM_Products_Validator::validate( $full_scid_payload, 'rakuten' );
vcheck( 'affiliate_url full item.rakuten.co.jp + scid -> valid=true', $full_scid_result['valid'] );
vcheck( 'affiliate_url full giữ nguyên y hệt', $full_scid_result['data']['affiliate_url'] === $full_scid_payload['affiliate_url'] );

$full_sc2id_payload = $valid_rakuten_payload;
$full_sc2id_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?sc2id=af_101_0_0';
vcheck( 'affiliate_url full item.rakuten.co.jp + sc2id -> valid=true', TB247_DM_Products_Validator::validate( $full_sc2id_payload, 'rakuten' )['valid'] );

$full_both_url     = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=af_pc_ich_pcweb_item_copy&sc2id=af_101_0_0#reviews';
$full_both_payload = $valid_rakuten_payload;
$full_both_payload['affiliate_url'] = $full_both_url;
$full_both_result = TB247_DM_Products_Validator::validate( $full_both_payload, 'rakuten' );
vcheck( 'affiliate_url full scid+sc2id -> valid=true', $full_both_result['valid'] );
vcheck( 'affiliate_url full giữ nguyên query + hash y hệt', $full_both_result['data']['affiliate_url'] === $full_both_url );

$full_missing_payload = $valid_rakuten_payload;
$full_missing_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/';
$full_missing_result = TB247_DM_Products_Validator::validate( $full_missing_payload, 'rakuten' );
vcheck( 'affiliate_url full URL không scid/sc2id -> valid=false', false === $full_missing_result['valid'] );
vcheck( 'errors.affiliate_url = missing_affiliate_parameters', 'missing_affiliate_parameters' === ( $full_missing_result['errors']['affiliate_url'] ?? null ) );
vcheck( 'source_url KHÔNG được dùng làm fallback khi affiliate_url thiếu params', '' === $full_missing_result['data']['affiliate_url'] );

$scid_empty_payload = $valid_rakuten_payload;
$scid_empty_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=';
vcheck( 'scid rỗng -> valid=false', false === TB247_DM_Products_Validator::validate( $scid_empty_payload, 'rakuten' )['valid'] );

$sc2id_empty_payload = $valid_rakuten_payload;
$sc2id_empty_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?sc2id=';
vcheck( 'sc2id rỗng -> valid=false', false === TB247_DM_Products_Validator::validate( $sc2id_empty_payload, 'rakuten' )['valid'] );

$utm_only_payload = $valid_rakuten_payload;
$utm_only_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?utm_source=discord';
vcheck( 'chỉ UTM, không affiliate parameter -> valid=false', false === TB247_DM_Products_Validator::validate( $utm_only_payload, 'rakuten' )['valid'] );

$a_r10_payload = $valid_rakuten_payload;
$a_r10_payload['affiliate_url'] = 'https://a.r10.to/fixture123';
$a_r10_result = TB247_DM_Products_Validator::validate( $a_r10_payload, 'rakuten' );
vcheck( 'affiliate_url=a.r10.to (short) -> valid=true', $a_r10_result['valid'] );
vcheck( 'affiliate_url a.r10.to giữ nguyên y hệt', $a_r10_result['data']['affiliate_url'] === $a_r10_payload['affiliate_url'] );

$source_valid_aff_invalid_payload = $valid_rakuten_payload;
$source_valid_aff_invalid_payload['affiliate_url'] = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/';
$source_valid_aff_invalid_result = TB247_DM_Products_Validator::validate( $source_valid_aff_invalid_payload, 'rakuten' );
vcheck(
	'source_url hợp lệ nhưng affiliate_url thiếu params -> TOÀN BỘ request valid=false',
	false === $source_valid_aff_invalid_result['valid']
	&& $source_valid_aff_invalid_result['data']['source_url'] === $valid_rakuten_payload['source_url']
);

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
