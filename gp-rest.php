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
	require_once __DIR__ . '/src/trait-gp-responses-helper.php';

	require_once __DIR__ . '/src/class-gp-rest-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-formats-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-glossaries-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-glossary-entries-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-languages-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-originals-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-projects-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-project-permissions-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-profile-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-translations-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-translation-sets-controller.php';
	require_once __DIR__ . '/src/class-gp-rest-settings-controller.php';

	$gprest_endpoints                        = array();
	$gprest_endpoints['formats']             = new GP_REST_Formats_Controller();
	$gprest_endpoints['glossaries']          = new GP_REST_Glossaries_Controller();
	$gprest_endpoints['glossary-entries']    = new GP_REST_Glossary_Entries_Controller();
	$gprest_endpoints['languages']           = new GP_REST_Languages_Controller();
	$gprest_endpoints['originals']           = new GP_REST_Originals_Controller();
	$gprest_endpoints['projects']            = new GP_REST_Projects_Controller();
	$gprest_endpoints['project-permissions'] = new GP_REST_Project_Permissions_Controller();
	$gprest_endpoints['profile']             = new GP_REST_Profile_Controller();
	$gprest_endpoints['translations']        = new GP_REST_Translations_Controller();
	$gprest_endpoints['translation-sets']    = new GP_REST_Translation_Sets_Controller();
	$gprest_endpoints['settings']            = new GP_REST_Settings_Controller();
}
add_action( 'gp_init', 'Meloniq\GpRest\gp_init' );
