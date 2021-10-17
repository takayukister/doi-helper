<?php
/*
Plugin Name: Double opt-in helper
Plugin URI: https://github.com/takayukister/doi-helper
Description: A plugin that helps implement the double opt-in process.
Author: Takayuki Miyoshi
Author URI: https://ideasilo.wordpress.com/
Text Domain: doi-helper
Domain Path: /languages/
Version: 0.72
*/

define( 'DOIHELPER_VERSION', '0.72' );

define( 'DOIHELPER_PLUGIN', __FILE__ );

define( 'DOIHELPER_QUERY_KEY', 'doitoken' );


add_action( 'init',
	function () {
		register_post_type(
			'doihelper_entry',
			array(
				'labels' => array(
					'name' => __( 'DOI Entries', 'doi-helper' ),
					'singular_name' => __( 'DOI Entry', 'doi-helper' ),
				),
				'public' => false,
				'rewrite' => false,
				'query_var' => false,
			)
		);

		register_post_meta(
			'doihelper_entry',
			'_agent',
			array(
				'type' => 'string',
				'single' => true,
				'show_in_rest' => true,
				'sanitize_callback' => 'sanitize_key',
			)
		);

		register_post_meta(
			'doihelper_entry',
			'_token',
			array(
				'type' => 'string',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		register_post_meta(
			'doihelper_entry',
			'_acceptance_period',
			array(
				'type' => 'integer',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		if ( isset( $_REQUEST( DOIHELPER_QUERY_KEY ) ) ) {
			$token = (string) array_shift(
				(array) $_REQUEST( DOIHELPER_QUERY_KEY )
			);
		} else {
			return;
		}

		$entry = doihelper_verify( $token );

		if ( $entry ) {
			do_action( 'doihelper_verified', $entry );
		}
	},
	10, 0
);


function doihelper_verify( $token ) {
	$q = new WP_Query();

	$posts = $q->query( array(
		'post_type' => 'doihelper_entry',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'offset' => 0,
		'orderby' => 'ID',
		'order' => 'ASC',
	) );

	return $posts;
}


abstract class DOIHELPER_Agent {

	abstract public function get_agent_name();


	public function send_email( $args = '' ) {
		$args = wp_parse_args( $args, array(
			'time_limit' => 24 * HOUR_IN_SECONDS,
			'locale' => null,
			'sender' => null,
			'recipient' => null,
			'template' => null,
		) );

		$token = $this->create_token( $args );
	}


	private function create_token( $args = '' ) {

	}

}
