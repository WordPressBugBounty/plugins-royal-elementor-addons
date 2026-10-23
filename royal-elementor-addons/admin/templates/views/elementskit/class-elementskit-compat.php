<?php

use WprAddons\Admin\Includes\WPR_Conditions_Manager;
use WprAddons\Classes\Utilities;

/**
 * ElementsKit header/footer compatibility.
 *
 * ElementsKit prints its header and footer from theme hooks (get_header/get_footer,
 * or theme-specific actions such as astra_header). REA content templates load
 * wpr-canvas.php, which never runs those hooks. This prints the active ElementsKit
 * templates on that canvas when REA is not already printing its own header/footer.
 */
class Wpr_ElementsKit_Compat {

	/**
	 * Instance of Wpr_ElementsKit_Compat.
	 *
	 * @var Wpr_ElementsKit_Compat
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @return Wpr_ElementsKit_Compat
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Wpr_ElementsKit_Compat();

			add_action( 'wp', [ self::$instance, 'hooks' ] );
		}

		return self::$instance;
	}

	/**
	 * Run all the Actions / Filters.
	 */
	public function hooks() {
		if ( ! class_exists( '\ElementsKit_Lite\Modules\Header_Footer\Activator' ) ) {
			return;
		}

		// Only when REA Theme Builder replaces the page with its canvas shell.
		if ( is_null( WPR_Conditions_Manager::canvas_page_content_display_conditions() ) ) {
			return;
		}

		// ElementsKit itself skips real Elementor Canvas pages.
		if ( 'elementor_canvas' === get_page_template_slug() ) {
			return;
		}

		add_action( 'elementor/page_templates/canvas/before_content', [ $this, 'render_header' ] );
		add_action( 'elementor/page_templates/canvas/after_content', [ $this, 'render_footer' ] );
	}

	/**
	 * Print the active ElementsKit header on REA canvas.
	 */
	public function render_header() {
		if ( $this->is_rea_canvas_header_active() ) {
			return;
		}

		$this->render_template( 'header' );
	}

	/**
	 * Print the active ElementsKit footer on REA canvas.
	 */
	public function render_footer() {
		if ( $this->is_rea_canvas_footer_active() ) {
			return;
		}

		$this->render_template( 'footer' );
	}

	/**
	 * Print one ElementsKit header or footer template.
	 *
	 * @param string $type header|footer.
	 */
	private function render_template( $type ) {
		$ids = \ElementsKit_Lite\Modules\Header_Footer\Activator::template_ids();
		$template_id = ( 'header' === $type ) ? ( $ids[0] ?? null ) : ( $ids[1] ?? null );

		if ( empty( $template_id ) ) {
			return;
		}

		do_action( 'elementskit/template/before_' . $type );

		echo '<div class="ekit-template-content-markup ekit-template-content-' . esc_attr( $type ) . '">';
		echo \ElementsKit_Lite\Utils::render_elementor_content( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ElementsKit Elementor template markup.
		echo '</div>';

		do_action( 'elementskit/template/after_' . $type );
	}

	/**
	 * Whether REA will print its own header on canvas (show on canvas enabled).
	 *
	 * @return bool
	 */
	private function is_rea_canvas_header_active() {
		$conditions = json_decode( get_option( 'wpr_header_conditions', '[]' ), true );
		$template_slug = WPR_Conditions_Manager::header_footer_display_conditions( $conditions );

		if ( is_null( $template_slug ) ) {
			return false;
		}

		$template_id = Utilities::get_template_id( $template_slug );
		$show_on_canvas = get_post_meta( $template_id, 'wpr_header_show_on_canvas', true );

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$default_language_code = apply_filters( 'wpml_default_language', null );
			$current_language_code = apply_filters( 'wpml_current_language', null );

			if ( $current_language_code && $current_language_code !== $default_language_code ) {
				$show_on_canvas = 'true';
			}
		}

		return ( ! empty( $show_on_canvas ) && 'true' === $show_on_canvas );
	}

	/**
	 * Whether REA will print its own footer on canvas (show on canvas enabled).
	 *
	 * @return bool
	 */
	private function is_rea_canvas_footer_active() {
		$conditions = json_decode( get_option( 'wpr_footer_conditions', '[]' ), true );
		$template_slug = WPR_Conditions_Manager::header_footer_display_conditions( $conditions );

		if ( is_null( $template_slug ) ) {
			return false;
		}

		$template_id = Utilities::get_template_id( $template_slug );
		$show_on_canvas = get_post_meta( $template_id, 'wpr_footer_show_on_canvas', true );

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$default_language_code = apply_filters( 'wpml_default_language', null );
			$current_language_code = apply_filters( 'wpml_current_language', null );

			if ( $current_language_code && $current_language_code !== $default_language_code ) {
				$show_on_canvas = 'true';
			}
		}

		return ( ! empty( $show_on_canvas ) && 'true' === $show_on_canvas );
	}
}

Wpr_ElementsKit_Compat::instance();
