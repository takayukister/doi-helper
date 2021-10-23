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
			DOIHELPER_Entry::verify( $token );
		} else {
			return;
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
}


class DOIHELPER_Entry {

	private $id = 0;


	private function __construct( $args = '' ) {
		$args = wp_parse_args( $args, array(
			'id' => 0,
		) );

		$this->id = (int) $args['id'];
	}


	public static function verify( $token ) {
		$entry = self::find( $token );

		if ( $entry ) {
			$acceptance_period = $entry->get_acceptance_period();
			$expires_at = get_post_timestamp( $this->id ) + $acceptance_period;

			if ( time() < $expires_at ) {
				$entry->change_status( 'opted-in' );

				$agent = $entry->call_agent();
				$agent->optin_callback();

				do_action( 'doihelper_optin', $entry );

				return $entry;
			} else {
				$entry->change_status( 'expired' );
			}
		}

		return false;
	}


	public static function find( $token ) {
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

			$entry = new self( array(
				'id' => $post->ID,
			) );

			return $entry;
		}

		return false;
	}


	public static function create( $args = '' ) {
		$args = wp_parse_args( $args, array(
			'agent_name' => '',
			'acceptance_period' => 0,
			'properties' => array(),
		) );

		$agency = DOIHELPER_Agency::get_instance();
		$agent = $agency->call_agent( $args['agent_name'] );

		if ( $agent ) {
			$post_id = wp_insert_post( array(
				'post_type' => 'doihelper_entry',
				'post_status' => 'publish',
				'post_title' => $agent->get_code_name(),
				'post_content' => '',
			) );

			if ( $post_id ) {
				add_post_meta( $post_id, '_agent', $args['agent_name'], true );

				add_post_meta( $post_id, '_token', "token here", true );

				add_post_meta( $post_id, '_acceptance_period',
					$args['acceptance_period'], true
				);

				return new self( array(
					'id' => $post_id,
				) );
			}
		}

		return false;
	}


	private function change_status( $status ) {
		if ( ! in_array( $status, array( 'opted-in', 'expired' ) ) ) {
			return false;
		}

		return wp_update_post( array(
			'ID' => $this->id,
			'post_status' => $status,
		) );
	}


	private function call_agent() {
		$agency = DOIHELPER_Agency::get_instance();
		$agent_name = get_post_meta( $this->id, '_agent', true );
		return $agency->call_agent( $agent_name );
	}


	private function get_acceptance_period() {
		return (int) get_post_meta( $this->id, '_acceptance_period', true );
	}
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
