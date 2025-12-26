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

	/**
	 * Get the items per page limit.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return int
	 */
	protected function get_items_per_page_limit( $request ) {
		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( ! $per_page ) {
			$per_page_site = get_option( 'gp_per_page' );
			$per_page_user = is_user_logged_in() ? get_user_option( get_current_user_id(), 'gp_per_page' ) : 0;

			$per_page = $per_page_user ? $per_page_user : $per_page_site;
		}

		if ( ! $per_page ) {
			$per_page = 20;
		}

		return (int) $per_page;
	}

	/**
	 * Get the items sort by parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	protected function get_items_sort_by_param( $request ) {
		$allowed_sorts = array_keys( GP_Options::get_sort_by_options() );

		$sort_by = $request->get_param( 'sort_by' );
		if ( ! $sort_by || ! in_array( $sort_by, $allowed_sorts, true ) ) {
			$sort = 'priority';
		}

		return $sort_by;
	}

	/**
	 * Get the items sort order parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	protected function get_items_sort_order_param( $request ) {
		$allowed_orders = array_keys( GP_Options::get_sort_order_options() );

		$sort_order = $request->get_param( 'sort_order' );
		if ( ! $sort_order || ! in_array( $sort_order, $allowed_orders, true ) ) {
			$sort_order = 'desc';
		}

		return $sort_order;
	}

	/**
	 * Get the items sort parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	protected function get_items_sort_param( $request ) {
		$sort_by    = $this->get_items_sort_by_param( $request );
		$sort_order = $this->get_items_sort_order_param( $request );

		return array(
			'by'  => $sort_by,
			'how' => $sort_order,
		);
	}

	/**
	 * Get the items filters parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	protected function get_items_filters_param( $request ) {
		$filters = array();

		// Todo.

		return $filters;
	}
}
