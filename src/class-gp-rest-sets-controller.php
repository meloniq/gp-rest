<?php
/**
 * REST API: GP_REST_Sets_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

/**
 * Core class used to manage a sets via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Sets_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'sets';
		parent::__construct();
	}
}
