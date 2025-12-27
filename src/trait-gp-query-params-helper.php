<?php
/**
 * Trait: GP_Query_Params_Helper class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use WP_REST_Request;

/**
 * Trait used to provide query params helper methods.
 */
trait GP_Query_Params_Helper {

	/**
	 * Get the items per page limit.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return int
	 */
	public function get_items_per_page_limit( $request ) {
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
	 * Get the items sort parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	public function get_items_sort_param( $request ) {
		$sort = array();

		$sort_by = $this->get_items_sort_by_param( $request );
		if ( $sort_by ) {
			$sort['by'] = $sort_by;
		}

		$sort_order = $this->get_items_sort_order_param( $request );
		if ( $sort_order ) {
			$sort['how'] = $sort_order;
		}

		return $sort;
	}

	/**
	 * Get the items filters parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	public function get_items_filters_param( $request ) {
		$filters = array();

		$term = $this->get_items_filters_term_param( $request );
		if ( $term ) {
			$filters['term'] = $term;
		}

		$case_sensitive = $this->get_items_filters_case_sensitive_param( $request );
		if ( $case_sensitive ) {
			$filters['case_sensitive'] = $case_sensitive;
		}

		$term_scope = $this->get_items_filters_term_scope_param( $request );
		if ( $term_scope ) {
			$filters['term_scope'] = $term_scope;
		}

		$status = $this->get_items_filters_status_param( $request );
		if ( $status ) {
			$filters['status'] = $status;
		}

		$user_login = $this->get_items_filters_user_login_param( $request );
		if ( $user_login ) {
			$filters['user_login'] = $user_login;
		}

		// Each option is set on the top level.
		$options = $this->get_items_filters_options_param( $request );
		if ( ! empty( $options ) ) {
			$filters = array_merge( $filters, $options );
		}

		return $filters;
	}

	/**
	 * Get the items "sort by" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_sort_by_param( $request ) {
		$allowed_sorts = array_keys( GP_Options::get_sort_by_options() );

		$sort_by = $request->get_param( 'sort_by' );
		if ( ! $sort_by || ! in_array( $sort_by, $allowed_sorts, true ) ) {
			$sort = 'priority';
		}

		return $sort_by;
	}

	/**
	 * Get the items "sort order" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_sort_order_param( $request ) {
		$allowed_orders = array_keys( GP_Options::get_sort_order_options() );

		$sort_order = $request->get_param( 'sort_order' );
		if ( ! $sort_order || ! in_array( $sort_order, $allowed_orders, true ) ) {
			$sort_order = 'desc';
		}

		return $sort_order;
	}

	/**
	 * Get the items "filters term" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_filters_term_param( $request ) {
		$term = $request->get_param( 'filters_term' );
		if ( ! $term || ! is_string( $term ) ) {
			return '';
		}

		$term = sanitize_text_field( $term );

		return $term;
	}

	/**
	 * Get the items "filters case sensitive" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_filters_case_sensitive_param( $request ) {
		$case_sensitive = $request->get_param( 'filters_case_sensitive' );
		if ( $case_sensitive ) {
			return 'yes';
		}

		return '';
	}

	/**
	 * Get the items "filters term scope" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_filters_term_scope_param( $request ) {
		$available_scopes = array_keys( GP_Options::get_term_scope_options() );

		$term_scope = $request->get_param( 'filters_term_scope' );
		if ( $term_scope && in_array( $term_scope, $available_scopes, true ) ) {
			return $term_scope;
		}

		return '';
	}

	/**
	 * Get the items "filters status" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_filters_status_param( $request ) {
		$available_statuses = array_keys( GP_Options::get_filter_status_options() );

		$statuses = $request->get_param( 'filters_status' );
		if ( ! $statuses || ! is_array( $statuses ) ) {
			return '';
		}

		$valid_statuses = array();
		foreach ( $statuses as $status ) {
			if ( in_array( $status, $available_statuses, true ) ) {
				$valid_statuses[] = $status;
			}
		}

		return implode( '_or_', $valid_statuses );
	}

	/**
	 * Get the items "filters options" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	public function get_items_filters_options_param( $request ) {
		$available_options = array_keys( GP_Options::get_filter_options_options() );

		$options = $request->get_param( 'filters_options' );
		if ( ! $options || ! is_array( $options ) ) {
			return array();
		}

		$valid_options = array();
		foreach ( $options as $option ) {
			if ( in_array( $option, $available_options, true ) ) {
				$valid_options[ $option ] = 'yes';
			}
		}

		return $valid_options;
	}

	/**
	 * Get the items "filters user login" parameter.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return string
	 */
	public function get_items_filters_user_login_param( $request ) {
		$user_login = $request->get_param( 'filters_user_login' );
		if ( ! $user_login || ! is_string( $user_login ) ) {
			return '';
		}

		$user_login = sanitize_text_field( $user_login );

		return $user_login;
	}
}
