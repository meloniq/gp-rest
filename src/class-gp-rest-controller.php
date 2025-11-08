<?php
/**
 * REST API: GP_REST_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

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
	protected $namespace = 'gp/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}
}
