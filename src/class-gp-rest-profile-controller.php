<?php
/**
 * REST API: GP_REST_Profile_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

/**
 * Core class used to manage a profile via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Profile_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'profile';
		parent::__construct();
	}
}
