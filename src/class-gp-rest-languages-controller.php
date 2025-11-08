<?php
/**
 * REST API: GP_REST_Languages_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

/**
 * Core class used to manage a languages via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Languages_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'languages';
		parent::__construct();
	}
}
