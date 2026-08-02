<?php
/**
 * Bootstrap mỏng của theme — chỉ require các file trong inc/, không viết logic trực tiếp ở đây.
 *
 * @package TB247_Gadget_Lab
 */

defined( 'ABSPATH' ) || exit;

define( 'TB247_THEME_DIR', get_template_directory() );
define( 'TB247_THEME_URI', get_template_directory_uri() );

require_once TB247_THEME_DIR . '/inc/setup.php';
require_once TB247_THEME_DIR . '/inc/enqueue.php';
require_once TB247_THEME_DIR . '/inc/template-tags.php';
require_once TB247_THEME_DIR . '/inc/cache.php';
