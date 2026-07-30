<?php
/**
 * Template fallback cuối cùng theo template hierarchy của WordPress.
 * Site này không phải blog nên trong vận hành bình thường file này hiếm khi
 * được dùng tới (front-page.php / page.php / single-deal.php đã bao phủ hết).
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
