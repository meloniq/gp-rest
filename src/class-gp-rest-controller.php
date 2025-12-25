<?php
/**
 * REST API: GP_REST_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use WP_REST_Controller;

/**
 * Core base controller for managing and interacting with REST API items.
 */
abstract class GP_REST_Controller extends WP_REST_Controller {

	/**
	 * The namespace for the REST API routes.
	 *
	 * @var string
	 */
	protected $namespace = 'gp/v0.1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}

	/**
	 * Current user can.
	 *
	 * @param string      $action      The action.
	 * @param string|null $object_type Optional. Type of an object. Default null.
	 * @param int|null    $object_id   Optional. ID of an object. Default null.
	 * @param array|null  $extra       Optional. Extra information for deciding the outcome.
	 *
	 * @return bool
	 */
	protected function current_user_can( $action, $object_type = null, $object_id = null, $extra = null ) {
		return GP::$permission->current_user_can( $action, $object_type, $object_id, $extra );
	}
}
