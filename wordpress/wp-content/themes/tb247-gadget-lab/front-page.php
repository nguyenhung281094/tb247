<?php
/**
 * Trang ホーム — Hero + card dẫn hướng, KHÔNG phải danh sách blog.
 * Card lấy tiêu đề/mô tả trực tiếp từ Menu chính (wp-admin), không hardcode
 * tên trang hay slug — đổi Menu ở wp-admin thì card tự đổi theo.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

get_header();

$link_cards = tb247_get_home_link_cards();
?>

<section class="tb247-hero">
	<div class="tb247-hero-decor" aria-hidden="true">
		<span class="tb247-hero-tag tb247-hero-tag--1"></span>
		<span class="tb247-hero-tag tb247-hero-tag--2"></span>
		<span class="tb247-hero-tag tb247-hero-tag--3"></span>
	</div>

	<div class="tb247-container tb247-hero-inner">
		<p class="tb247-eyebrow"><?php esc_html_e( '各ECサイトのお得情報をお届け', 'tb247-gadget-lab' ); ?></p>

		<h1 class="tb247-hero-title">
			<?php esc_html_e( 'セール・ポイント還元・キャンペーンなど、', 'tb247-gadget-lab' ); ?>
			<br />
			<?php esc_html_e( '各ECサイトのお得な情報をわかりやすく紹介。', 'tb247-gadget-lab' ); ?>
		</h1>

		<p class="tb247-hero-sub">
			<?php esc_html_e( '気になる商品のセール状況やお得なキャンペーン情報を、複数のオンラインショップから厳選してお届けします。', 'tb247-gadget-lab' ); ?>
		</p>
	</div>
</section>

<?php if ( ! empty( $link_cards ) ) : ?>
	<section class="tb247-links">
		<div class="tb247-container tb247-links-grid">
			<?php foreach ( array_slice( $link_cards, 0, 3 ) as $item ) : ?>
				<a class="tb247-link-card" href="<?php echo esc_url( $item->url ); ?>">
					<span class="tb247-tag-icon" aria-hidden="true"></span>
					<span class="tb247-link-card-title"><?php echo esc_html( $item->title ); ?></span>
					<?php if ( ! empty( $item->description ) ) : ?>
						<span class="tb247-link-card-desc"><?php echo esc_html( $item->description ); ?></span>
					<?php endif; ?>
					<span class="tb247-link-card-cta"><?php esc_html_e( '見る', 'tb247-gadget-lab' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
