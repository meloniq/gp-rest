<?php
/**
 * REST API: GP_REST_Glossary_Entries_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Glossary_Entry;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a glossary entries via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Glossary_Entries_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'glossaries';
		parent::__construct();
	}

	/**
	 * Registers the routes for the glossary entries endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET glossaries/{id}/entries .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/entries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_glossary_entries' ),
					'permission_callback' => array( $this, 'get_glossary_entries_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// POST glossaries/{id}/entries .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/entries',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_glossary_entry' ),
					'permission_callback' => array( $this, 'create_glossary_entry_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		// GET glossaries/{id}/entries/{entry_id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/entries/(?P<entry_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_glossary_entry' ),
					'permission_callback' => array( $this, 'get_glossary_entry_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// PUT glossaries/{id}/entries/{entry_id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/entries/(?P<entry_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'edit_glossary_entry' ),
					'permission_callback' => array( $this, 'edit_glossary_entry_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		// DELETE glossaries/{id}/entries/{entry_id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/entries/(?P<entry_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_glossary_entry' ),
					'permission_callback' => array( $this, 'delete_glossary_entry_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /glossaries/{id}/entries endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_glossary_entries( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$entries = GP::$glossary_entry->by_glossary_id( $glossary_id );

		$data = array();
		foreach ( $entries as $entry ) {
			$item   = $this->prepare_item_for_response( $entry, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles POST requests to /glossaries/{id}/entries endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_glossary_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$entry_term           = sanitize_text_field( $request->get_param( 'term' ) );
		$entry_translation    = sanitize_text_field( $request->get_param( 'translation' ) );
		$entry_part_of_speech = sanitize_text_field( $request->get_param( 'part_of_speech' ) );
		$entry_comment        = sanitize_text_field( $request->get_param( 'comment' ) );

		if ( empty( $entry_term ) || empty( $entry_translation ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_glossary_entry_data',
					'message' => __( 'Term and translation are required fields.', 'gp-rest' ),
				),
				400
			);
		}

		$params = array(
			'glossary_id'    => $glossary_id,
			'term'           => $entry_term,
			'translation'    => $entry_translation,
			'part_of_speech' => $entry_part_of_speech,
			'comment'        => $entry_comment,
		);

		if ( GP::$glossary_entry->find_one( $params ) ) {
			return $this->response_409_glossary_entry_already_exists();
		}

		$params['last_edited_by'] = get_current_user_id();

		$new_glossary_entry     = new GP_Glossary_Entry( $params );
		$created_glossary_entry = GP::$glossary_entry->create_and_select( $new_glossary_entry );

		if ( ! $created_glossary_entry ) {
			return $this->response_500_glossary_entry_creation_failed();
		}

		$data = $this->prepare_item_for_response( $created_glossary_entry, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /glossaries/{id}/entries/{entry_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_glossary_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$entry_id = (int) $request->get_param( 'entry_id' );
		$entry    = GP::$glossary_entry->get( $entry_id );
		if ( ! $entry || $entry->glossary_id !== $glossary_id ) {
			return $this->response_404_glossary_entry_not_found();
		}

		$data = $this->prepare_item_for_response( $entry, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles PUT requests to /glossaries/{id}/entries/{entry_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function edit_glossary_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$entry_id = (int) $request->get_param( 'entry_id' );
		$entry    = GP::$glossary_entry->get( $entry_id );
		if ( ! $entry || $entry->glossary_id !== $glossary_id ) {
			return $this->response_404_glossary_entry_not_found();
		}

		$entry_term           = sanitize_text_field( $request->get_param( 'term' ) );
		$entry_translation    = sanitize_text_field( $request->get_param( 'translation' ) );
		$entry_part_of_speech = sanitize_text_field( $request->get_param( 'part_of_speech' ) );
		$entry_comment        = sanitize_text_field( $request->get_param( 'comment' ) );

		if ( empty( $entry_term ) || empty( $entry_translation ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_glossary_entry_data',
					'message' => __( 'Term and translation are required fields.', 'gp-rest' ),
				),
				400
			);
		}

		$entry->term           = $entry_term;
		$entry->translation    = $entry_translation;
		$entry->part_of_speech = $entry_part_of_speech;
		$entry->comment        = $entry_comment;
		$entry->last_edited_by = get_current_user_id();

		$updated = GP::$glossary_entry->update( $entry );
		if ( ! $updated ) {
			return $this->response_500_glossary_entry_update_failed();
		}

		$data = $this->prepare_item_for_response( $entry, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /glossaries/{id}/entries/{entry_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_glossary_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$entry_id = (int) $request->get_param( 'entry_id' );
		$entry    = GP::$glossary_entry->get( $entry_id );
		if ( ! $entry || $entry->glossary_id !== $glossary_id ) {
			return $this->response_404_glossary_entry_not_found();
		}

		// Set glossary entry ID and delete.
		GP::$glossary_entry->id = $entry_id;
		$deleted                = GP::$glossary_entry->delete();
		if ( ! $deleted ) {
			return $this->response_500_glossary_entry_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for getting glossary entries.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_glossary_entries_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_glossary_entry_permissions_check( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return false;
		}

		return $this->current_user_can( 'approve', 'translation-set', $glossary->translation_set_id );
	}

	/**
	 * Permission check for retrieving a glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_glossary_entry_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_glossary_entry_permissions_check( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return false;
		}

		return $this->current_user_can( 'approve', 'translation-set', $glossary->translation_set_id );
	}

	/**
	 * Permission check for deleting a glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_glossary_entry_permissions_check( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return false;
		}

		return $this->current_user_can( 'approve', 'translation-set', $glossary->translation_set_id );
	}

	/**
	 * Prepares a single glossary entry output for response.
	 *
	 * @param GP_Glossary_Entry $item    Glossary entry object.
	 * @param WP_REST_Request   $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$entry = $item;

		$data = array(
			'id'             => $entry->id,
			'glossary_id'    => $entry->glossary_id,
			'term'           => $entry->term,
			'translation'    => $entry->translation,
			'part_of_speech' => $entry->part_of_speech,
			'comment'        => $entry->comment,
			'last_edited_by' => $entry->last_edited_by,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a glossary entry returned from the REST API.
		 * Allows modification of the glossary entry right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param GP_Glossary_Entry $entry    The original object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_glossary_entry', $response, $entry, $request );
	}

	/**
	 * Retrieves the glossary entry schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'glossary_entry',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'description' => __( 'Unique identifier for the glossary entry.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'glossary_id'    => array(
					'description' => __( 'Identifier of the glossary this entry belongs to.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'term'           => array(
					'description' => __( 'The term of the glossary entry.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'translation'    => array(
					'description' => __( 'The translation of the term.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'part_of_speech' => array(
					'description' => __( 'The part of speech of the term.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'comment'        => array(
					'description' => __( 'Additional comments about the glossary entry.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'last_edited_by' => array(
					'description' => __( 'User ID of the last editor of the glossary entry.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
