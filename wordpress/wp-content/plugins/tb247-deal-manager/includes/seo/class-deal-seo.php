<?php
/**
 * Tự sinh OG tags / Twitter Card cho Landing Page /d/{code}.
 *
 * Đặt trong Plugin (không phải Theme) để hoạt động độc lập với theme đang
 * active — đổi theme sau này vẫn không mất SEO meta. Không cần plugin SEO
 * ngoài (Yoast/RankMath...).
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

class TB247_DM_Deal_SEO {

	/**
	 * Đăng ký hook wp_head, chạy sớm để tag nằm gần đầu <head>.
	 */
	public static function register_hooks() {
		add_action( 'wp_head', array( __CLASS__, 'output_meta_tags' ), 1 );
	}

	/**
	 * In OG/Twitter meta tags nếu trang hiện tại là một deal hợp lệ.
	 */
	public static function output_meta_tags() {
		$code = get_query_var( TB247_DM_Deal_Rewrite::QUERY_VAR );

		if ( empty( $code ) ) {
			return;
		}

		$deal = TB247_DM_Deal_Service::find_by_code( $code );

		if ( ! $deal ) {
			return;
		}

		$title = get_the_title( $deal );
		$image = get_post_meta( $deal->ID, '_tb247_image', true );
		$price = (int) get_post_meta( $deal->ID, '_tb247_sale_price', true );
		$jan   = get_post_meta( $deal->ID, '_tb247_jan', true );
		$asin  = get_post_meta( $deal->ID, '_tb247_asin', true );
		$url   = home_url( '/d/' . rawurlencode( $asin ) );

		$description = sprintf(
			/* translators: 1: tên sản phẩm, 2: giá bán, 3: mã JAN */
			__( '%1$s ｜ %2$s円 ｜ JAN: %3$s', 'tb247-deal-manager' ),
			$title,
			number_format_i18n( $price ),
			$jan ? $jan : '-'
		);

		echo "\n" . '<meta property="og:type" content="website" />' . "\n";
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );

		if ( $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		}

		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );

		if ( $image ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
		}
	}
}
