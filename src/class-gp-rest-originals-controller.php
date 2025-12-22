<?php
/**
 * REST API: GP_REST_Originals_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a originals via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Originals_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'originals';
		parent::__construct();
	}

	/**
	 * Registers the routes for the originals endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET originals .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_originals' ),
					'permission_callback' => array( $this, 'get_originals_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// POST originals/import .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_originals' ),
					'permission_callback' => array( $this, 'import_originals_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET originals/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_original' ),
					'permission_callback' => array( $this, 'get_original_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// DELETE originals/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_original' ),
					'permission_callback' => array( $this, 'delete_original_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /originals endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function get_originals( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$originals = GP::$original->by_project_id( $project_id );

		$data = array();
		foreach ( $originals as $original ) {
			$data[] = array(
				'id'         => $original->id,
				'project_id' => $original->project_id,
				'context'    => $original->context,
				'singular'   => $original->singular,
				'plural'     => $original->plural,
				'comment'    => $original->comment,
				'references' => $original->references,
				'status'     => $original->status,
				'priority'   => $original->priority,
				'date_added' => $original->date_added,
			);
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles POST requests to /originals/import endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function import_originals( $request ) {
		global $wp_filesystem;

		// Load the WordPress file system.
		include_once ABSPATH . 'wp-admin/includes/file.php';

		$project_id = absint( $request->get_param( 'project_id' ) );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$project = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		// file format.
		$format = $request->get_param( 'format' );
		if ( ! in_array( $format, array( 'auto', 'android', 'po', 'mo', 'resx', 'strings', 'properties', 'json', 'jed1x', 'ngx' ), true ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_format',
					'message' => __( 'The format parameter.', 'gp-rest' ),
				),
				400
			);
		}

		$import_file = $request->get_param( 'import-file' );
		// save content into a temp file.
		WP_Filesystem();
		$filename = wp_tempnam();
		$success  = $wp_filesystem->put_contents( $filename, $import_file );
		if ( ! $success ) {
			return new WP_REST_Response(
				array(
					'code'    => 'file_write_error',
					'message' => __( 'Failed to write the uploaded file.', 'gp-rest' ),
				),
				500
			);
		}

		// TODO: Fix this part.
		$format_obj = gp_get_import_file_format( $format, $filename );
		if ( ! $format_obj ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_file_format',
					'message' => __( 'The imported file format is invalid or not supported.', 'gp-rest' ),
				),
				400
			);
		}

		$translations = $format_obj->read_originals_from_file( $filename );

		$imported_count = GP::$original->import_from_project( $project, $translations );

		list( $originals_added, $originals_existing, $originals_fuzzied, $originals_obsoleted, $originals_error ) = $imported_count;

		$data = array(
			'originals_added'     => $originals_added,
			'originals_existing'  => $originals_existing,
			'originals_fuzzied'   => $originals_fuzzied,
			'originals_obsoleted' => $originals_obsoleted,
			'originals_error'     => $originals_error,
		);

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles GET requests to /originals/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_original( $request ) {
		$original_id = absint( $request->get_param( 'id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_original_not_found();
		}

		$data = array(
			'id'         => $original->id,
			'project_id' => $original->project_id,
			'context'    => $original->context,
			'singular'   => $original->singular,
			'plural'     => $original->plural,
			'comment'    => $original->comment,
			'references' => $original->references,
			'status'     => $original->status,
			'priority'   => $original->priority,
			'date_added' => $original->date_added,
		);

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles DELETE requests to /originals/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_original( $request ) {
		$original_id = absint( $request->get_param( 'id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_translation_original_not_found();
		}

		// Set original ID and delete.
		GP::$original->id = $original_id;
		$deleted          = GP::$original->delete();
		if ( ! $deleted ) {
			return $this->response_500_original_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for retrieving originals.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_originals_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for importing a new originals.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function import_originals_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for retrieving a original.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_original_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for deleting a original.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_original_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}
}
