<?php
/**
 * Xác thực + làm sạch URL đích trước khi render CTA marketplace trên Landing
 * Page — chặn open redirect/phishing bằng allowlist hostname so khớp chính
 * xác (không strpos/contains). Độc lập với Marketplace Registry (vốn chỉ
 * phục vụ parse payload bot gửi lên, không liên quan validate URL hiển thị)
 * nên hoạt động ngay cho Rakuten/Yahoo dù registry chưa đăng ký 2 sàn đó.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Url_Guard {

	/**
	 * Denylist tham số tracking được phép loại khỏi URL sản phẩm — KHÔNG bao
	 * gồm affiliate tag, mã sản phẩm, hay tham số chính thức nào của
	 * marketplace/affiliate network.
	 */
	const TRACKING_PARAM_DENYLIST = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'fbclid',
		'gclid',
		'msclkid',
	);

	/**
	 * Hostname được phê duyệt cho mỗi sàn. Chỉ thêm domain thực sự cần thiết.
	 *
	 * @return array<string, string[]>
	 */
	private static function get_allowed_hosts_map() {
		return array(
			'amazon'  => array(
				'amazon.co.jp',
				'www.amazon.co.jp',
				'amzn.to',
				'amzn.asia',
			),
			'rakuten' => array(
				'rakuten.co.jp',
				'www.rakuten.co.jp',
				'item.rakuten.co.jp',
				'hb.afl.rakuten.co.jp',
				'r10.to',
				// Exact host riêng cho shop không có canonical item.rakuten.co.jp
				// hợp lệ — đã xác minh thực tế qua audit (rel=canonical/og:url
				// của BicCamera trỏ về chính nó, không phải item.rakuten.co.jp).
				// Chỉ thêm hostname đã xác minh, KHÔNG dùng wildcard *.rakuten.co.jp.
				'biccamera.rakuten.co.jp',
			),
			'yahoo'   => array(
				'shopping.yahoo.co.jp',
				'store.shopping.yahoo.co.jp',
				'ck.jp.ap.valuecommerce.com',
			),
		);
	}

	/**
	 * Xác thực 1 URL đích có thuộc đúng sàn đã phê duyệt không. Trả về URL đã
	 * chuẩn hoá (esc_url_raw) nếu hợp lệ, hoặc null nếu không. Không bao giờ
	 * ném exception/gây fatal error, không fallback sang URL khác.
	 *
	 * @param string $url         URL thô cần kiểm tra.
	 * @param string $marketplace Slug sàn (vd: "amazon").
	 * @return string|null
	 */
	public static function validate_marketplace_url( $url, $marketplace ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return null;
		}

		// Chặn CRLF injection (kể cả dạng encode %0d/%0a) trước khi parse.
		if ( preg_match( '/%0d|%0a|\r|\n/i', $url ) ) {
			self::log_rejection( $marketplace, 'crlf_detected' );
			return null;
		}

		$parts = wp_parse_url( $url );

		if ( false === $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			self::log_rejection( $marketplace, 'missing_host_or_scheme' );
			return null;
		}

		// Chỉ chấp nhận https — chặn http, javascript:, data:, file:, ftp:, và
		// URL protocol-relative (//evil.example, không có scheme rõ ràng).
		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			self::log_rejection( $marketplace, 'scheme_not_https' );
			return null;
		}

		// Chặn URL dạng https://user:pass@host/.
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			self::log_rejection( $marketplace, 'userinfo_present' );
			return null;
		}

		$host              = strtolower( rtrim( $parts['host'], '.' ) );
		$allowed_hosts_map = self::get_allowed_hosts_map();
		$marketplace_key   = sanitize_key( (string) $marketplace );

		if ( ! isset( $allowed_hosts_map[ $marketplace_key ] ) ) {
			self::log_rejection( $marketplace, 'unknown_marketplace' );
			return null;
		}

		// So khớp hostname chính xác — không strpos/contains — để không bị
		// qua mặt bằng subdomain giả dạng như "amazon.co.jp.evil.example".
		if ( ! in_array( $host, $allowed_hosts_map[ $marketplace_key ], true ) ) {
			self::log_rejection( $marketplace, 'host_not_allowed' );
			return null;
		}

		return esc_url_raw( $url );
	}

	/**
	 * Loại bỏ tham số tracking thừa (UTM/fbclid/gclid...) khỏi query string —
	 * giữ nguyên affiliate tag, mã sản phẩm, và mọi tham số khác không nằm
	 * trong denylist. Nếu không có tham số nào bị loại, trả về URL gốc y
	 * nguyên (không rebuild) để tránh mọi rủi ro đổi encoding.
	 *
	 * @param string $url URL đã qua validate_marketplace_url().
	 * @return string
	 */
	public static function strip_tracking_params( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['query'] ) ) {
			return $url;
		}

		parse_str( $parts['query'], $query_args );

		if ( empty( $query_args ) ) {
			return $url;
		}

		$filtered = array();

		foreach ( $query_args as $key => $value ) {
			if ( in_array( $key, self::TRACKING_PARAM_DENYLIST, true ) ) {
				continue;
			}

			$filtered[ $key ] = $value;
		}

		if ( count( $filtered ) === count( $query_args ) ) {
			return $url;
		}

		$rebuilt = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$rebuilt .= ':' . $parts['port'];
		}

		if ( ! empty( $parts['path'] ) ) {
			$rebuilt .= $parts['path'];
		}

		if ( ! empty( $filtered ) ) {
			$rebuilt .= '?' . http_build_query( $filtered, '', '&', PHP_QUERY_RFC3986 );
		}

		if ( ! empty( $parts['fragment'] ) ) {
			$rebuilt .= '#' . $parts['fragment'];
		}

		return $rebuilt;
	}

	/**
	 * Ghi log tối thiểu khi reject 1 URL — chỉ marketplace + mã lý do, không
	 * bao giờ ghi full URL/query string (có thể chứa token affiliate).
	 *
	 * @param string $marketplace Slug sàn.
	 * @param string $reason      Mã lý do ngắn gọn.
	 */
	private static function log_rejection( $marketplace, $reason ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[TB247] Rejected marketplace URL (marketplace=%s, reason=%s)', sanitize_key( (string) $marketplace ), $reason ) );
		}
	}
}

/**
 * Xác thực URL đích có thuộc marketplace đã phê duyệt không — chặn open
 * redirect/phishing trước khi render CTA. Trả về URL đã chuẩn hoá nếu hợp
 * lệ, null nếu không (không render CTA trong trường hợp này).
 *
 * @param string $url         URL thô cần kiểm tra.
 * @param string $marketplace Slug sàn (vd: "amazon").
 * @return string|null
 */
function tb247_validate_marketplace_url( $url, $marketplace ) {
	return TB247_DM_Url_Guard::validate_marketplace_url( $url, $marketplace );
}

/**
 * Loại bỏ tham số tracking thừa (UTM/fbclid/gclid...) khỏi 1 URL đã qua
 * tb247_validate_marketplace_url() — giữ nguyên affiliate tag/mã sản phẩm.
 *
 * @param string $url URL đã qua validate.
 * @return string
 */
function tb247_strip_tracking_params( $url ) {
	return TB247_DM_Url_Guard::strip_tracking_params( $url );
}
