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
	},
	10, 0
);


abstract class DOIHELPER_Agent {

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


function doihelper_verify( $code ) {

}


add_action( 'init', function () {

	$code = '';

	if ( doihelper_verify( $code ) ) {
		do_action( 'doihelper_verified' );
	}

}, 10, 0 );
