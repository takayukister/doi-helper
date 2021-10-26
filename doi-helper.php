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
		doihelper_register_post_types();

		if ( isset( $_REQUEST[DOIHELPER_Manager::token_query_key] ) ) {
			$token = $_REQUEST[DOIHELPER_Manager::token_query_key];

			$manager = DOIHELPER_Manager::get_instance();
			$manager->verify_token( $token );
		}
	},
	10, 0
);


function doihelper_register_post_types() {
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
			'sanitize_callback' => 'sanitize_key',
		)
	);

	register_post_meta(
		'doihelper_entry',
		'_token',
		array(
			'type' => 'string',
			'single' => true,
		)
	);

	register_post_meta(
		'doihelper_entry',
		'_properties',
		array(
			'type' => 'array',
			'single' => true,
		)
	);
}


class DOIHELPER_Manager {

	const token_query_key = 'doitoken';

	private static $instance;

	private $agents = array();

	private function __construct() {}


	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	public function register_agent( $agent_name, $args = '' ) {
		$agent_name = sanitize_key( $agent_name );

		$args = wp_parse_args( $args, array(
			'acceptance_period' => 24 * HOUR_IN_SECONDS,
			'optin_callback' => null,
		) );

		$this->agents[$agent_name] = $args;
	}


	public function call_agent( $agent_name ) {
		$agent_name = sanitize_key( $agent_name );

		if ( ! empty( $this->agents[$agent_name] ) ) {
			return $this->agents[$agent_name];
		}

		return null;
	}


	public function start_doi_session( $agent_name, $properties = array() ) {
		$agent_name = sanitize_key( $agent_name );
		$agent = $this->call_agent( $agent_name );

		if ( ! $agent ) {
			return false;
		}

		$post_id = wp_insert_post( array(
			'post_type' => 'doihelper_entry',
			'post_status' => 'publish',
			'post_title' => __( 'DOI Entry', 'doi-helper' ),
			'post_content' => '',
		) );

		if ( $post_id ) {
			$token = wp_generate_password( 24, false );

			add_post_meta( $post_id, '_agent', $agent_name, true );
			add_post_meta( $post_id, '_token', $token, true );
			add_post_meta( $post_id, '_properties', (array) $properties, true );

			return $token;
		}

		return false;
	}


	public function verify_token( $token ) {
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

		if ( ! isset( $posts[0] ) ) {
			return false;
		}

		$post = get_post( $posts[0] );

		if ( $post ) {
			$agent_name = get_post_meta( $post->ID, '_agent', true );
			$agent = $this->call_agent( $agent_name );

			if ( ! $agent ) {
				return false;
			}

			$acceptance_period = (int) $agent['acceptance_period'];
			$expires_at = get_post_timestamp( $post->ID ) + $acceptance_period;

			if ( time() < $expires_at ) {
				if ( is_callable( $agent['optin_callback'] ) ) {
					$properties = (array) get_post_meta( $post->ID, '_properties', true );
					call_user_func( $agent['optin_callback'], $properties );
				}

				wp_delete_post( $post->ID, true );
				return true;
			} else {
				wp_delete_post( $post->ID, true );
				return false;
			}
		}

		return false;
	}

}
