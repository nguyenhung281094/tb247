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
	 * Kiểm tra + làm sạch payload thô từ request.
	 *
	 * @param array $payload Dữ liệu thô từ JSON body.
	 * @return array{valid: bool, errors: array<string,string>, data: array}
	 */
	public static function validate( array $payload ) {
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
