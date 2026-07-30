<?php
/**
 * Landing Page /d/{code} — override template dự phòng của plugin.
 *
 * Chỉ hiển thị: ảnh, tên sản phẩm (kèm link), giá, JAN (+ nút copy), nút mua,
 * 1 dòng lưu ý ngắn. KHÔNG hiển thị: review, ngày đăng/cập nhật, giá Kaitori,
 * comment, related posts, author, share, tags.
 *
 * @package TB247_Gadget_Lab
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

	// '_tb247_in_stock' lưu '1' (còn hàng) / '0' (hết hàng) / '' (chưa có dữ
	// liệu — deal cũ trước khi có tính năng này, hoặc bot chưa xác định được).
	// Chỉ '0' mới ép ẩn giá; mọi trường hợp khác giữ nguyên hành vi cũ (theo giá).
	$in_stock_meta = get_post_meta( $deal_id, '_tb247_in_stock', true );
	$show_price    = ( '0' !== $in_stock_meta ) && ( $price > 0 );

	// Tên sản phẩm và nút mua dùng chung 1 URL affiliate, cùng thuộc tính rel
	// (nofollow + sponsored bắt buộc theo quy định Amazon Associates).
	$affiliate_rel = 'noopener noreferrer nofollow sponsored';
	?>
	<article class="tb247-deal">
		<div class="tb247-deal-container">
			<div class="tb247-deal-card">

				<?php if ( $image ) : ?>
					<div class="tb247-deal-media">
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>" />

						<?php if ( $show_price || $affiliate_url ) : ?>
							<div class="tb247-deal-media-overlay" aria-hidden="true"></div>

							<div class="tb247-deal-media-footer<?php echo $show_price ? '' : ' tb247-deal-media-footer--no-price'; ?>">
								<?php if ( $show_price ) : ?>
									<div class="tb247-deal-price-block">
										<p class="tb247-deal-price">&yen;<?php echo esc_html( number_format_i18n( $price ) ); ?></p>
									</div>
								<?php endif; ?>

								<?php if ( $affiliate_url ) : ?>
									<a
										class="tb247-buy-button-mini"
										href="<?php echo esc_url( $affiliate_url ); ?>"
										target="_blank"
										rel="<?php echo esc_attr( $affiliate_rel ); ?>"
									><?php esc_html_e( 'Amazon', 'tb247-gadget-lab' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="tb247-deal-body">
					<h1 class="tb247-deal-title">
						<?php if ( $affiliate_url ) : ?>
							<a href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="<?php echo esc_attr( $affiliate_rel ); ?>">
								<?php the_title(); ?>
							</a>
						<?php else : ?>
							<?php the_title(); ?>
						<?php endif; ?>
					</h1>

					<?php if ( $jan ) : ?>
						<div class="tb247-deal-jan">
							<span class="tb247-deal-jan-label">JAN: <strong><?php echo esc_html( $jan ); ?></strong></span>
							<button
								type="button"
								class="tb247-copy-jan-btn"
								data-jan="<?php echo esc_attr( $jan ); ?>"
								data-label-default="<?php echo esc_attr__( 'JANをコピー', 'tb247-gadget-lab' ); ?>"
								data-label-copied="<?php echo esc_attr__( 'コピーしました', 'tb247-gadget-lab' ); ?>"
							><?php esc_html_e( 'JANをコピー', 'tb247-gadget-lab' ); ?></button>
						</div>
					<?php endif; ?>

					<p class="tb247-deal-note">
						<?php esc_html_e( '価格・在庫は変動する場合があります。最新情報は販売ページをご確認ください。', 'tb247-gadget-lab' ); ?>
					</p>
				</div>

			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
