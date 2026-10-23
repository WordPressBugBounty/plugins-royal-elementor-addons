<?php

use \Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPR_DC_Section_Archive extends WPR_DC_Section_Base {

	public function get_id() {
		return 'wpr_dc_archive';
	}

	public function get_label() {
		return esc_html__( 'Archive', 'wpr-addons' );
	}

	public function get_order() {
		return 50;
	}

	public function register_controls( $element ) {
		$options = [];

		foreach ( self::get_page_types() as $type => $page_type ) {
			$options[ $type ] = $page_type['label'];
		}

		$element->add_control(
			'wpr_dc_archive_types',
			[
				'label'       => esc_html__( 'Page Types', 'wpr-addons' ),
				'description' => esc_html__( 'Select page types to match.', 'wpr-addons' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'default'     => [],
				'options'     => $options,
				'render_type' => 'none',
			]
		);

		$element->add_control(
			'wpr_dc_archive_term_property',
			[
				'label'       => esc_html__( 'Term Property', 'wpr-addons' ),
				'description' => esc_html__( 'Additional check on the current archive term.', 'wpr-addons' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'default'     => '',
				'options'     => [
					''             => esc_html__( 'Not Set', 'wpr-addons' ),
					'has_children' => esc_html__( 'Has Child Terms', 'wpr-addons' ),
					'is_root'      => esc_html__( 'Is Root Term (no parent)', 'wpr-addons' ),
					'is_leaf'      => esc_html__( 'Is Leaf Term (no children)', 'wpr-addons' ),
					'has_posts'    => esc_html__( 'Has Posts', 'wpr-addons' ),
				],
				'render_type' => 'none',
			]
		);
	}

	public function evaluate( $settings ) {
		$types    = ! empty( $settings['wpr_dc_archive_types'] ) ? $settings['wpr_dc_archive_types'] : [];
		$property = ! empty( $settings['wpr_dc_archive_term_property'] ) ? $settings['wpr_dc_archive_term_property'] : '';

		if ( empty( $types ) && '' === $property ) {
			return null;
		}

		$results = [];

		// Check page types. The stored value is only an allowlist key.
		// It is never passed to call_user_func().
		if ( ! empty( $types ) ) {
			$type_match = false;

			if ( is_array( $types ) ) {
				$page_types = self::get_page_types();

				foreach ( $types as $type ) {
					if ( ! is_string( $type ) || ! isset( $page_types[ $type ]['callback'] ) ) {
						continue;
					}

					if ( $page_types[ $type ]['callback']() ) {
						$type_match = true;
						break;
					}
				}
			}

			$results[] = $type_match;
		}

		// Check term property (only relevant on taxonomy archives)
		if ( '' !== $property ) {
			$results[] = $this->check_term_property( $property );
		}

		if ( empty( $results ) ) {
			return null;
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Page type keys, panel labels, and the conditional tag each key runs.
	 *
	 * Keys match the Page Types control. Callbacks are defined here so a
	 * saved setting can only select one of these checks.
	 *
	 * @return array<string, array{label: string, callback: callable}>
	 */
	private static function get_page_types() {
		static $types = null;

		if ( null !== $types ) {
			return $types;
		}

		$types = [
			'is_front_page' => [
				'label'    => esc_html__( 'Front Page', 'wpr-addons' ),
				'callback' => static function () {
					return is_front_page();
				},
			],
			'is_home'       => [
				'label'    => esc_html__( 'Blog / Posts Page', 'wpr-addons' ),
				'callback' => static function () {
					return is_home();
				},
			],
			'is_singular'   => [
				'label'    => esc_html__( 'Any Single Post/Page', 'wpr-addons' ),
				'callback' => static function () {
					return is_singular();
				},
			],
			'is_single'     => [
				'label'    => esc_html__( 'Single Post', 'wpr-addons' ),
				'callback' => static function () {
					return is_single();
				},
			],
			'is_page'       => [
				'label'    => esc_html__( 'Single Page', 'wpr-addons' ),
				'callback' => static function () {
					return is_page();
				},
			],
			'is_archive'    => [
				'label'    => esc_html__( 'Any Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_archive();
				},
			],
			'is_category'   => [
				'label'    => esc_html__( 'Category Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_category();
				},
			],
			'is_tag'        => [
				'label'    => esc_html__( 'Tag Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_tag();
				},
			],
			'is_tax'        => [
				'label'    => esc_html__( 'Taxonomy Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_tax();
				},
			],
			'is_author'     => [
				'label'    => esc_html__( 'Author Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_author();
				},
			],
			'is_date'       => [
				'label'    => esc_html__( 'Date Archive', 'wpr-addons' ),
				'callback' => static function () {
					return is_date();
				},
			],
			'is_search'     => [
				'label'    => esc_html__( 'Search Results', 'wpr-addons' ),
				'callback' => static function () {
					return is_search();
				},
			],
			'is_404'        => [
				'label'    => esc_html__( '404 Page', 'wpr-addons' ),
				'callback' => static function () {
					return is_404();
				},
			],
		];

		return $types;
	}

	/**
	 * Drop Page Types values that are not in the control option list before they are stored.
	 *
	 * @param array $data Elementor document data.
	 * @return array
	 */
	public static function sanitize_saved_document( $data ) {
		if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = self::sanitize_elements_page_types( $data['elements'] );

		return $data;
	}

	/**
	 * @param array $elements Elementor elements tree.
	 * @return array
	 */
	private static function sanitize_elements_page_types( array $elements ) {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['settings']['wpr_dc_archive_types'] ) && is_array( $element['settings']['wpr_dc_archive_types'] ) ) {
				$element['settings']['wpr_dc_archive_types'] = self::filter_page_types( $element['settings']['wpr_dc_archive_types'] );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::sanitize_elements_page_types( $element['elements'] );
			}

			$elements[ $index ] = $element;
		}

		return $elements;
	}

	/**
	 * Keep only Page Types keys that have a matching conditional check.
	 *
	 * @param array $types Saved control values.
	 * @return array
	 */
	private static function filter_page_types( array $types ) {
		$allowed = self::get_page_types();
		$clean   = [];

		foreach ( $types as $type ) {
			if ( is_string( $type ) && isset( $allowed[ $type ] ) ) {
				$clean[] = $type;
			}
		}

		return $clean;
	}

	private function check_term_property( $property ) {
		$term = get_queried_object();

		if ( ! $term || ! isset( $term->term_id ) ) {
			return false;
		}

		switch ( $property ) {
			case 'has_children':
				$children = get_term_children( $term->term_id, $term->taxonomy );
				return ! empty( $children ) && ! is_wp_error( $children );

			case 'is_root':
				return 0 === (int) $term->parent;

			case 'is_leaf':
				$children = get_term_children( $term->term_id, $term->taxonomy );
				return empty( $children ) || is_wp_error( $children );

			case 'has_posts':
				return $term->count > 0;

			default:
				return true;
		}
	}
}
