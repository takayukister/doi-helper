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

		register_post_status( 'opted-in', array(
			'label' => __( 'Opted in', 'doi-helper' ),
			'public' => false,
			'internal' => true,
		) );

		register_post_status( 'expired', array(
			'label' => __( 'Expired', 'doi-helper' ),
			'public' => false,
			'internal' => true,
		) );

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
		'post_status' => 'publish',
		'posts_per_page' => 1,
		'offset' => 0,
		'orderby' => 'ID',
		'order' => 'ASC',
		'meta_key' => '_token',
		'meta_value' => $token,
	) );

	if ( isset( $posts[0] ) ) {
		$post = get_post( $posts[0] );

		// todo: check _acceptance_period
		// todo: change the post_status

		return $post;
	}

	return false;
}


class DOIHELPER_Agency {

	private static $instance;

	private $agents = array();

	private function __construct() {}


	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	public function register_agent( $name, $class ) {
		if ( is_subclass_of( $class, 'DOIHELPER_Agent' ) ) {
			$name = sanitize_key( $name );
			$this->agents[$name] = $class;
		}
	}


	public function call_agent( $name ) {
		$name = sanitize_key( $name );

		if ( ! empty( $this->agents[$name] ) ) {
			$class = $this->agents[$name];
			return new $class;
		}

		return null;
	}

}


abstract class DOIHELPER_Agent {

	abstract public function get_agent_name();
	abstract public function optin_callback();


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
