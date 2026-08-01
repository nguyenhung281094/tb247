<?php
/**
 * Trang 随時セール情報 — nội dung tĩnh (page.php mặc định) + lưới sản phẩm đang
 * bật cờ is_sale (đặt qua slash command /sale trên Discord).
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
					<?php the_content(); ?>
				</div>
			</div>
		</div>
	</article>
	<?php
endwhile;

$deals = tb247_query_deals_by_flag( '_tb247_is_sale' );
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
				<?php esc_html_e( '現在セール情報はまだ登録されていません。', 'tb247-gadget-lab' ); ?>
			</p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
