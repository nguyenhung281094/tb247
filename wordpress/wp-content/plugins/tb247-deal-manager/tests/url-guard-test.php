<?php
/**
 * Test standalone cho TB247_DM_Url_Guard — chạy độc lập ngoài WordPress
 * (không cần database/bootstrap đầy đủ), chỉ stub 3 hàm WP tối thiểu mà
 * class-url-guard.php cần. Dùng để hồi quy mỗi khi thêm marketplace mới.
 *
 * Chạy: php tests/url-guard-test.php
 *
 * @package TB247_Deal_Manager
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function esc_url_raw( $url ) {
	return $url;
}

require __DIR__ . '/../includes/marketplaces/class-url-guard.php';

$pass = 0;
$fail = 0;

/**
 * @param string      $label
 * @param string|null $actual
 * @param bool        $expected_is_valid
 */
function check( $label, $actual, $expected_is_valid ) {
	global $pass, $fail;
	$is_valid = null !== $actual;
	$ok       = $is_valid === $expected_is_valid;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - $label => " . var_export( $actual, true ) . "\n";
	$ok ? $pass++ : $fail++;
}

function check_bool( $label, $ok ) {
	global $pass, $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - $label\n";
	$ok ? $pass++ : $fail++;
}

echo "########################################\n";
echo "# A. AMAZON — ACCEPT hợp lệ\n";
echo "########################################\n";
check( 'amazon www', TB247_DM_Url_Guard::validate_marketplace_url( 'https://www.amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22', 'amazon' ), true );
check( 'amazon apex', TB247_DM_Url_Guard::validate_marketplace_url( 'https://amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22', 'amazon' ), true );
check( 'amzn.to', TB247_DM_Url_Guard::validate_marketplace_url( 'https://amzn.to/abc123', 'amazon' ), true );
check( 'yahoo shopping (khác sàn, kiểm tra không ảnh hưởng lẫn nhau)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://shopping.yahoo.co.jp/products/12345', 'yahoo' ), true );

echo "\n########################################\n";
echo "# B. AMAZON — REJECT phải chặn\n";
echo "########################################\n";
check( 'subdomain giả dạng', TB247_DM_Url_Guard::validate_marketplace_url( 'https://amazon.co.jp.evil.example/', 'amazon' ), false );
check( 'open redirect qua query', TB247_DM_Url_Guard::validate_marketplace_url( 'https://evil.example/?next=amazon.co.jp', 'amazon' ), false );
check( 'javascript scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'javascript:alert(1)', 'amazon' ), false );
check( 'data scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'data:text/html,<script>alert(1)</script>', 'amazon' ), false );
check( 'protocol-relative', TB247_DM_Url_Guard::validate_marketplace_url( '//evil.example/', 'amazon' ), false );
check( 'userinfo trong URL', TB247_DM_Url_Guard::validate_marketplace_url( 'https://user:pass@amazon.co.jp/', 'amazon' ), false );
check( 'CRLF encoded', TB247_DM_Url_Guard::validate_marketplace_url( 'https://evil.example/%0d%0aLocation:https://phish.example', 'amazon' ), false );
check( 'http (không phải https)', TB247_DM_Url_Guard::validate_marketplace_url( 'http://amazon.co.jp/dp/B0GWHBFNGG', 'amazon' ), false );
check( 'ftp scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'ftp://amazon.co.jp/', 'amazon' ), false );
check( 'file scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'file:///etc/passwd', 'amazon' ), false );
check( 'sai sàn (amazon URL nhưng khai marketplace=rakuten)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://amazon.co.jp/dp/B0GWHBFNGG', 'rakuten' ), false );
check( 'marketplace không tồn tại', TB247_DM_Url_Guard::validate_marketplace_url( 'https://amazon.co.jp/dp/B0GWHBFNGG', 'ebay' ), false );
check( 'punycode lạ không nằm allowlist', TB247_DM_Url_Guard::validate_marketplace_url( 'https://xn--80ak6aa92e.com/', 'amazon' ), false );
check( 'rỗng', TB247_DM_Url_Guard::validate_marketplace_url( '', 'amazon' ), false );

echo "\n########################################\n";
echo "# C. AMAZON — Query sanitation\n";
echo "########################################\n";
$dirty = 'https://www.amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22&utm_source=discord&fbclid=abc123&gclid=xyz';
$clean = TB247_DM_Url_Guard::strip_tracking_params( $dirty );
echo "input : $dirty\n";
echo "output: $clean\n";
check_bool(
	'giữ affiliate tag, xóa utm/fbclid/gclid',
	false !== strpos( $clean, 'tag=tb247fun-22' )
		&& false === strpos( $clean, 'utm_source' )
		&& false === strpos( $clean, 'fbclid' )
		&& false === strpos( $clean, 'gclid' )
);

$already_clean = 'https://www.amazon.co.jp/dp/B0GWHBFNGG?tag=tb247fun-22';
$result_clean  = TB247_DM_Url_Guard::strip_tracking_params( $already_clean );
check_bool( 'URL đã sạch giữ nguyên y hệt (không rebuild)', $result_clean === $already_clean );

echo "\n########################################\n";
echo "# D. RAKUTEN — ACCEPT hợp lệ\n";
echo "########################################\n";
check( 'rakuten apex', TB247_DM_Url_Guard::validate_marketplace_url( 'https://rakuten.co.jp/', 'rakuten' ), true );
check( 'rakuten www', TB247_DM_Url_Guard::validate_marketplace_url( 'https://www.rakuten.co.jp/', 'rakuten' ), true );
check( 'rakuten item', TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp/example-shop/example-item/', 'rakuten' ), true );
check( 'rakuten hb.afl (affiliate)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://hb.afl.rakuten.co.jp/example', 'rakuten' ), true );
check( 'rakuten r10.to (short link)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://r10.to/example', 'rakuten' ), true );
check( 'rakuten a.r10.to (short link thật của tài khoản affiliate)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://a.r10.to/fixture123', 'rakuten' ), true );
check( 'rakuten biccamera exact host (shop không có canonical item.rakuten.co.jp)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://biccamera.rakuten.co.jp/item/4906128579038/', 'rakuten' ), true );

echo "\n########################################\n";
echo "# E. RAKUTEN — REJECT phải chặn\n";
echo "########################################\n";
check( 'rakuten.co.jp.evil.example', TB247_DM_Url_Guard::validate_marketplace_url( 'https://rakuten.co.jp.evil.example/', 'rakuten' ), false );
check( 'item.rakuten.co.jp.evil.example', TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp.evil.example/', 'rakuten' ), false );
check( 'biccamera.rakuten.co.jp.evil.example (giả dạng host mới thêm)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://biccamera.rakuten.co.jp.evil.example/item/123/', 'rakuten' ), false );
check( 'fakebiccamera.rakuten.co.jp (không tự accept, không phải exact host đã thêm)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://fakebiccamera.rakuten.co.jp/item/123/', 'rakuten' ), false );
check( 'http://biccamera.rakuten.co.jp (không phải https)', TB247_DM_Url_Guard::validate_marketplace_url( 'http://biccamera.rakuten.co.jp/item/4906128579038/', 'rakuten' ), false );
check( 'biccamera.rakuten.co.jp có userinfo', TB247_DM_Url_Guard::validate_marketplace_url( 'https://user:pass@biccamera.rakuten.co.jp/item/4906128579038/', 'rakuten' ), false );
check( 'fake-rakuten.co.jp', TB247_DM_Url_Guard::validate_marketplace_url( 'https://fake-rakuten.co.jp/', 'rakuten' ), false );
check( 'evilrakuten.co.jp', TB247_DM_Url_Guard::validate_marketplace_url( 'https://evilrakuten.co.jp/', 'rakuten' ), false );
check( 'open redirect qua query (?next=)', TB247_DM_Url_Guard::validate_marketplace_url( 'https://evil.example/?next=https://rakuten.co.jp/', 'rakuten' ), false );
check( 'http (không phải https)', TB247_DM_Url_Guard::validate_marketplace_url( 'http://item.rakuten.co.jp/example/', 'rakuten' ), false );
check( 'protocol-relative', TB247_DM_Url_Guard::validate_marketplace_url( '//item.rakuten.co.jp/example/', 'rakuten' ), false );
check( 'javascript scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'javascript:alert(1)', 'rakuten' ), false );
check( 'data scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'data:text/html,test', 'rakuten' ), false );
check( 'ftp scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'ftp://item.rakuten.co.jp/', 'rakuten' ), false );
check( 'file scheme', TB247_DM_Url_Guard::validate_marketplace_url( 'file:///etc/passwd', 'rakuten' ), false );
check( 'userinfo trong URL', TB247_DM_Url_Guard::validate_marketplace_url( 'https://user:pass@item.rakuten.co.jp/', 'rakuten' ), false );
check( 'CRLF encoded', TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp/%0d%0aLocation:https://evil.example/', 'rakuten' ), false );
check( 'rỗng', TB247_DM_Url_Guard::validate_marketplace_url( '', 'rakuten' ), false );
check( 'marketplace identifier không hợp lệ (typo "rakutenn")', TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp/example/', 'rakutenn' ), false );
check( 'Rakuten URL validate dưới marketplace amazon', TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp/example/', 'amazon' ), false );
check( 'Amazon URL validate dưới marketplace rakuten', TB247_DM_Url_Guard::validate_marketplace_url( 'https://www.amazon.co.jp/dp/B0GWHBFNGG', 'rakuten' ), false );
check( 'punycode hostname lạ không nằm allowlist', TB247_DM_Url_Guard::validate_marketplace_url( 'https://xn--80ak6aa92e.com/', 'rakuten' ), false );

echo "\n########################################\n";
echo "# F. RAKUTEN — chuẩn hoá hostname phải nhất quán (ACCEPT vì host thật khớp allowlist sau chuẩn hoá)\n";
echo "########################################\n";
check(
	'trailing-dot hostname hợp lệ (FQDN) — phải accept nếu rtrim trailing dot đúng',
	TB247_DM_Url_Guard::validate_marketplace_url( 'https://item.rakuten.co.jp./example-shop/example-item/', 'rakuten' ),
	true
);
check(
	'mixed-case hostname (viết hoa toàn bộ) — phải accept nhất quán sau strtolower',
	TB247_DM_Url_Guard::validate_marketplace_url( 'https://ITEM.RAKUTEN.CO.JP/example-shop/example-item/', 'rakuten' ),
	true
);
check(
	'mixed-case hostname (viết hoa xen kẽ) — phải accept nhất quán sau strtolower',
	TB247_DM_Url_Guard::validate_marketplace_url( 'https://Item.Rakuten.Co.Jp/example-shop/example-item/', 'rakuten' ),
	true
);

echo "\n########################################\n";
echo "# G. RAKUTEN — Query sanitation\n";
echo "########################################\n";
$rak_dirty = 'https://item.rakuten.co.jp/example-shop/example-item/?scid=af_pc_etc&s-id=ph_pc_itemname&utm_source=discord&utm_campaign=promo&fbclid=abc123&gclid=xyz#reviews';
$rak_clean = TB247_DM_Url_Guard::strip_tracking_params( $rak_dirty );
echo "input : $rak_dirty\n";
echo "output: $rak_clean\n";
check_bool(
	'xóa đúng utm_source/utm_campaign/fbclid/gclid',
	false === strpos( $rak_clean, 'utm_source' )
		&& false === strpos( $rak_clean, 'utm_campaign' )
		&& false === strpos( $rak_clean, 'fbclid' )
		&& false === strpos( $rak_clean, 'gclid' )
);
check_bool(
	'giữ nguyên tham số affiliate Rakuten mẫu (scid, s-id)',
	false !== strpos( $rak_clean, 'scid=af_pc_etc' ) && false !== strpos( $rak_clean, 's-id=ph_pc_itemname' )
);
check_bool(
	'giữ nguyên path (item code) và fragment',
	false !== strpos( $rak_clean, '/example-shop/example-item/' ) && false !== strpos( $rak_clean, '#reviews' )
);

$rak_already_clean = 'https://item.rakuten.co.jp/example-shop/example-item/?scid=af_pc_etc';
$rak_result_clean   = TB247_DM_Url_Guard::strip_tracking_params( $rak_already_clean );
check_bool( 'URL Rakuten đã sạch giữ nguyên y hệt (không rebuild)', $rak_result_clean === $rak_already_clean );

echo "\n########################################\n";
echo "# H. RAKUTEN — validate_affiliate_url() (short vs full affiliate URL)\n";
echo "########################################\n";

$aff_r10       = TB247_DM_Url_Guard::validate_affiliate_url( 'https://r10.to/fixture123', 'rakuten' );
check_bool( 'r10.to (short) -> PASS', null !== $aff_r10['url'] );

$aff_a_r10 = TB247_DM_Url_Guard::validate_affiliate_url( 'https://a.r10.to/fixture123', 'rakuten' );
check_bool( 'a.r10.to (short) -> PASS', null !== $aff_a_r10['url'] );

$aff_hbafl = TB247_DM_Url_Guard::validate_affiliate_url( 'https://hb.afl.rakuten.co.jp/hgc/fixture', 'rakuten' );
check_bool( 'hb.afl.rakuten.co.jp (short) -> PASS', null !== $aff_hbafl['url'] );

$aff_full_scid = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=af_pc_ich_pcweb_item_copy', 'rakuten' );
check_bool( 'item.rakuten.co.jp + scid (full) -> PASS', null !== $aff_full_scid['url'] );

$aff_full_sc2id = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?sc2id=af_101_0_0', 'rakuten' );
check_bool( 'item.rakuten.co.jp + sc2id (full) -> PASS', null !== $aff_full_sc2id['url'] );

$aff_full_both_url = 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=af_pc_ich_pcweb_item_copy&sc2id=af_101_0_0#reviews';
$aff_full_both     = TB247_DM_Url_Guard::validate_affiliate_url( $aff_full_both_url, 'rakuten' );
check_bool( 'item.rakuten.co.jp + scid + sc2id (full) -> PASS', null !== $aff_full_both['url'] );
check_bool( 'full affiliate URL giữ nguyên query + hash y hệt', $aff_full_both['url'] === $aff_full_both_url );

$aff_biccamera = TB247_DM_Url_Guard::validate_affiliate_url( 'https://biccamera.rakuten.co.jp/item/fixture-item/?scid=af_pc_ich_pcweb_item_copy', 'rakuten' );
check_bool( 'biccamera.rakuten.co.jp + scid (full) -> PASS', null !== $aff_biccamera['url'] );

$aff_full_missing = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/', 'rakuten' );
check_bool( 'item.rakuten.co.jp không scid/sc2id -> FAIL', null === $aff_full_missing['url'] );
check_bool( 'reason = missing_affiliate_parameters', 'missing_affiliate_parameters' === $aff_full_missing['reason'] );

$aff_scid_empty = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?scid=', 'rakuten' );
check_bool( 'scid rỗng -> FAIL (missing_affiliate_parameters)', null === $aff_scid_empty['url'] && 'missing_affiliate_parameters' === $aff_scid_empty['reason'] );

$aff_sc2id_empty = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?sc2id=', 'rakuten' );
check_bool( 'sc2id rỗng -> FAIL (missing_affiliate_parameters)', null === $aff_sc2id_empty['url'] && 'missing_affiliate_parameters' === $aff_sc2id_empty['reason'] );

$aff_only_utm = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp/fixture-shop/fixture-item/?utm_source=discord', 'rakuten' );
check_bool( 'chỉ UTM, không affiliate parameter -> FAIL', null === $aff_only_utm['url'] && 'missing_affiliate_parameters' === $aff_only_utm['reason'] );

$aff_malicious_1 = TB247_DM_Url_Guard::validate_affiliate_url( 'https://evil.a.r10.to/x', 'rakuten' );
check_bool( 'evil.a.r10.to -> FAIL (host không exact match)', null === $aff_malicious_1['url'] );

$aff_malicious_2 = TB247_DM_Url_Guard::validate_affiliate_url( 'https://a.r10.to.evil.example/x', 'rakuten' );
check_bool( 'a.r10.to.evil.example -> FAIL (host không exact match)', null === $aff_malicious_2['url'] );

$aff_malicious_3 = TB247_DM_Url_Guard::validate_affiliate_url( 'https://item.rakuten.co.jp.evil.example/x?scid=abc', 'rakuten' );
check_bool( 'item.rakuten.co.jp.evil.example -> FAIL (host không exact match)', null === $aff_malicious_3['url'] );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
