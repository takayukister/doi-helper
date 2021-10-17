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

define( 'DOIHELPER_QUERY_KEY', 'doicode' );


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

		if ( isset( $_REQUEST( DOIHELPER_QUERY_KEY ) ) ) {
			$code = (string) array_shift(
				(array) $_REQUEST( DOIHELPER_QUERY_KEY )
			);
		} else {
			return;
		}

		$entry = doihelper_verify( $code );

		if ( $entry ) {
			do_action( 'doihelper_verified', $entry );
		}
	},
	10, 0
);


function doihelper_verify( $code ) {
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

		$code = $this->generate_code( $args );
	}


	private function generate_code( $args = '' ) {

	}

}
