<?php
/**
 * Khung đầu trang: menu chính (ホーム, 随時セール情報, おすすめ商品, お問い合わせ) — nội dung menu
 * tạo trong wp-admin, không hardcode ở đây.
 *
 * Trên Landing Page (/d/{code}): ẩn hoàn toàn menu và tên site, chỉ còn Logo
 * (phóng to) để nhận diện thương hiệu — không phải liên kết, không chuyển
 * trang, không hover — để người dùng chỉ tập trung vào sản phẩm và nút
 * "Amazonで購入".
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="tb247-site-header<?php echo tb247_is_deal_page() ? ' tb247-site-header--deal' : ''; ?>">
	<div class="tb247-container">
		<?php if ( tb247_is_deal_page() ) : ?>
			<div class="tb247-logo tb247-logo--static">
				<?php tb247_the_logo_mark( 'tb247-logo-mark' ); ?>
			</div>
		<?php else : ?>
			<a class="tb247-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php tb247_the_logo_mark( 'tb247-logo-mark' ); ?>
				<span class="tb247-logo-text"><?php bloginfo( 'name' ); ?></span>
			</a>

			<nav class="tb247-nav" aria-label="<?php esc_attr_e( 'Menu chính', 'tb247-gadget-lab' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
</header>

<main class="tb247-main">
