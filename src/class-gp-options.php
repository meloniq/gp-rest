<?php
/**
 * REST API: GP_Options class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

/**
 * Class for various options used in the REST API.
 */
class GP_Options {

	/**
	 * Get "Sort by" options.
	 *
	 * @return array
	 */
	public static function get_sort_by_options() {
		$options = array(
			'original_date_added'       => __( 'Date added (original)', 'gp-rest' ),
			'translation_date_added'    => __( 'Date added (translation)', 'gp-rest' ),
			'translation_date_modified' => __( 'Date modified (translation)', 'gp-rest' ),
			'original'                  => __( 'Original string', 'gp-rest' ),
			'translation'               => __( 'Translation', 'gp-rest' ),
			'priority'                  => __( 'Priority', 'gp-rest' ),
			'references'                => __( 'Filename in source', 'gp-rest' ),
			'length'                    => __( 'Original length', 'gp-rest' ),
			'random'                    => __( 'Random', 'gp-rest' ),
		);

		return $options;
	}

	/**
	 * Get "Sort order" options.
	 *
	 * @return array
	 */
	public static function get_sort_order_options() {
		$options = array(
			'asc'  => __( 'Ascending', 'gp-rest' ),
			'desc' => __( 'Descending', 'gp-rest' ),
		);

		return $options;
	}

	/**
	 * Get "Term scope" options.
	 *
	 * @return array
	 */
	public static function get_term_scope_options() {
		$options = array(
			'scope_originals'    => __( 'Originals only', 'gp-rest' ),
			'scope_translations' => __( 'Translations only', 'gp-rest' ),
			'scope_context'      => __( 'Context only', 'gp-rest' ),
			'scope_references'   => __( 'References only', 'gp-rest' ),
			'scope_both'         => __( 'Both Originals and Translations', 'gp-rest' ),
			'scope_any'          => __( 'Any', 'gp-rest' ),
		);

		return $options;
	}

	/**
	 * Get "Filter status" options.
	 *
	 * @return array
	 */
	public static function get_filter_status_options() {
		$options = array(
			'current'      => __( 'Current', 'gp-rest' ),
			'waiting'      => __( 'Waiting', 'gp-rest' ),
			'fuzzy'        => __( 'Fuzzy', 'gp-rest' ),
			'untranslated' => __( 'Untranslated', 'gp-rest' ),
			'rejected'     => __( 'Rejected', 'gp-rest' ),
			'old'          => __( 'Old', 'gp-rest' ),
		);

		return $options;
	}

	/**
	 * Get "Filter options" options.
	 *
	 * @return array
	 */
	public static function get_filter_options_options() {
		$options = array(
			'with_comment' => __( 'With comment', 'gp-rest' ),
			'with_context' => __( 'With context', 'gp-rest' ),
			'warnings'     => __( 'With warnings', 'gp-rest' ),
			'with_plural'  => __( 'With plural', 'gp-rest' ),
		);

		return $options;
	}
}
