<?php
/**
 * Khung cuối trang.
 *
 * Landing Page (/d/{code}) dùng Footer tối giản riêng — chỉ 1 dòng thông báo
 * affiliate + copyright, không tiêu đề, không menu — để không phân tán sự
 * chú ý khỏi nút "Amazonで購入" (Conversion Landing Page). Các trang khác
 * giữ nguyên Footer đầy đủ như cũ.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

$is_deal_footer = tb247_is_deal_page();
?>
</main>

<footer class="tb247-site-footer<?php echo $is_deal_footer ? ' tb247-site-footer--deal' : ''; ?>">
	<div class="tb247-container">
		<?php if ( $is_deal_footer ) : ?>

			<p class="tb247-footer-disclosure">
				<?php esc_html_e( '当サイトでは各種アフィリエイトプログラムを利用した広告を掲載しています。', 'tb247-gadget-lab' ); ?>
			</p>

			<p class="tb247-footer-copyright">
				<?php esc_html_e( 'TB247', 'tb247-gadget-lab' ); ?>
			</p>

		<?php else : ?>

			<div class="tb247-footer-top">
				<a class="tb247-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>

				<nav class="tb247-footer-nav" aria-label="<?php esc_attr_e( 'フッターメニュー', 'tb247-gadget-lab' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
					?>
				</nav>
			</div>

			<p class="tb247-footer-disclosure">
				<?php esc_html_e( '当サイトでは各種アフィリエイトプログラムを利用した広告を掲載しています。', 'tb247-gadget-lab' ); ?>
			</p>

			<p class="tb247-footer-copyright">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
			</p>

		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
