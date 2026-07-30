<?php
/**
 * Template mặc định cho các Page tĩnh (お問い合わせ, 随時セール情報, おすすめ商品...).
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

get_footer();
