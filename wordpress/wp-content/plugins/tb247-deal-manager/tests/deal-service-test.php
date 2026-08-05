<?php
/**
 * Test standalone cho TB247_DM_Deal_Service::should_update_recommended_date()
 * (§8 — quy tắc 7 ngày cho 更新日) + structural check cho write_meta()/
 * refresh_recommended_data()/find_due_recommended_deals() và wiring REST mới
 * (/deals/refresh, /deals/due). Chỉ gọi hàm THUẦN (không đụng WP_Query/
 * get_post_meta thật) — phần còn lại verify qua production regression sau
 * deploy, cùng convention với các test standalone khác trong repo.
 *
 * Chạy: php tests/deal-service-test.php
 *
 * @package TB247_Deal_Manager
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

require __DIR__ . '/../includes/services/class-deal-service.php';

$pass = 0;
$fail = 0;

function check( $label, $ok ) {
	global $pass, $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - $label\n";
	$ok ? $pass++ : $fail++;
}

echo "########################################\n";
echo "# TB247_DM_Deal_Service::should_update_recommended_date() — quy tắc 7 ngày (§8)\n";
echo "########################################\n";

$now = strtotime( '2026-08-06 02:30:00' );

check(
	'dữ liệu KHÔNG đổi -> không bao giờ đổi ngày (dù đã lâu)',
	false === TB247_DM_Deal_Service::should_update_recommended_date( false, '2026-01-01 00:00:00', $now )
);

check(
	'dữ liệu đổi + CHƯA từng có ngày -> set lần đầu',
	true === TB247_DM_Deal_Service::should_update_recommended_date( true, '', $now )
);

check(
	'dữ liệu đổi + chưa từng có ngày (null) -> set lần đầu',
	true === TB247_DM_Deal_Service::should_update_recommended_date( true, null, $now )
);

$less_than_7_days_ago = gmdate( 'Y-m-d H:i:s', $now - ( 3 * DAY_IN_SECONDS ) );
check(
	'dữ liệu đổi + đã có ngày nhưng CHƯA đủ 7 ngày (3 ngày) -> giữ nguyên',
	false === TB247_DM_Deal_Service::should_update_recommended_date( true, $less_than_7_days_ago, $now )
);

$exactly_7_days_ago = gmdate( 'Y-m-d H:i:s', $now - ( 7 * DAY_IN_SECONDS ) );
check(
	'dữ liệu đổi + đúng 7 ngày (biên >=) -> đổi',
	true === TB247_DM_Deal_Service::should_update_recommended_date( true, $exactly_7_days_ago, $now )
);

$more_than_7_days_ago = gmdate( 'Y-m-d H:i:s', $now - ( 10 * DAY_IN_SECONDS ) );
check(
	'dữ liệu đổi + đã quá 7 ngày (10 ngày) -> đổi',
	true === TB247_DM_Deal_Service::should_update_recommended_date( true, $more_than_7_days_ago, $now )
);

check(
	'giá trị ngày cũ hỏng/không parse được -> coi như chưa có, set lại (không Fatal)',
	true === TB247_DM_Deal_Service::should_update_recommended_date( true, 'not-a-real-date', $now )
);

check(
	'dữ liệu không đổi + chưa từng có ngày -> vẫn KHÔNG set (không set ngày nếu chả có gì thay đổi thật)',
	false === TB247_DM_Deal_Service::should_update_recommended_date( false, '', $now )
);

echo "\n########################################\n";
echo "# Structural check: write_meta() — affiliate_url KHÔNG bị xoá khi payload không gửi (§4/§7/§11.2)\n";
echo "########################################\n";

$service_source = file_get_contents( __DIR__ . '/../includes/services/class-deal-service.php' );

check(
	'affiliate_url chỉ ghi đè khi payload có giá trị KHÔNG rỗng, hoặc deal vừa tạo mới',
	strpos( $service_source, '$affiliate_provided || $is_new_affiliate' ) !== false
);
check(
	'is_new_affiliate xác định qua metadata_exists() (đúng deal MỚI, không đoán qua field khác)',
	strpos( $service_source, "metadata_exists( 'post', \$post_id, '_tb247_affiliate_url' )" ) !== false
);

echo "\n########################################\n";
echo "# Structural check: update_flags() — set 更新日 lần đầu khi bật recommended (§8.1)\n";
echo "########################################\n";

check(
	'update_flags() set _tb247_recommended_updated_at khi is_recommended=true VÀ chưa từng có ngày',
	strpos( $service_source, "\$is_recommended && '' === trim( (string) get_post_meta( \$post_id, '_tb247_recommended_updated_at', true ) )" ) !== false
);

echo "\n########################################\n";
echo "# Structural check: refresh_recommended_data() — update-only, KHÔNG đụng flags/affiliate_url (§7/§11.2)\n";
echo "########################################\n";

$refresh_fn_start = strpos( $service_source, 'public static function refresh_recommended_data' );
$refresh_fn_end   = strpos( $service_source, "\n\t}\n", $refresh_fn_start );
$refresh_fn_source = substr( $service_source, $refresh_fn_start, $refresh_fn_end - $refresh_fn_start );

check( 'refresh_recommended_data() KHÔNG chứa "_tb247_is_recommended" (không đụng flag)', strpos( $refresh_fn_source, '_tb247_is_recommended' ) === false );
check( 'refresh_recommended_data() KHÔNG chứa "_tb247_is_sale" (không đụng flag)', strpos( $refresh_fn_source, '_tb247_is_sale' ) === false );
check( 'refresh_recommended_data() KHÔNG chứa "_tb247_affiliate_url" (không đổi affiliate)', strpos( $refresh_fn_source, '_tb247_affiliate_url' ) === false );
check( 'refresh_recommended_data() gọi should_update_recommended_date() (dùng đúng quy tắc 7 ngày, không tự set tuỳ tiện)', strpos( $refresh_fn_source, 'self::should_update_recommended_date(' ) !== false );
check( 'refresh_recommended_data() luôn cập nhật _tb247_last_checked_at', strpos( $refresh_fn_source, '_tb247_last_checked_at' ) !== false );

echo "\n########################################\n";
echo "# Structural check: find_due_recommended_deals() — chỉ recommended + due, không tạo/xoá gì\n";
echo "########################################\n";

$due_fn_start = strpos( $service_source, 'public static function find_due_recommended_deals' );
$due_fn_source = substr( $service_source, $due_fn_start, 2200 );

check( 'lọc theo _tb247_is_recommended = 1', strpos( $due_fn_source, "'key'   => '_tb247_is_recommended'" ) !== false );
check( 'điều kiện due: NOT EXISTS hoặc rỗng hoặc <= threshold 7 ngày', strpos( $due_fn_source, 'NOT EXISTS' ) !== false && strpos( $due_fn_source, '7 * DAY_IN_SECONDS' ) !== false );
check( 'dùng WP_Query (không tự viết SQL)', strpos( $due_fn_source, 'new WP_Query(' ) !== false );
check( 'không có wp_insert_post/wp_delete_post trong hàm (chỉ đọc, không tạo/xoá)', strpos( $due_fn_source, 'wp_insert_post' ) === false && strpos( $due_fn_source, 'wp_delete_post' ) === false );

echo "\n########################################\n";
echo "# Structural check: REST wiring — /deals/refresh + /deals/due đăng ký đúng, auth bắt buộc\n";
echo "########################################\n";

$refresh_controller_source = file_get_contents( __DIR__ . '/../includes/rest/class-deals-refresh-rest-controller.php' );

check( 'route /deals/refresh đăng ký (EDITABLE = PUT/PATCH/POST)', strpos( $refresh_controller_source, "'/deals/refresh'" ) !== false );
check( 'route /deals/due đăng ký (READABLE = GET)', strpos( $refresh_controller_source, "'/deals/due'" ) !== false );
check( 'handle_refresh() auth qua TB247_DM_Auth::check_bearer() (cùng token /products đang dùng)', substr_count( $refresh_controller_source, 'TB247_DM_Auth::check_bearer( $request )' ) === 2 );
check( 'handle_refresh() KHÔNG tạo deal khi not found — trả 404, không có wp_insert_post/create_or_update', (function () use ( $refresh_controller_source ) {
	$fn_start = strpos( $refresh_controller_source, 'public static function handle_refresh' );
	$fn_body  = substr( $refresh_controller_source, $fn_start, 1800 );
	return strpos( $fn_body, "'code'    => 'not_found'" ) !== false
		&& strpos( $fn_body, 'create_or_update' ) === false
		&& strpos( $fn_body, 'wp_insert_post' ) === false;
} )() );
check( 'handle_refresh() KHÔNG đọc is_recommended/is_sale/affiliate_url từ payload', (function () use ( $refresh_controller_source ) {
	$fn_start = strpos( $refresh_controller_source, 'public static function handle_refresh' );
	$fn_end   = strpos( $refresh_controller_source, "\n\t}\n", $fn_start );
	$fn_body  = substr( $refresh_controller_source, $fn_start, $fn_end - $fn_start );
	return strpos( $fn_body, "payload['is_recommended']" ) === false
		&& strpos( $fn_body, "payload['is_sale']" ) === false
		&& strpos( $fn_body, "payload['affiliate_url']" ) === false;
} )() );
check( 'due_deal_to_array() KHÔNG trả affiliate_url (không public dữ liệu nội bộ hơn mức cần — §11.3)', (function () use ( $refresh_controller_source ) {
	$fn_start = strpos( $refresh_controller_source, 'private static function due_deal_to_array' );
	$fn_end   = strpos( $refresh_controller_source, "\n\t}\n", $fn_start );
	$fn_body  = substr( $refresh_controller_source, $fn_start, $fn_end - $fn_start );
	return strpos( $fn_body, 'affiliate_url' ) === false;
} )() );
check( '/deals/due có MAX_DUE_LIMIT (client không tự yêu cầu vượt trần)', strpos( $refresh_controller_source, 'MAX_DUE_LIMIT' ) !== false );

$plugin_bootstrap_source = file_get_contents( __DIR__ . '/../includes/class-plugin.php' );
check( 'TB247_DM_Deals_Refresh_Rest_Controller được hook vào rest_api_init', strpos( $plugin_bootstrap_source, "array( 'TB247_DM_Deals_Refresh_Rest_Controller', 'register_routes' )" ) !== false );

$main_plugin_file_source = file_get_contents( __DIR__ . '/../tb247-deal-manager.php' );
check( 'class-deals-refresh-rest-controller.php được require', strpos( $main_plugin_file_source, "includes/rest/class-deals-refresh-rest-controller.php" ) !== false );

echo "\n########################################\n";
echo "# Structural check: /deals/flags — cập nhật affiliate_url optional cho deal ĐÃ tồn tại (§4.4/§12)\n";
echo "########################################\n";

$lookup_controller_source = file_get_contents( __DIR__ . '/../includes/rest/class-deals-lookup-rest-controller.php' );
$flags_fn_start           = strpos( $lookup_controller_source, 'public static function handle_update_flags' );
$flags_fn_end             = strpos( $lookup_controller_source, "\n\t}\n", $flags_fn_start );
$flags_fn_source          = substr( $lookup_controller_source, $flags_fn_start, $flags_fn_end - $flags_fn_start );

check( 'handle_update_flags() chỉ ghi affiliate_url khi payload có giá trị KHÔNG rỗng', strpos( $flags_fn_source, "isset( \$payload['affiliate_url'] ) && '' !== trim( (string) \$payload['affiliate_url'] )" ) !== false );
check( 'affiliate_url mới validate qua tb247_validate_affiliate_url() theo đúng marketplace của deal (defense-in-depth, không tin payload)', strpos( $flags_fn_source, 'tb247_validate_affiliate_url( $raw_affiliate, $marketplace )' ) !== false );
check( 'affiliate_url không hợp lệ -> 400 invalid_affiliate_url, KHÔNG âm thầm bỏ qua/ghi rác', strpos( $flags_fn_source, "'code'    => 'invalid_affiliate_url'" ) !== false );

echo "\n########################################\n";
echo "# Structural check: products-validator — affiliate_url Rakuten optional (§4)\n";
echo "########################################\n";

$validator_source = file_get_contents( __DIR__ . '/../includes/rest/class-products-validator.php' );
check( 'validate_rakuten() gọi validate_affiliate_url_field() với required=false', strpos( $validator_source, "self::validate_affiliate_url_field( \$payload, 'affiliate_url', \$marketplace, false, \$errors )" ) !== false );

echo "\n########################################\n";
echo "TỔNG KẾT: $pass PASS, $fail FAIL\n";
echo "########################################\n";

exit( $fail > 0 ? 1 : 0 );
