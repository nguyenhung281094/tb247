<?php
/**
 * Validate + sanitize payload của endpoint POST /wp-json/tb247/v1/products.
 *
 * Tách riêng khỏi Amazon_Marketplace::validate() vì contract của /products
 * (field name, quy tắc kiểm tra chi tiết hơn) khác với /deals — nhưng cả 2
 * endpoint vẫn cùng ghi dữ liệu qua TB247_DM_Deal_Service ở tầng dưới.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Products_Validator {

	/**
	 * Kiểm tra + làm sạch payload thô từ request. Amazon (mặc định, kể cả khi
	 * không truyền $marketplace — giữ đúng hành vi hiện tại) đi qua nguyên
	 * validate_amazon() không đổi 1 dòng. Sàn khác (rakuten...) đi qua
	 * validate_rakuten() — field khác (shop_code/item_code thay asin),
	 * affiliate_url/source_url phải qua allowlist tb247_validate_marketplace_url()
	 * thay vì chỉ check http/https chung chung.
	 *
	 * @param array  $payload     Dữ liệu thô từ JSON body.
	 * @param string $marketplace Slug sàn ("amazon" mặc định).
	 * @return array{valid: bool, errors: array<string,string>, data: array}
	 */
	public static function validate( array $payload, $marketplace = 'amazon' ) {
		$marketplace = sanitize_key( (string) $marketplace );

		if ( '' === $marketplace || 'amazon' === $marketplace ) {
			return self::validate_amazon( $payload );
		}

		return self::validate_rakuten( $payload, $marketplace );
	}

	/**
	 * Validate payload Amazon — logic gốc, không đổi để không ảnh hưởng luồng
	 * Amazon đang chạy production.
	 *
	 * @param array $payload Dữ liệu thô từ JSON body.
	 * @return array{valid: bool, errors: array<string,string>, data: array}
	 */
	private static function validate_amazon( array $payload ) {
		$errors = array();
		$data   = array();

		$data['asin'] = self::validate_asin( $payload, $errors );
		$data['jan']  = self::validate_jan( $payload, $errors );

		$data['title'] = self::validate_title( $payload, $errors );
		$data['price'] = self::validate_price( $payload, $errors );
		$data['image'] = self::validate_url_field( $payload, 'image', __( 'Image must be a valid http or https URL.', 'tb247-deal-manager' ), true, $errors );

		$data['affiliate_url'] = self::validate_url_field( $payload, 'affiliate_url', __( 'Affiliate URL must be a valid http or https URL.', 'tb247-deal-manager' ), true, $errors );

		$data['brand'] = ! empty( $payload['brand'] ) ? sanitize_text_field( (string) $payload['brand'] ) : '';

		$data['in_stock'] = self::validate_in_stock( $payload );

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
			'data'   => $data,
		);
	}

	/**
	 * Validate payload Rakuten (và sàn khác ngoài Amazon nói chung). Không có
	 * ASIN — dùng shop_code/item_code. affiliate_url/source_url phải vượt qua
	 * allowlist đúng sàn (tb247_validate_marketplace_url()), không chỉ check
	 * http/https chung chung như Amazon — WordPress không tin tuyệt đối dữ
	 * liệu bot gửi lên.
	 *
	 * @param array  $payload     Dữ liệu thô từ JSON body.
	 * @param string $marketplace Slug sàn (vd: "rakuten").
	 * @return array{valid: bool, errors: array<string,string>, data: array}
	 */
	private static function validate_rakuten( array $payload, $marketplace ) {
		$errors = array();
		$data   = array();

		$data['shop_code'] = self::validate_required_code( $payload, 'shop_code', __( 'shop_code is required.', 'tb247-deal-manager' ), $errors );
		$data['item_code'] = self::validate_required_code( $payload, 'item_code', __( 'item_code is required.', 'tb247-deal-manager' ), $errors );
		$data['jan']       = self::validate_jan( $payload, $errors );

		$data['title'] = self::validate_title( $payload, $errors );
		$data['price'] = self::validate_positive_price( $payload, $errors );
		$data['image'] = self::validate_url_field( $payload, 'image', __( 'Image must be a valid http or https URL.', 'tb247-deal-manager' ), true, $errors );

		$data['affiliate_url'] = self::validate_marketplace_url_field( $payload, 'affiliate_url', $marketplace, true, $errors );
		$data['source_url']    = self::validate_marketplace_url_field( $payload, 'source_url', $marketplace, false, $errors );

		$data['in_stock'] = self::validate_in_stock( $payload );

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
			'data'   => $data,
		);
	}

	/**
	 * shop_code/item_code: bắt buộc, chuỗi text thường (loại HTML/script qua
	 * sanitize_text_field), không giới hạn định dạng cụ thể vì mã Rakuten do
	 * từng shop tự đặt (không có chuẩn cố định như ASIN).
	 *
	 * @param array                $payload       Payload thô.
	 * @param string               $field         Tên field trong payload.
	 * @param string               $error_message Thông báo lỗi nếu thiếu.
	 * @param array<string,string> $errors        Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_required_code( array $payload, $field, $error_message, array &$errors ) {
		$raw = isset( $payload[ $field ] ) ? sanitize_text_field( trim( (string) $payload[ $field ] ) ) : '';

		if ( '' === $raw ) {
			$errors[ $field ] = $error_message;
		}

		return $raw;
	}

	/**
	 * Validate 1 field URL phải thuộc marketplace đã phê duyệt — dùng cho
	 * affiliate_url/source_url của sàn ngoài Amazon. Đi qua đúng allowlist
	 * exact-hostname của TB247_DM_Url_Guard, không chỉ check http/https
	 * chung chung như validate_url_field().
	 *
	 * @param array                $payload     Payload thô.
	 * @param string               $field       Tên field trong payload.
	 * @param string               $marketplace Slug sàn.
	 * @param bool                 $required    Field có bắt buộc không.
	 * @param array<string,string> $errors      Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_marketplace_url_field( array $payload, $field, $marketplace, $required, array &$errors ) {
		$raw = isset( $payload[ $field ] ) ? trim( (string) $payload[ $field ] ) : '';

		if ( '' === $raw ) {
			if ( $required ) {
				$errors[ $field ] = sprintf(
					/* translators: %s: field name */
					__( '%s is required and must be a valid, approved marketplace URL.', 'tb247-deal-manager' ),
					$field
				);
			}
			return '';
		}

		if ( ! function_exists( 'tb247_validate_marketplace_url' ) ) {
			$errors[ $field ] = __( 'URL validation is unavailable.', 'tb247-deal-manager' );
			return '';
		}

		$validated = tb247_validate_marketplace_url( $raw, $marketplace );

		if ( null === $validated ) {
			$errors[ $field ] = sprintf(
				/* translators: %s: field name */
				__( '%s must be a valid https URL from an approved marketplace host.', 'tb247-deal-manager' ),
				$field
			);
			return '';
		}

		return $validated;
	}

	/**
	 * ASIN: bắt buộc, chỉ chữ+số, đúng 10 ký tự (cùng quy tắc với Amazon ASIN
	 * thật), chuẩn hoá thành chữ in hoa.
	 *
	 * @param array                $payload Payload thô.
	 * @param array<string,string> $errors  Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_asin( array $payload, array &$errors ) {
		$raw = isset( $payload['asin'] ) ? trim( (string) $payload['asin'] ) : '';

		if ( '' === $raw ) {
			$errors['asin'] = __( 'ASIN is required.', 'tb247-deal-manager' );
			return '';
		}

		if ( ! preg_match( '/^[A-Za-z0-9]{10}$/', $raw ) ) {
			$errors['asin'] = __( 'ASIN must be exactly 10 alphanumeric characters (no spaces or special characters).', 'tb247-deal-manager' );
			return '';
		}

		return strtoupper( $raw );
	}

	/**
	 * JAN: không bắt buộc. Nếu có, chỉ giữ chữ số và kiểm tra độ dài hợp lý
	 * (8 hoặc 13 số theo chuẩn JAN/EAN Nhật). Rỗng KHÔNG làm request thất bại.
	 *
	 * @param array                $payload Payload thô.
	 * @param array<string,string> $errors  Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_jan( array $payload, array &$errors ) {
		if ( empty( $payload['jan'] ) ) {
			return '';
		}

		$digits = preg_replace( '/\D/', '', (string) $payload['jan'] );

		if ( '' === $digits || ! in_array( strlen( $digits ), array( 8, 13 ), true ) ) {
			$errors['jan'] = __( 'JAN must contain only digits and be 8 or 13 characters long.', 'tb247-deal-manager' );
			return '';
		}

		return $digits;
	}

	/**
	 * Title: bắt buộc, sanitize_text_field tự loại bỏ HTML/script.
	 *
	 * @param array                $payload Payload thô.
	 * @param array<string,string> $errors  Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_title( array $payload, array &$errors ) {
		$title = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '';

		if ( '' === $title ) {
			$errors['title'] = __( 'Title is required.', 'tb247-deal-manager' );
		}

		return $title;
	}

	/**
	 * Price: bắt buộc, phải là số, không âm, lưu thành số nguyên yên.
	 * is_numeric() tự loại các chuỗi có dấu phẩy/ký hiệu 円 (không phải số hợp lệ).
	 *
	 * @param array                $payload Payload thô.
	 * @param array<string,string> $errors  Mảng lỗi, truyền theo tham chiếu.
	 * @return int
	 */
	private static function validate_price( array $payload, array &$errors ) {
		if ( ! isset( $payload['price'] ) || ! is_numeric( $payload['price'] ) || (float) $payload['price'] < 0 ) {
			$errors['price'] = __( 'Price must be a non-negative number.', 'tb247-deal-manager' );
			return 0;
		}

		return (int) round( (float) $payload['price'] );
	}

	/**
	 * Price cho Rakuten: bắt buộc, phải là số, phải LỚN HƠN 0 (chặt hơn
	 * validate_price() dùng cho Amazon, vốn chỉ yêu cầu không âm — giữ
	 * validate_price() nguyên vẹn để không đổi hành vi Amazon hiện tại).
	 *
	 * @param array                $payload Payload thô.
	 * @param array<string,string> $errors  Mảng lỗi, truyền theo tham chiếu.
	 * @return int
	 */
	private static function validate_positive_price( array $payload, array &$errors ) {
		if ( ! isset( $payload['price'] ) || ! is_numeric( $payload['price'] ) || (float) $payload['price'] <= 0 ) {
			$errors['price'] = __( 'Price must be a positive number.', 'tb247-deal-manager' );
			return 0;
		}

		return (int) round( (float) $payload['price'] );
	}

	/**
	 * Validate 1 field dạng URL bắt buộc http/https, dùng cho image và affiliate_url.
	 * Dùng wp_http_validate_url() của WordPress core thay vì tự viết regex.
	 *
	 * @param array                $payload      Payload thô.
	 * @param string               $field        Tên field trong payload.
	 * @param string               $error_message Thông báo lỗi nếu không hợp lệ.
	 * @param bool                 $required     Field có bắt buộc không.
	 * @param array<string,string> $errors       Mảng lỗi, truyền theo tham chiếu.
	 * @return string
	 */
	private static function validate_url_field( array $payload, $field, $error_message, $required, array &$errors ) {
		$raw = isset( $payload[ $field ] ) ? trim( (string) $payload[ $field ] ) : '';

		if ( '' === $raw ) {
			if ( $required ) {
				$errors[ $field ] = $error_message;
			}
			return '';
		}

		$validated = wp_http_validate_url( $raw );

		if ( ! $validated ) {
			$errors[ $field ] = $error_message;
			return '';
		}

		return esc_url_raw( $validated );
	}

	/**
	 * in_stock: không bắt buộc, backward-compatible. Chỉ true/false hợp lệ mới
	 * được nhận; thiếu field, null, hoặc kiểu dữ liệu khác đều coi là "chưa xác
	 * định" (null) — KHÔNG lỗi validate, KHÔNG ghi đè trạng thái tồn kho đã lưu
	 * trước đó (Deal_Service::write_meta() sẽ bỏ qua meta khi giá trị là null).
	 *
	 * @param array $payload Payload thô.
	 * @return bool|null
	 */
	private static function validate_in_stock( array $payload ) {
		if ( ! array_key_exists( 'in_stock', $payload ) ) {
			return null;
		}

		$value = $payload['in_stock'];

		if ( true === $value || false === $value ) {
			return $value;
		}

		return null;
	}
}
