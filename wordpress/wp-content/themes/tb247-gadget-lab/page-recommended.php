<?php
/**
 * Trang おすすめ商品 — nội dung tĩnh (page.php mặc định) + lưới sản phẩm đang
 * bật cờ is_recommended (đặt qua slash command /recommend trên Discord),
 * lọc theo marketplace (?marketplace=amazon|rakuten|yahoo) + phân trang
 * 12 sản phẩm/trang qua query string (?paged=N) — không đụng /sale-info/
 * (dùng tb247_query_deals_by_flag() riêng, không filter/pagination).
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

// $_GET['marketplace'] thô — PHẢI qua sanitize_key()+allowlist trước khi dùng
// bất cứ đâu (meta_query, URL, hiển thị). Không nhận meta key/taxonomy từ GET.
// is_scalar() chặn dạng ?marketplace[]=x (mảng) trước khi ép (string) — tránh
// PHP Warning "Array to string conversion".
$raw_marketplace     = ( isset( $_GET['marketplace'] ) && is_scalar( $_GET['marketplace'] ) ) ? wp_unslash( $_GET['marketplace'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_marketplace  = tb247_sanitize_recommended_marketplace( $raw_marketplace );
// get_query_var('page') PHẢI được ưu tiên: WordPress tự 301-redirect
// ?paged=N sang /recommended/page/N/ trên static Page (redirect_canonical()),
// và tại URL đó $_GET['paged'] không tồn tại — chỉ đọc $_GET sẽ âm thầm
// hiển thị lại trang 1. Xem tb247_resolve_recommended_paged().
$get_paged           = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged               = tb247_resolve_recommended_paged( $get_paged, get_query_var( 'page' ) );
$recommended_query   = tb247_query_recommended_deals( $active_marketplace, $paged );
$deals               = $recommended_query->posts;
$total_pages         = (int) $recommended_query->max_num_pages;
$base_url            = get_permalink();
$pagination_window   = tb247_build_pagination_window( $paged, $total_pages );
?>

<section class="tb247-recommended-section">
	<div class="tb247-recommended-container">
		<div class="tb247-recommended-layout">
			<nav class="tb247-marketplace-sidebar" aria-label="<?php esc_attr_e( 'プラットフォームで絞り込み', 'tb247-gadget-lab' ); ?>">
				<p class="tb247-marketplace-sidebar__title"><?php esc_html_e( 'プラットフォーム', 'tb247-gadget-lab' ); ?></p>
				<ul class="tb247-marketplace-filter">
					<?php foreach ( tb247_get_recommended_filter_options() as $option ) : ?>
						<?php
						$is_active = ( $option['slug'] === $active_marketplace );
						$item_url  = tb247_build_recommended_url( $base_url, $option['slug'], 1 );
						$item_class = 'tb247-marketplace-filter__item' . ( $is_active ? ' tb247-marketplace-filter__item--active' : '' );
						?>
						<li class="tb247-marketplace-filter__list-item">
							<a
								class="<?php echo esc_attr( $item_class ); ?>"
								href="<?php echo esc_url( $item_url ); ?>"
								<?php echo $is_active ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( $option['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="tb247-recommended-main">
				<?php if ( ! empty( $deals ) ) : ?>
					<div class="tb247-recommended-grid">
						<?php foreach ( $deals as $deal ) : ?>
							<?php tb247_the_deal_card( $deal ); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="tb247-deal-grid-empty">
						<?php if ( '' === $active_marketplace ) : ?>
							<?php esc_html_e( '現在おすすめ商品はまだ登録されていません。', 'tb247-gadget-lab' ); ?>
						<?php else : ?>
							<?php esc_html_e( '現在、このショップのおすすめ商品はありません。', 'tb247-gadget-lab' ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $pagination_window ) ) : ?>
					<nav class="tb247-pagination" aria-label="<?php esc_attr_e( 'おすすめ商品のページネーション', 'tb247-gadget-lab' ); ?>">
						<?php if ( $paged > 1 ) : ?>
							<a class="tb247-pagination__edge" href="<?php echo esc_url( tb247_build_recommended_url( $base_url, $active_marketplace, $paged - 1 ) ); ?>">
								<?php esc_html_e( '‹ 前へ', 'tb247-gadget-lab' ); ?>
							</a>
						<?php else : ?>
							<span class="tb247-pagination__edge tb247-pagination__edge--disabled" aria-disabled="true">
								<?php esc_html_e( '‹ 前へ', 'tb247-gadget-lab' ); ?>
							</span>
						<?php endif; ?>

						<ul class="tb247-pagination__list">
							<?php foreach ( $pagination_window as $page_item ) : ?>
								<?php if ( '...' === $page_item ) : ?>
									<li class="tb247-pagination__ellipsis" aria-hidden="true">&hellip;</li>
								<?php elseif ( $page_item === $paged ) : ?>
									<li>
										<span class="tb247-pagination__item tb247-pagination__item--active" aria-current="page">
											<?php echo esc_html( (string) $page_item ); ?>
										</span>
									</li>
								<?php else : ?>
									<li>
										<a class="tb247-pagination__item" href="<?php echo esc_url( tb247_build_recommended_url( $base_url, $active_marketplace, $page_item ) ); ?>">
											<?php echo esc_html( (string) $page_item ); ?>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>

						<?php if ( $paged < $total_pages ) : ?>
							<a class="tb247-pagination__edge" href="<?php echo esc_url( tb247_build_recommended_url( $base_url, $active_marketplace, $paged + 1 ) ); ?>">
								<?php esc_html_e( '次へ ›', 'tb247-gadget-lab' ); ?>
							</a>
						<?php else : ?>
							<span class="tb247-pagination__edge tb247-pagination__edge--disabled" aria-disabled="true">
								<?php esc_html_e( '次へ ›', 'tb247-gadget-lab' ); ?>
							</span>
						<?php endif; ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
