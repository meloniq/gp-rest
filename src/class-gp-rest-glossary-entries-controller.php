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
 * Core class used to manage a glossaries via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Glossary_Entries_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'glossaries';
		parent::__construct();
	}

	/**
	 * Registers the routes for the glossaries endpoint.
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
					'callback'            => array( $this, 'get_entries' ),
					'permission_callback' => array( $this, 'get_entries_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'create_entry' ),
					'permission_callback' => array( $this, 'create_entry_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'edit_entry' ),
					'permission_callback' => array( $this, 'edit_entry_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'delete_entry' ),
					'permission_callback' => array( $this, 'delete_entry_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
	public function get_entries( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_not_found',
					'message' => __( 'Glossary not found.', 'gp-rest' ),
				),
				404
			);
		}

		$entries = $glossary->get_entries();
		$data    = array();
		foreach ( $entries as $entry ) {
			$data[] = array(
				'glossary_id'    => $entry->glossary_id,
				'id'             => $entry->id,
				'term'           => $entry->term,
				'translation'    => $entry->translation,
				'part_of_speech' => $entry->part_of_speech,
				'comment'        => $entry->comment,
				'last_edited_by' => $entry->last_edited_by,
			);
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles POST requests to /glossaries/{id}/entries endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_not_found',
					'message' => __( 'Glossary not found.', 'gp-rest' ),
				),
				404
			);
		}

		$entry_term           = sanitize_text_field( $request->get_param( 'term' ) );
		$entry_translation    = sanitize_text_field( $request->get_param( 'translation' ) );
		$entry_part_of_speech = sanitize_text_field( $request->get_param( 'part_of_speech' ) );
		$entry_comment        = sanitize_text_field( $request->get_param( 'comment' ) );

		if ( empty( $entry_term ) || empty( $entry_translation ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_entry_data',
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
			return new WP_REST_Response(
				array(
					'code'    => 'entry_exists',
					'message' => __( 'Glossary entry already exists.', 'gp-rest' ),
				),
				409
			);
		}

		$params['last_edited_by'] = get_current_user_id();

		$new_glossary_entry     = new GP_Glossary_Entry( $params );
		$created_glossary_entry = GP::$glossary_entry->create_and_select( $new_glossary_entry );

		if ( ! $created_glossary_entry ) {
			return new WP_REST_Response(
				array(
					'code'    => 'entry_creation_failed',
					'message' => __( 'Failed to create glossary entry.', 'gp-rest' ),
				),
				500
			);
		}

		$data = array(
			'glossary_id'    => $created_glossary_entry->glossary_id,
			'id'             => $created_glossary_entry->id,
			'term'           => $created_glossary_entry->term,
			'translation'    => $created_glossary_entry->translation,
			'part_of_speech' => $created_glossary_entry->part_of_speech,
			'comment'        => $created_glossary_entry->comment,
			'last_edited_by' => $created_glossary_entry->last_edited_by,
		);

		$response = new WP_REST_Response( $data, 201 );

		return $response;
	}

	/**
	 * Handles PUT requests to /glossaries/{id}/entries/{entry_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function edit_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_not_found',
					'message' => __( 'Glossary not found.', 'gp-rest' ),
				),
				404
			);
		}

		$entry_id = (int) $request->get_param( 'entry_id' );
		$entry    = GP::$glossary_entry->get( $entry_id );
		if ( ! $entry || $entry->glossary_id !== $glossary_id ) {
			return new WP_REST_Response(
				array(
					'code'    => 'entry_not_found',
					'message' => __( 'Glossary entry not found.', 'gp-rest' ),
				),
				404
			);
		}

		$entry_term           = sanitize_text_field( $request->get_param( 'term' ) );
		$entry_translation    = sanitize_text_field( $request->get_param( 'translation' ) );
		$entry_part_of_speech = sanitize_text_field( $request->get_param( 'part_of_speech' ) );
		$entry_comment        = sanitize_text_field( $request->get_param( 'comment' ) );

		if ( empty( $entry_term ) || empty( $entry_translation ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_entry_data',
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
			return new WP_REST_Response(
				array(
					'code'    => 'entry_update_failed',
					'message' => __( 'Failed to update glossary entry.', 'gp-rest' ),
				),
				500
			);
		}

		$data = array(
			'glossary_id'    => $entry->glossary_id,
			'id'             => $entry->id,
			'term'           => $entry->term,
			'translation'    => $entry->translation,
			'part_of_speech' => $entry->part_of_speech,
			'comment'        => $entry->comment,
			'last_edited_by' => $entry->last_edited_by,
		);

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles DELETE requests to /glossaries/{id}/entries/{entry_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_entry( $request ) {
		$glossary_id = (int) $request->get_param( 'id' );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_not_found',
					'message' => __( 'Glossary not found.', 'gp-rest' ),
				),
				404
			);
		}

		$entry_id = (int) $request->get_param( 'entry_id' );
		$entry    = GP::$glossary_entry->get( $entry_id );
		if ( ! $entry || $entry->glossary_id !== $glossary_id ) {
			return new WP_REST_Response(
				array(
					'code'    => 'entry_not_found',
					'message' => __( 'Glossary entry not found.', 'gp-rest' ),
				),
				404
			);
		}

		// Set glossary entry ID and delete.
		GP::$glossary_entry->id = $entry_id;
		$deleted                = GP::$glossary_entry->delete();
		if ( ! $deleted ) {
			return new WP_REST_Response(
				array(
					'code'    => 'entry_deletion_failed',
					'message' => __( 'Failed to delete glossary entry.', 'gp-rest' ),
				),
				500
			);
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
	public function get_entries_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_entry_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		// $can_edit = $this->can( 'approve', 'translation-set', $translation_set->id );.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for editing a glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_entry_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for deleting a glossary entry.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_entry_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}
}
