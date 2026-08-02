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
	$marketplace   = get_post_meta( $deal_id, '_tb247_marketplace', true );
	$affiliate_url = get_post_meta( $deal_id, '_tb247_affiliate_url', true );

	// Xác thực domain đích trước khi render — chỉ cho phép marketplace đã
	// phê duyệt (chặn open redirect/phishing nếu dữ liệu bot từng bị lỗi/giả
	// mạo). Nếu helper của plugin không sẵn sàng vì lý do gì đó, ẩn hẳn CTA
	// thay vì fallback sang URL chưa xác thực.
	if ( $affiliate_url ) {
		if ( function_exists( 'tb247_validate_marketplace_url' ) ) {
			$affiliate_url = tb247_validate_marketplace_url( $affiliate_url, $marketplace );

			if ( $affiliate_url && function_exists( 'tb247_strip_tracking_params' ) ) {
				$affiliate_url = tb247_strip_tracking_params( $affiliate_url );
			}
		} else {
			$affiliate_url = '';
		}
	}

	// '_tb247_in_stock' lưu '1' (còn hàng) / '0' (hết hàng) / '' (chưa có dữ
	// liệu — deal cũ trước khi có tính năng này, hoặc bot chưa xác định được).
	// Chỉ '0' mới ép ẩn giá; mọi trường hợp khác giữ nguyên hành vi cũ (theo giá).
	$in_stock_meta = get_post_meta( $deal_id, '_tb247_in_stock', true );
	$show_price    = ( '0' !== $in_stock_meta ) && ( $price > 0 );

	// Tên sản phẩm và nút mua dùng chung 1 URL affiliate, cùng thuộc tính
	// target/rel chuẩn hoá qua helper dùng chung (Amazon hiện tại, Rakuten/
	// Yahoo sau này không cần sửa lại ở đây).
	$marketplace_link_attrs = tb247_get_marketplace_link_attributes();
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
										target="<?php echo esc_attr( $marketplace_link_attrs['target'] ); ?>"
										rel="<?php echo esc_attr( $marketplace_link_attrs['rel'] ); ?>"
									><?php esc_html_e( 'Amazon', 'tb247-gadget-lab' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="tb247-deal-body">
					<h1 class="tb247-deal-title">
						<?php if ( $affiliate_url ) : ?>
							<a href="<?php echo esc_url( $affiliate_url ); ?>" target="<?php echo esc_attr( $marketplace_link_attrs['target'] ); ?>" rel="<?php echo esc_attr( $marketplace_link_attrs['rel'] ); ?>">
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
