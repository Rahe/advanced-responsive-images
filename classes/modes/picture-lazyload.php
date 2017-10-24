<?php

namespace ARI\Modes;

use ARI\Mode_Interface;
use ARI\Image_Locations;
use ARI\Image_Sizes;

/**
 * Abstract Class Picture_Lazyload
 *
 * List of vars replaced :
 *      - %%srcset%% : white pixel html
 *      - %%attributes%% : composed of classes and alt for default img
 *      - %%sources%% : list of sources composed of image sizes
 *      - %%img-617-333%% : exemple of image size to replace by URL
 *
 * @package ARI\Modes
 */
class Picture_Lazyload extends Mode implements Mode_Interface {

	/**
	 * @var []
	 */
	private $args = array();

	/**
	 * @var int
	 */
	private $attachment_id = 0;

	/**
	 *
	 * @author Alexandre Sadowski
	 */
	protected function init() {
	}

	/**
	 * @param $args
	 *
	 * @author Alexandre Sadowski
	 */
	public function set_args( $args ) {
		$this->args = $args;
	}

	public function set_attachment_id( $id ) {
		$this->attachment_id = (int) $id;
	}

	/**
	 * @author Alexandre Sadowski
	 */
	public function add_filters() {
		add_filter( 'post_thumbnail_html', array( $this, 'update_html' ), self::$priority, 5 );
		self::$priority ++;
	}

	/**
	 * Display default img if empty post_thumbnail
	 *
	 * @param $html
	 * @param $post_id
	 * @param $post_thumbnail_id
	 * @param $size
	 * @param $attr
	 *
	 * @return string
	 * @author Alexandre Sadowski
	 */
	public function update_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		if ( ! isset( $this->args['data-location'] ) ) {
			return $html;
		}

		return $this->render_image( $html );
	}

	/**
	 * @param string $html
	 *
	 * @author Alexandre Sadowski
	 */
	public function render_image( $html = '' ) {
		/**
		 * @var $locations Image_Locations
		 */
		$locations      = Image_Locations::get_instance();
		$location_array = $locations->get_location( $this->args['data-location'] );

		//check all tpl
		$check_tpl = $this->check_tpl( $location_array, $html );
		if ( ! is_array( $check_tpl ) ) {
			return $check_tpl;
		}

		$img_size = Image_Sizes::get_instance();


		$location_content = $check_tpl['location_content'];
		$main_content     = $check_tpl['main_content'];

		$classes        = array( $this->args['classes'] );
		$location_array = reset( $location_array );
		foreach ( $location_array->srcsets as $location ) {
			if ( ! isset( $location->size ) || empty( $location->size ) ) {
				continue;
			}
			/**
			 * @var $img_size Image_Sizes
			 */
			$img = wp_get_attachment_image_src( $this->attachment_id, (array) $img_size->get_image_size( $location->size ) );
			if ( empty( $img ) ) {
				continue;
			}

			// Verif SSL
			$img[0] = ( function_exists( 'is_ssl' ) && is_ssl() ) ? str_replace( 'http://', 'https://', $img[0] ) : $img[0];

			// Replace size in content
			$location_content = str_replace( '%%' . $location->size . '%%', $img[0], $location_content );

			// Get classes for each size
			if ( isset( $location->class ) && ! empty( $location->class ) ) {
				$classes[] = $location->class;
			}
		}

		// Add default img url
		if ( isset( $location_array->img_base ) && ! empty( $location_array->img_base ) ) {
			$default_img = wp_get_attachment_image_src( $this->attachment_id, (array) $img_size->get_image_size( $img_size ), false );
		} else {
			$default_img = wp_get_attachment_image_src( $this->attachment_id, 'thumbnail', false );
		}

		if ( is_array( $default_img ) ) {
			$main_content = str_replace( '%%default_img%%', reset( $default_img ), $main_content );
		}

		// Add sources in main content tpl
		$content_with_sources = str_replace( '%%sources%%', $location_content, $main_content );

		// Add all attributes : classes, alt...
		$alt     = trim( strip_tags( get_post_meta( $this->attachment_id, '_wp_attachment_image_alt', true ) ) );
		$classes = implode( ' ', $classes );

		$attributes              = 'class="' . esc_attr( $classes ) . '" alt="' . esc_attr( $alt ) . '"';
		$content_with_attributes = str_replace( '%%attributes%%', $attributes, $content_with_sources );

		// Add pixel on all
		return str_replace( '%%srcset%%', 'src="' . ARI_PIXEL . '"', $content_with_attributes );
	}

	/**
	 * @param $location_array
	 * @param $html
	 *
	 * @return array|mixed
	 * @author Alexandre Sadowski
	 */
	private function check_tpl( $location_array, $html ) {
		if ( ! is_array( $location_array ) ) {
			return str_replace( '/>', 'data-error="No location found in source file" />', $html );
		}

		$location_array = reset( $location_array );
		if ( ! isset( $location_array->srcsets ) || empty( $location_array->srcsets ) ) {
			return str_replace( '/>', 'data-error="No srcsets found or not V2 JSON" />', $html );
		}

		//Check if default tpl is overloaded
		if ( isset( $this->args['data-tpl'] ) && ! empty( $this->args['data-tpl'] ) ) {
			$main_tpl = ARI_JSON_DIR . 'tpl/' . $this->args['data-tpl'] . '.tpl';
		} else {
			$main_tpl = ARI_JSON_DIR . 'tpl/default-picture.tpl';
		}

		if ( ! is_readable( $main_tpl ) ) {
			return str_replace( '/>', 'data-error="Default tpl not exists or not readable" />', $html );
		}

		$main_content = file_get_contents( $main_tpl );
		if ( empty( $main_content ) ) {
			return str_replace( '/>', 'data-error="Empty default tpl" />', $html );
		}

		//Check if default tpl is overloaded
		$location_tpl = ARI_JSON_DIR . 'tpl/' . $this->args['data-location'] . '.tpl';

		if ( ! is_readable( $location_tpl ) ) {
			return str_replace( '/>', 'data-error="Location tpl not exists or not readable" />', $html );
		}

		$location_content = file_get_contents( $location_tpl );
		if ( empty( $location_content ) ) {
			return str_replace( '/>', 'data-error="Empty location tpl" />', $html );
		}

		return array( 'location_content' => $location_content, 'main_content' => $main_content );
	}
}