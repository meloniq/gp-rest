<?php
/**
 * Plugin Name:       GP REST
 * Plugin URI:        https://blog.meloniq.net/gp-rest/
 *
 * Description:       Extends GlotPress by adding REST API endpoints, enabling developers to integrate, extend, and build custom applications on top of the GlotPress translation system.
 * Tags:              glotpress, rest, api, endpoint, interface
 *
 * Requires at least: 4.9
 * Requires PHP:      7.4
 * Version:           1.0
 *
 * Author:            MELONIQ.NET
 * Author URI:        https://meloniq.net/
 *
 * License:           GPLv2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain:       gp-rest
 *
 * Requires Plugins:  glotpress
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

// If this file is accessed directly, then abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'GPREST_TD', 'gp-rest' );
define( 'GPREST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GPREST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * GP Init Setup.
 *
 * @return void
 */
function gp_init() {
	global $gprest_endpoints;

	require_once __DIR__ . '/src/trait-gp-profile-helper.php';

	require_once __DIR__ . '/src/class-gp-rest-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-languages-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-settings-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-profile-controller.php';

	$gprest_endpoints              = array();
	$gprest_endpoints['languages'] = new GP_REST_Languages_Controller();
	$gprest_endpoints['settings']  = new GP_REST_Settings_Controller();
	$gprest_endpoints['profile']   = new GP_REST_Profile_Controller();
}
add_action( 'gp_init', 'Meloniq\GpRest\gp_init' );
