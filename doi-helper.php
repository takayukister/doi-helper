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

define( 'DOIHELPER_TOKEN_QUERY_KEY', 'doitoken' );


add_action( 'init',
	function () {
		doihelper_register_post_types();

		if ( isset( $_REQUEST[DOIHELPER_TOKEN_QUERY_KEY] ) ) {
			$token = $_REQUEST[DOIHELPER_TOKEN_QUERY_KEY];

			$agency = DOIHELPER_Agency::get_instance();
			$agency->verify_token( $token );
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


	public function register_agent( $agent_name, $class ) {
		$agent_name = sanitize_key( $agent_name );

		if ( is_subclass_of( $class, 'DOIHELPER_Agent' ) ) {
			$this->agents[$agent_name] = $class;
		}
	}


	public function call_agent( $agent_name ) {
		$agent_name = sanitize_key( $agent_name );

		if ( ! empty( $this->agents[$agent_name] ) ) {
			$class = $this->agents[$agent_name];
			return new $class;
		}

		return null;
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

			$acceptance_period = (int) $agent::acceptance_period;
			$expires_at = get_post_timestamp( $post->ID ) + $acceptance_period;

			if ( time() < $expires_at ) {
				wp_update_post( array(
					'ID' => $post->ID,
					'post_status' => 'opted-in',
				) );

				$agent->optin_callback();
				return true;
			} else {
				wp_update_post( array(
					'ID' => $post->ID,
					'post_status' => 'expired',
				) );
			}
		}

		return false;
	}

}


abstract class DOIHELPER_Agent {

	const acceptance_period = 24 * HOUR_IN_SECONDS;

	abstract public function get_code_name();
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
