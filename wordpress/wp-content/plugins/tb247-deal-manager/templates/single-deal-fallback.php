<?php
/**
 * Template dự phòng cho Landing Page /d/{code}, chỉ dùng khi theme đang
 * active KHÔNG có sẵn single-deal.php. Theme tb247-gadget-lab có template
 * riêng đẹp hơn — file này chỉ để trang không bao giờ "vỡ" nếu đổi theme.
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$deal_id       = get_the_ID();
	$jan           = get_post_meta( $deal_id, '_tb247_jan', true );
	$price         = (int) get_post_meta( $deal_id, '_tb247_sale_price', true );
	$image         = get_post_meta( $deal_id, '_tb247_image', true );
	$affiliate_url = get_post_meta( $deal_id, '_tb247_affiliate_url', true );
	$source_url    = get_post_meta( $deal_id, '_tb247_product_url', true );

	// §6: deal (Rakuten) không có affiliate_url -> CTA fallback về source_url
	// thay vì ẩn hẳn — cùng quy tắc với single-deal.php của theme, bản fallback
	// tối giản này không chạy tb247_validate_marketplace_url() (không phụ thuộc
	// theme) nên chỉ check rỗng, không tự bịa domain nếu 2 field cùng rỗng.
	$has_affiliate = (bool) $affiliate_url;
	$cta_url       = $has_affiliate ? $affiliate_url : $source_url;
	$cta_rel       = $has_affiliate ? 'nofollow sponsored noopener' : 'nofollow noopener';
	$cta_label     = $has_affiliate ? __( 'Amazonで購入', 'tb247-deal-manager' ) : __( '商品ページを見る', 'tb247-deal-manager' );

	// Cùng quy ước với single-deal.php của theme: '0' = hết hàng (ép ẩn giá),
	// mọi giá trị khác ('1' hoặc '' — chưa có dữ liệu) giữ hành vi cũ (theo giá).
	$in_stock_meta = get_post_meta( $deal_id, '_tb247_in_stock', true );
	$show_price    = ( '0' !== $in_stock_meta ) && ( $price > 0 );
	?>
	<article class="tb247-deal-fallback">
		<?php if ( $image ) : ?>
			<img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>" />
		<?php endif; ?>

		<h1><?php the_title(); ?></h1>

		<?php if ( $show_price ) : ?>
			<p><?php echo esc_html( number_format_i18n( $price ) ); ?>円</p>
		<?php endif; ?>

		<?php if ( $jan ) : ?>
			<p>JAN: <?php echo esc_html( $jan ); ?></p>
		<?php endif; ?>

		<?php if ( $cta_url ) : ?>
			<p>
				<a href="<?php echo esc_url( $cta_url ); ?>" rel="<?php echo esc_attr( $cta_rel ); ?>" target="_blank">
					<?php echo esc_html( $cta_label ); ?>
				</a>
			</p>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
