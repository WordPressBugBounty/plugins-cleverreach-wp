<?php
/**
 * CleverReach WordPress Integration.
 *
 * @package CleverReach
 */

namespace CleverReach\WordPress\Components\Utility;

/**
 * A utility class that adds custom CSS applied to CleverReach forms.
 */
class Forms_Formatter {
	const CSS_FILE_PATH = '/cleverreach-wp/resources/css/cleverreach-form-style.css';

	/**
	 * Returns formatted form HTML with applied custom CSS.
	 *
	 * @param string $form_html
	 *
	 * @return mixed
	 */
	public static function get_form_code( $form_html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return $form_html;
		}

		$dom           = new \DOMDocument();
		$dom->encoding = 'utf-8';

		@$dom->loadHTML( file_get_contents( WP_PLUGIN_DIR . '/cleverreach-wp/resources/views/recaptcha.php' ) );

		// Suppress warnings generated by loadHTML
		$errors = libxml_use_internal_errors( true );
		@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $form_html );
		libxml_use_internal_errors( $errors );

		$styles  = $dom->getElementsByTagName( 'style' );
		$buttons = $dom->getElementsByTagName( 'button' );
		if ( $styles->length !== 0 ) {
			$css = file_get_contents( WP_PLUGIN_DIR . self::CSS_FILE_PATH );

			/** @var \DOMNode $style */
			$style              = $styles[0];
			$style->textContent = $css;
		}

		foreach ( $buttons as $button ) {
			/** @var \DOMElement $button */
			$class_list = $button->getAttribute( 'class' );
			$class_list .= ' components-button is-button is-primary';
			$button->setAttribute( 'class', $class_list );
		}

		return $dom->saveHTML();
	}
}
