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
				// Short affiliate link host thực tế của tài khoản affiliate hiện
				// tại (khác r10.to trần) — xác minh qua audit thực tế 2026-08-04.
				'a.r10.to',
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
				// Short affiliate link host chính thức của Yahoo (yahoo.jp/{token})
				// — EXACT host, không phải *.yahoo.jp (xem AFFILIATE_SHORT_HOSTS_MAP
				// bên dưới cho phần validate riêng affiliate_url).
				'yahoo.jp',
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
	 * Hostname "short affiliate URL" của Rakuten — link tracking/rút gọn chính
	 * thức, không cần affiliate query parameter để được chấp nhận làm
	 * affiliate_url. Giữ nguyên tên hằng số cũ (không đổi để không phá
	 * backward-compat) — nguồn sự thật thật sự giờ là
	 * AFFILIATE_SHORT_HOSTS_MAP bên dưới, dùng chung cho mọi sàn.
	 */
	const RAKUTEN_SHORT_AFFILIATE_HOSTS = array(
		'r10.to',
		'a.r10.to',
		'hb.afl.rakuten.co.jp',
	);

	/**
	 * Tên query parameter affiliate hợp lệ cho 1 full Rakuten product URL dùng
	 * làm affiliate_url — chỉ cần ít nhất 1 trong 2 có giá trị không rỗng.
	 */
	const RAKUTEN_AFFILIATE_QUERY_PARAMS = array(
		'scid',
		'sc2id',
	);

	/**
	 * Host "short affiliate URL" theo từng sàn — EXACT host, không wildcard.
	 * Rakuten dùng lại đúng RAKUTEN_SHORT_AFFILIATE_HOSTS (không định nghĩa
	 * lại) để 2 hằng số không bao giờ lệch nhau.
	 *
	 * @return array<string, string[]>
	 */
	private static function get_affiliate_short_hosts_map() {
		return array(
			'rakuten' => self::RAKUTEN_SHORT_AFFILIATE_HOSTS,
			// yahoo.jp: short link chính thức của Yahoo!ショッピング (vd
			// https://yahoo.jp/{token}) — EXACT host, KHÔNG *.yahoo.jp.
			'yahoo'   => array( 'yahoo.jp' ),
		);
	}

	/**
	 * Host "full product URL dùng làm affiliate_url" theo từng sàn — bắt buộc
	 * kèm ít nhất 1 query parameter trong get_affiliate_query_params_map()
	 * mới được chấp nhận (tránh nhận nhầm URL sản phẩm thường).
	 *
	 * @return array<string, string[]>
	 */
	private static function get_affiliate_full_hosts_map() {
		return array(
			'rakuten' => array( 'item.rakuten.co.jp', 'biccamera.rakuten.co.jp' ),
			// store.shopping.yahoo.co.jp CHỈ — KHÔNG bao gồm shopping.yahoo.co.jp
			// trần (host đó không theo cấu trúc {shop_code}/{item_code}.html
			// nên không phải "full product URL" theo nghĩa affiliate ở đây).
			'yahoo'   => array( 'store.shopping.yahoo.co.jp' ),
		);
	}

	/**
	 * Tên query parameter affiliate hợp lệ cho "full product URL" theo từng
	 * sàn — chỉ cần 1 trong danh sách có giá trị không rỗng.
	 *
	 * @return array<string, string[]>
	 */
	private static function get_affiliate_query_params_map() {
		return array(
			'rakuten' => self::RAKUTEN_AFFILIATE_QUERY_PARAMS,
			'yahoo'   => array( 'sc_e' ),
		);
	}

	/**
	 * Xác thực 1 URL dùng làm affiliate_url — khác validate_marketplace_url()
	 * (dùng chung cho cả source_url) ở chỗ: với sàn có "full product URL
	 * dùng làm affiliate" (Rakuten, Yahoo), nếu host KHÔNG phải short
	 * affiliate host, bắt buộc phải có ít nhất 1 affiliate query parameter
	 * của đúng sàn đó với giá trị không rỗng — tránh nhận nhầm 1 URL sản
	 * phẩm thường (không mang affiliate tag) làm affiliate_url. Amazon (và
	 * bất kỳ sàn nào không có trong 2 map trên) giữ nguyên hành vi cũ: chỉ
	 * cần qua validate_marketplace_url() là đủ.
	 *
	 * @param string $url         URL thô cần kiểm tra.
	 * @param string $marketplace Slug sàn (vd: "rakuten", "yahoo").
	 * @return array{url: string|null, reason: string|null}
	 */
	public static function validate_affiliate_url( $url, $marketplace ) {
		$validated = self::validate_marketplace_url( $url, $marketplace );

		if ( null === $validated ) {
			return array(
				'url'    => null,
				'reason' => 'invalid_affiliate_url',
			);
		}

		$marketplace_key   = sanitize_key( (string) $marketplace );
		$short_hosts_map    = self::get_affiliate_short_hosts_map();
		$full_hosts_map     = self::get_affiliate_full_hosts_map();
		$query_params_map   = self::get_affiliate_query_params_map();

		// Sàn không có quy tắc affiliate riêng (vd Amazon) — validate_marketplace_url()
		// đã đủ, giữ nguyên hành vi cũ.
		if ( ! isset( $full_hosts_map[ $marketplace_key ] ) && ! isset( $short_hosts_map[ $marketplace_key ] ) ) {
			return array(
				'url'    => $validated,
				'reason' => null,
			);
		}

		$parts = wp_parse_url( $validated );
		$host  = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );

		$short_hosts = $short_hosts_map[ $marketplace_key ] ?? array();

		if ( in_array( $host, $short_hosts, true ) ) {
			return array(
				'url'    => $validated,
				'reason' => null,
			);
		}

		$full_hosts = $full_hosts_map[ $marketplace_key ] ?? array();

		if ( ! in_array( $host, $full_hosts, true ) ) {
			// Host hợp lệ theo validate_marketplace_url() (vd shopping.yahoo.co.jp
			// trần) nhưng KHÔNG nằm trong danh sách host được phép dùng làm
			// affiliate_url — reject rõ ràng thay vì chấp nhận mơ hồ.
			return array(
				'url'    => null,
				'reason' => 'invalid_affiliate_host',
			);
		}

		// Full product host — bắt buộc có ít nhất 1 affiliate query parameter
		// của ĐÚNG sàn đó, không rỗng — nếu không thì đây chỉ là URL sản
		// phẩm thường.
		$query_args = array();

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
		}

		$query_params = $query_params_map[ $marketplace_key ] ?? array();

		foreach ( $query_params as $param_name ) {
			if ( isset( $query_args[ $param_name ] ) && '' !== trim( (string) $query_args[ $param_name ] ) ) {
				return array(
					'url'    => $validated,
					'reason' => null,
				);
			}
		}

		return array(
			'url'    => null,
			'reason' => 'missing_affiliate_parameters',
		);
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
 * Xác thực 1 URL dùng làm affiliate_url — xem TB247_DM_Url_Guard::validate_affiliate_url().
 *
 * @param string $url         URL thô cần kiểm tra.
 * @param string $marketplace Slug sàn (vd: "rakuten").
 * @return array{url: string|null, reason: string|null}
 */
function tb247_validate_affiliate_url( $url, $marketplace ) {
	return TB247_DM_Url_Guard::validate_affiliate_url( $url, $marketplace );
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
