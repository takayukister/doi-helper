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
