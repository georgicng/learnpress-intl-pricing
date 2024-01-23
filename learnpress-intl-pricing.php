<?php
/**
 * Plugin Name
 *
 * @package     LearnPress Intl Pricing
 * @author      Ikpugbu George
 *
 * @wordpress-plugin
 * Plugin Name: Intl Pricing Plugin for LearnPress
 * Plugin URI:  https://wordpress.org/plugins/lp-intl-pricing/
 * Description: Set custom price for foreign customers
 * Version:     4.2.3.5
 * Author:      George Ikpugbu
 * Require_LP_Version: 4.2.3.5
 * Text Domain: lp-intl-pricing
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined( 'ABSPATH' ) or die();

define( 'LP_ADDON_INTL_PRICING_FILE', __FILE__ );
define( 'LP_ADDON_INTL_PRICING_VER', '4.2.3.5' );
define( 'LP_ADDON_INTL_PRICING_REQUIRE_VER', '4.2.3.5' );

/**
 * Class LP_Addon_Intl_Pricing_Preload
 */
class LP_Addon_Intl_Pricing_Preload {

	/**
	 * LP_Addon_Intl_Pricing_Preload constructor.
	 */
	public function __construct() {
		add_action( 'learn-press/ready', array( $this, 'load' ) );
	}

	/**
	 * Load addon
	 */
	public function load() {
		LP_Addon::load( 'LP_Addon_Intl_Pricing', 'inc/load.php', __FILE__ );
	}
}

new LP_Addon_Intl_Pricing_Preload();
