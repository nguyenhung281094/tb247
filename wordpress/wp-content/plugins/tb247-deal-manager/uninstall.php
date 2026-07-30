<?php
/**
 * Chạy khi plugin bị gỡ (Delete) từ wp-admin.
 *
 * Không xoá dữ liệu deal đã lưu (an toàn dữ liệu) — chỉ dọn rewrite rules.
 *
 * @package TB247_Deal_Manager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

flush_rewrite_rules();
