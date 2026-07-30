<?php
/**
 * Xác thực request REST API từ bot bằng header X-TB247-API-KEY, so khớp
 * hằng số TB247_API_KEY khai báo trong wp-config.php (không lưu trong DB
 * để tránh lộ qua export/backup).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Auth {

	/**
	 * permission_callback cho route REST API.
	 *
	 * @param WP_REST_Request $request Request hiện tại.
	 * @return true|WP_Error
	 */
	public static function check( WP_REST_Request $request ) {
		if ( ! defined( 'TB247_API_KEY' ) || '' === TB247_API_KEY ) {
			return new WP_Error(
				'tb247_api_key_not_configured',
				__( 'Site chưa khai báo hằng số TB247_API_KEY trong wp-config.php.', 'tb247-deal-manager' ),
				array( 'status' => 500 )
			);
		}

		$provided = $request->get_header( 'x-tb247-api-key' );

		if ( ! $provided || ! hash_equals( TB247_API_KEY, $provided ) ) {
			return new WP_Error(
				'tb247_unauthorized',
				__( 'API key không hợp lệ.', 'tb247-deal-manager' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Xác thực endpoint /products bằng header "Authorization: Bearer <token>".
	 * Token lấy theo thứ tự ưu tiên: hằng số TB247_API_TOKEN trong wp-config.php,
	 * rồi tới biến môi trường cùng tên. Không log giá trị token ở bất kỳ đâu.
	 *
	 * @param WP_REST_Request $request Request hiện tại.
	 * @return true|WP_Error
	 */
	public static function check_bearer( WP_REST_Request $request ) {
		$configured_token = self::get_configured_bearer_token();

		if ( null === $configured_token ) {
			return new WP_Error(
				'tb247_token_not_configured',
				__( 'Site chưa cấu hình TB247_API_TOKEN.', 'tb247-deal-manager' ),
				array( 'status' => 500 )
			);
		}

		$provided_token = self::extract_bearer_token( $request );

		if ( null === $provided_token || ! hash_equals( $configured_token, $provided_token ) ) {
			return new WP_Error(
				'tb247_unauthorized',
				__( 'Invalid or missing API token.', 'tb247-deal-manager' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Đọc token đã cấu hình phía server: ưu tiên constant, sau đó env var.
	 * Không dùng option trong database vì dự án hiện chưa có cơ chế cấu hình
	 * kiểu đó cho token/API key.
	 *
	 * @return string|null
	 */
	private static function get_configured_bearer_token() {
		if ( defined( 'TB247_API_TOKEN' ) && '' !== TB247_API_TOKEN ) {
			return TB247_API_TOKEN;
		}

		$env_token = getenv( 'TB247_API_TOKEN' );

		return ( false !== $env_token && '' !== $env_token ) ? $env_token : null;
	}

	/**
	 * Lấy token từ header Authorization, chỉ chấp nhận đúng định dạng "Bearer <token>".
	 *
	 * @param WP_REST_Request $request Request hiện tại.
	 * @return string|null
	 */
	private static function extract_bearer_token( WP_REST_Request $request ) {
		$header = $request->get_header( 'authorization' );

		if ( ! $header || 0 !== stripos( trim( $header ), 'Bearer ' ) ) {
			return null;
		}

		$token = trim( substr( trim( $header ), 7 ) );

		return ( '' !== $token ) ? $token : null;
	}
}
