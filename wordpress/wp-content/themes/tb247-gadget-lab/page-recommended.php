<?php
/**
 * Trang おすすめ商品 — nội dung tĩnh (page.php mặc định) + lưới sản phẩm đang
 * bật cờ is_recommended (đặt qua slash command /recommend trên Discord).
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<header class="tb247-page-header">
		<div class="tb247-container">
			<p class="tb247-eyebrow"><?php bloginfo( 'name' ); ?></p>
			<h1><?php the_title(); ?></h1>
		</div>
	</header>

	<article class="tb247-page">
		<div class="tb247-container">
			<div class="tb247-page-card">
				<div class="tb247-page-content">
					<p><?php esc_html_e( '複数のECサイトから、おすすめ商品やお得な情報を厳選してご紹介しています。', 'tb247-gadget-lab' ); ?></p>
					<p><?php esc_html_e( '価格・在庫は変動する場合があります。最新情報は販売ページをご確認ください。', 'tb247-gadget-lab' ); ?></p>
				</div>
			</div>
		</div>
	</article>
	<?php
endwhile;

$deals = tb247_query_deals_by_flag( '_tb247_is_recommended' );
?>

<section class="tb247-deal-grid-section">
	<div class="tb247-container">
		<?php if ( ! empty( $deals ) ) : ?>
			<div class="tb247-deal-grid">
				<?php foreach ( $deals as $deal ) : ?>
					<?php tb247_the_deal_card( $deal ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="tb247-deal-grid-empty">
				<?php esc_html_e( '現在おすすめ商品はまだ登録されていません。', 'tb247-gadget-lab' ); ?>
			</p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
