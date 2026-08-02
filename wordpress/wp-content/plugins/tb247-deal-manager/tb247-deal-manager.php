<?php
/**
 * Plugin Name:       TB247 Deal Manager
 * Plugin URI:        https://tb247.example
 * Description:       Quản lý deal Amazon (và sau này Rakuten/Yahoo) + Landing Page /d/{code} cho TB247 Corporation. Nhận dữ liệu từ Discord Bot qua REST API.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            TB247
 * Text Domain:       tb247-deal-manager
 * Domain Path:       /languages
 *
 * @package TB247_Deal_Manager
 */

defined( 'ABSPATH' ) || exit;

define( 'TB247_DM_VERSION', '1.0.0' );
define( 'TB247_DM_PLUGIN_FILE', __FILE__ );
define( 'TB247_DM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TB247_DM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TB247_DM_PLUGIN_DIR . 'includes/marketplaces/interface-marketplace.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/marketplaces/class-marketplace-registry.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/marketplaces/class-amazon-marketplace.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/marketplaces/class-url-guard.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/cpt/class-deal-post-type.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/services/class-deal-service.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/routing/class-deal-rewrite.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/rest/class-auth.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/rest/class-rest-controller.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/rest/class-products-validator.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/rest/class-products-rest-controller.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/rest/class-deals-lookup-rest-controller.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/seo/class-deal-seo.php';
require_once TB247_DM_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'TB247_DM_Deal_Rewrite', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TB247_DM_Deal_Rewrite', 'deactivate' ) );

TB247_DM_Plugin::init();
