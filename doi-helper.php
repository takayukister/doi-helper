<?php
/*
Plugin Name: DOI Helper
Plugin URI: https://contactform7.com/doi-helper/
Description: A WordPress plugin that helps developers implement the double opt-in process in their plugins.
Author: Takayuki Miyoshi
Author URI: https://ideasilo.wordpress.com/
Text Domain: doi-helper
Domain Path: /languages/
Version: 0.72
*/

define( 'DOIHELPER_VERSION', '0.72' );

define( 'DOIHELPER_PLUGIN', __FILE__ );


/**
 * Wrapper function for DOIHELPER_Manager::register_agent().
 */
function doihelper_register_agent( $agent_name, $args = '' ) {
	$manager = DOIHELPER_Manager::get_instance();
	return $manager->register_agent( $agent_name, $args );
}


/**
 * Wrapper function for DOIHELPER_Manager::start_session().
 */
function doihelper_start_session( $agent_name, $properties = array() ) {
	$manager = DOIHELPER_Manager::get_instance();
	return $manager->start_session( $agent_name, $properties );
}


add_action( 'init',
	function () {
		doihelper_register_post_types();

		if ( isset( $_REQUEST['doitoken'] ) ) {
			$manager = DOIHELPER_Manager::get_instance();
			$manager->verify_token( $_REQUEST['doitoken'] );
		}
	},
	10, 0
);


add_action( 'wp_after_insert_post',
	function ( $post_id ) {
		$post_type = get_post_type( $post_id );
		$post_status = get_post_status( $post_id );

		if ( 'doihelper_entry' === $post_type and 'future' !== $post_status ) {
			wp_delete_post( $post_id, true );
		}
	},
	10, 1
);


/**
 * Registers the post types and post metas for this plugin.
 */
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
		'_properties',
		array(
			'type' => 'array',
			'single' => true,
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
}


class DOIHELPER_Manager {

	private static $instance;

	private $agents = array();

	private function __construct() {}


	/**
	 * Retrieves the singleton instance of DOI manager.
	 *
	 * @param DOIHELPER_Manager The manager object.
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	/**
	 * Registers an agent.
	 *
	 * @param string $agent_name Agent name.
	 * @param string|array $args Optional. Arguments for the agent.
	 */
	public function register_agent( $agent_name, $args = '' ) {
		$agent_name = sanitize_key( $agent_name );

		$args = wp_parse_args( $args, array(
			'acceptance_period' => 24 * HOUR_IN_SECONDS,
			'optin_callback' => null,
			'email_callback' => null,
		) );

		$this->agents[$agent_name] = $args;
	}


	/**
	 * Calls an agent.
	 *
	 * @param string $agent_name Agent name.
	 * @return array|null The array of arguments for the agent
	 *                    if the agent exists, null otherwise.
	 */
	public function call_agent( $agent_name ) {
		$agent_name = sanitize_key( $agent_name );

		if ( ! empty( $this->agents[$agent_name] ) ) {
			return $this->agents[$agent_name];
		}

		return null;
	}


	/**
	 * Starts a double opt-in session.
	 *
	 * @param string $agent_name Agent name.
	 * @param string|array $args Optional. Arguments for the session.
	 * @return string|bool The token on success, false on failure.
	 */
	public function start_session( $agent_name, $args = '' ) {
		$args = wp_parse_args( $args, array(
			'properties' => array(),
		) );

		$agent_name = sanitize_key( $agent_name );
		$agent = $this->call_agent( $agent_name );

		if ( ! $agent ) {
			return false;
		}

		$expires_at = new DateTimeImmutable(
			sprintf( '@%d', time() + (int) $agent['acceptance_period'] ),
			wp_timezone()
		);

		$properties = (array) $args['properties'];

		$token = wp_generate_password( 24, false );

		$post_id = wp_insert_post( array(
			'post_type' => 'doihelper_entry',
			'post_status' => 'future',
			'post_date' => $expires_at->format( 'Y-m-d H:i:s' ),
			'post_title' => __( 'DOI Entry', 'doi-helper' ),
			'post_content' => '',
		) );

		if ( $post_id ) {
			add_post_meta( $post_id, '_agent', $agent_name, true );
			add_post_meta( $post_id, '_properties', $properties, true );
			add_post_meta( $post_id, '_token', $token, true );

			$args = array_merge( $args, array(
				'token' => $token,
				'expires_at' => $expires_at,
			) );

			$this->send_email( $agent_name, $args );

			return $token;
		}

		return false;
	}


	/**
	 * Sends a confirmation email.
	 *
	 * @param string $agent_name Agent name.
	 * @param string|array $args Optional. Arguments for the email.
	 * @return bool True if email has been sent successfully. False otherwise.
	 */
	public function send_email( $agent_name, $args = '' ) {
		$args = wp_parse_args( $args, array(
			'email_to' => null,
		) );

		if ( empty( $args['email_to'] ) or ! is_email( $args['email_to'] ) ) {
			return false;
		}

		$agent_name = sanitize_key( $agent_name );
		$agent = $this->call_agent( $agent_name );

		if ( ! $agent ) {
			return false;
		}

		if ( is_callable( $agent['email_callback'] ) ) {
			return call_user_func( $agent['email_callback'], $args );
		} else {
			// todo: send default email
		}
	}


	/**
	 * Verifies the given token.
	 *
	 * @param string $token The token.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function verify_token( $token ) {
		$q = new WP_Query();

		$posts = $q->query( array(
			'post_type' => 'doihelper_entry',
			'post_status' => 'future',
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

			if ( get_post_timestamp( $post ) < time() ) {
				wp_delete_post( $post->ID, true );
				return false;
			}

			$agent_name = get_post_meta( $post->ID, '_agent', true );
			$agent = $this->call_agent( $agent_name );

			if ( ! $agent ) {
				return false;
			}

			if ( is_callable( $agent['optin_callback'] ) ) {
				$properties = (array) get_post_meta( $post->ID, '_properties', true );
				call_user_func( $agent['optin_callback'], $properties );
			}

			wp_delete_post( $post->ID, true );
			return true;
		}

		return false;
	}

}
