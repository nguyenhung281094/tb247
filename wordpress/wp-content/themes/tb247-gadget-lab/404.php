<?php
/**
 * Trang 404 — dùng khi /d/{code} không tìm thấy deal, hoặc URL bất kỳ không tồn tại.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<header class="tb247-page-header">
	<div class="tb247-container">
		<p class="tb247-eyebrow"><?php bloginfo( 'name' ); ?></p>
		<h1><?php esc_html_e( 'ページが見つかりません', 'tb247-gadget-lab' ); ?></h1>
	</div>
</header>

<section class="tb247-page">
	<div class="tb247-container">
		<div class="tb247-page-card">
			<div class="tb247-page-content">
				<p><?php esc_html_e( 'お探しのページは移動または削除された可能性があります。', 'tb247-gadget-lab' ); ?></p>
				<p>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップページに戻る', 'tb247-gadget-lab' ); ?></a>
				</p>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
