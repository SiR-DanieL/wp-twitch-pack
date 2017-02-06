<?php
/**
 * The main HTTP client for WP Twitch Pack.
 *
 * Manages all the HTTP requests, and generates the OAuth URLs and tokens.
 *
 * @package WP Twitch Pack
 * @author  Nicola Mustone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles all the OAuth and HTTP requests for WP Twitch Pack.
 */
class WP_Twitch_Pack_HTTP {
	/**
	 * WP_Twitch_Pack_HTTP instance
	 *
	 * @var WP_Twitch_Pack_HTTP
	 * @static
	 * @access private
	 */
	private static $_instance = null;

	/**
	 * Plugin settings
	 *
	 * @var array
	 * @access private
	 */
	private $_settings = array();

	/**
	 * Twitch Client ID.
	 *
	 * @var string
	 * @access private
	 */
	private $_client_id = '';

	/**
	 * The base endpoint of the API for Twitch.
	 *
	 * @var string
	 * @access private
	 */
	private $_endpoint = 'https://api.twitch.tv/kraken/';

	/**
	 * The user OAuth access token.
	 *
	 * @var string
	 * @access private
	 */
	private $_user_access_token = '';

	/**
	 * Instance of WP_Twitch_Pack_Logger.
	 *
	 * @var WP_Twitch_Pack_Logger
	 * @access private
	 */
	private $_log = null;

	/**
	 * Main WP_Twitch_Pack_HTTP Instance.
	 *
	 * Ensures only one instance of WP_Twitch_Pack_HTTP is loaded or can be loaded.
	 *
	 * @static
	 * @return WP_Twitch_Pack_HTTP
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Loads the settings and and adds the necessary hooks.
	 *
	 * @access private
	 */
	private function __construct() {
		$this->_settings  = get_option( 'wp-twitch-pack-settings' );
		$this->_client_id = $this->_settings['client_id'];
		$this->_log       = new WP_Twitch_Pack_Logger();
	}

	/**
	 * Redirects to the admin if it's for authorization purposes and using an admin account.
	 * Otherwise redirects to the home page after checking if the user followed.
	 */
	public function redirect_oauth() {
		if ( isset( $_GET['code'] ) && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=wp-twitch-pack&code=' . sanitize_key( $_GET['code'] ) ) );
			exit;
		} elseif ( isset( $_GET['code'] ) ) {
			$followed     = 'no';
			$this->_user_access_token = $this->get_oauth_token( sanitize_key( $_GET['code'] ) );

			if ( false !== $this->_user_access_token ) {
				$channel_id = $this->_settings['channel']->_id;
				$user_id    = (int) $this->get_user( '_id' );

				if ( ! $this->is_following_channel( $user_id, $channel_id ) ) {
					// Follows the admin's channel for the user.
					$result = $this->follow_channel( $user_id, $channel_id );

					$followed = ( true === $result ? 'yes' : 'no' );
				} else {
					$followed = 'yes';
				}
			}

			wp_safe_redirect( add_query_arg( 'followed', $followed, home_url() ) );
			exit;
		}
	}

	/**
	 * Returns the oAuth authorization URL for the user.
	 *
	 * @param  string $scope        The scope of the request.
	 * @return string               The OAuth authorization URL.
	 */
	public function generate_oauth_url( $scope ) {
		if ( is_array( $scope ) ) {
			$scope = implode( ' ', $scope );
		}

		$args = array(
			'response_type' => 'code',
			'client_id'     => esc_html( $this->_settings['client_id'] ),
			'redirect_uri'  => home_url(),
			'scope'         => esc_html( $scope ),
			'state'         => wp_create_nonce( 'wp-twitch-pack-oauth' ),
		);

		return $this->_endpoint . 'oauth2/authorize?' . http_build_query( $args );
	}

	/**
	 * Gets the user oAuth token.
	 *
	 * @param  string $authorization_code The authorization code returned by the oAuth authorization.
	 * @return string
	 * @throws Exception The error message for the exception.
	 */
	public function get_oauth_token( $authorization_code ) {
		$args = array(
			'client_id'     => esc_html( $this->_settings['client_id'] ),
			'client_secret' => esc_html( $this->_settings['client_secret'] ),
			'redirect_uri'  => home_url(),
			'grant_type'    => 'authorization_code',
			'code'          => esc_html( $authorization_code ),
			'state'         => wp_create_nonce( 'wp-twitch-pack-oauth' ),
		);

		$response = $this->_make_api_call( 'POST', 'oauth2/token', array( 'body' => http_build_query( $args ) ), false );

		if ( false !== $response ) {
			return $response->access_token;
		}

		return false;
	}

	/**
	 * Gets the administrator channel object.
	 *
	 * @param  string $index  The specific index to return from the channel object.
	 * @param  array  $params Additional request parameters.
	 * @return mixed          Returns the channel object, or false on failure.
	 */
	public function get_channel( $index = '', $params = array() ) {
		$channel = $this->_make_api_call( 'GET', 'channel', $params );

		if ( false !== $channel ) {
			if ( ! empty( $index ) && isset( $channel->{$index} ) ) {
				return $channel->{$index};
			}

			return $channel;
		}

		return false;
	}

	/**
	 * Gets the channel VODs.
	 *
	 * @param  array $params Additional request parameters.
	 * @return mixed         Returns the videos object, or false on failure.
	 */
	public function get_channel_videos( $params = array() ) {
		$channel_id = absint( $this->_settings['channel']->_id );

		$videos = $this->_make_api_call( 'GET', 'channels/' . $channel_id . '/videos', $params );

		if ( false !== $videos ) {
			return $videos->videos;
		}

		return false;
	}

	/**
	 * Returns the channel highlighted VODs.
	 *
	 * @param  array $params Additional request parameters.
	 * @return mixed Returns the videos object, or false on failure.
	 */
	public function get_channel_highlights( $params = array() ) {
		$videos = wp_cache_get( 'wp-twitch-pack-videos-highlight' );

		if ( false !== $videos ) {
			return $videos;
		}

		$params['query']['broadcast_type'] = 'highlight';
		$videos                            = $this->get_channel_videos( $params );

		if ( false !== $videos ) {
			wp_cache_set( 'wp-twitch-pack-videos-highlight', $videos, false, DAY_IN_SECONDS );

			return $videos;
		}

		return false;
	}

	/**
	 * Returns the channel archived VODs.
	 *
	 * @param  array $params Additional request parameters.
	 * @return mixed Returns the videos object, or false on failure.
	 */
	public function get_channel_archive( $params = array() ) {
		$videos = wp_cache_get( 'wp-twitch-pack-videos-archive' );

		if ( false !== $videos ) {
			return $videos;
		}

		$params['query']['broadcast_type'] = 'archive';
		$videos                            = $this->get_channel_videos( $params );

		if ( false !== $videos ) {
			wp_cache_set( 'wp-twitch-pack-videos-archive', $videos, false, DAY_IN_SECONDS );

			return $videos;
		}

		return false;
	}

	/**
	 * Returns the status of the admin stream.
	 *
	 * @param  array $params Additional request parameters.
	 * @return mixed
	 */
	public function get_stream( $params = array() ) {
		$channel_id = $this->_settings['channel']->_id;
		$stream     = wp_cache_get( 'wp-twitch-pack-stream' );

		if ( false !== $stream ) {
			return $stream;
		}

		$stream = $this->_make_api_call( 'GET', 'streams/' . $channel_id, $params );

		if ( false !== $stream ) {
			wp_cache_set( 'wp-twitch-pack-stream', $stream->stream, false, 60 * 30 );

			return $stream->stream;
		}

		return false;
	}

	/**
	 * Returns the status of a stream.
	 *
	 * @param  array $params Additional request parameters.
	 * @return mixed
	 */
	public function get_stream_status( $params = array() ) {
		$channel_id = $this->_settings['channel']->_id;
		$stream     = wp_cache_get( 'wp-twitch-pack-stream' );

		if ( false === $stream ) {
			$stream = $this->get_stream();
		}

		$data = array();

		if ( ! empty( $stream ) ) {
			$data['status'] = 'live';
			$data['game']   = $stream->game;
		} else {
			$data['status'] = 'offline';
		}

		return $data;
	}

	/**
	 * Gets the user object.
	 *
	 * @param  string $index  The specific index to return from the user object.
	 * @param  array  $params Additional request parameters.
	 * @return mixed          Returns the user object, or false on failure.
	 */
	public function get_user( $index = '', $params = array() ) {
		if ( ! current_user_can( 'manage_options' ) && ! empty( $this->_user_access_token ) ) {
			$params['headers']['Authorization'] = 'OAuth ' . $this->_user_access_token;
		}

		$user = $this->_make_api_call( 'GET', 'user', $params );

		if ( false !== $user ) {
			if ( ! empty( $index ) && isset( $user->{$index} ) ) {
				return $user->{$index};
			}

			return $user;
		}

		return false;
	}

	/**
	 * Checks if a user is following the connected channel.
	 *
	 * @param  int   $user_id    The user ID.
	 * @param  array $params     Additional request parameters.
	 * @return bool
	 */
	public function is_following_channel( $user_id, $params = array() ) {
		if ( ! current_user_can( 'manage_options' ) && ! empty( $this->_user_access_token ) ) {
			$params['headers']['Authorization'] = 'OAuth ' . $this->_user_access_token;
		}

		$channel_id = $this->_settings['channel']->_id;

		if ( false !== $channel_id && false !== $user_id ) {
			$response = $this->_make_api_call( 'GET', 'users/' . $user_id . '/follows/channels/' . $channel_id, $params );

			if ( isset( $response->channel->_id ) && absint( $response->channel->_id ) === absint( $channel_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Follows the connected channel for a user.
	 *
	 * @param  int   $user_id    The user ID.
	 * @param  array $params     Additional request parameters.
	 * @return bool
	 */
	public function follow_channel( $user_id, $params = array() ) {
		if ( ! current_user_can( 'manage_options' ) && ! empty( $this->_user_access_token ) ) {
			$params['headers']['Authorization'] = 'OAuth ' . $this->_user_access_token;
		}

		if ( false !== $this->_settings['channel']->_id && false !== $user_id ) {
			$response = $this->_make_api_call( 'PUT', 'users/' . $user_id . '/follows/channels/' . $this->_settings['channel']->_id, $params );

			if ( absint( $response->channel->_id ) === absint( $this->_settings['channel']->_id ) ) {
				$this->_log->info( sprintf( esc_html__( 'User %1$d is now following the channel %2$d', 'wp-twitch-pack' ), $user_id, $this->_settings['channel']->_id ) );

				$followers_from_site = (int) get_option( 'wp-twitch-pack-followers-from-site' );
				update_option( 'wp-twitch-pack-followers-from-site', ++$followers_from_site );

				return true;
			}
		}

		return false;
	}

	/**
	 * Makes an API call to Twitch.
	 *
	 * @param  string $method        The method of the request.
	 * @param  string $endpoint      The endpoint where to send the request.
	 * @param  array  $params        The query string for the request.
	 * @param  bool   $authenticated Specifies if the request has to be authenticated or not.
	 * @return object|bool           Returns the response body or false on failure.
	 * @access private
	 */
	private function _make_api_call( $method, $endpoint, $params = array(), $authenticated = true ) {
		$endpoint  = esc_url( $this->_endpoint . $endpoint );
		$data      = array();

		if ( in_array( strtoupper( $method ), array( 'GET', 'POST', 'PUT', 'DELETE' ), true ) ) {
			$data = array(
				'method'  => esc_html( strtoupper( $method ) ),
				'headers' => array(
					'Client-ID' => esc_html( $this->_settings['client_id'] ),
					'Accept'    => 'application/vnd.twitchtv.v5+json',
				),
			);
		}

		// If the request needs to be authenticated, send the Authorization header.
		if ( true === $authenticated ) {
			$data['headers']['Authorization'] = 'OAuth ' . $this->_settings['token'];
		}

		$data = apply_filters( 'wp_twitch_pack_api_call_params', array_replace_recursive( $data, $params ) );

		if ( isset( $data['query'] ) && '' !== $data['query'] ) {
			$query_string = http_build_query( $data['query'] );
			$endpoint    .= '?' . $query_string;

			unset( $data['query'] );
		}

		$response = wp_remote_request( $endpoint, $data );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		$this->_log->error( sprintf( esc_html_x( '%1$d - %2$s', 'error number - error message', 'wp-twitch-pack' ), wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_response_message( $response ) ) );
		$this->_log->debug( print_r( json_decode( wp_remote_retrieve_body( $response ) ), true ) );

		return false;
	}
}
