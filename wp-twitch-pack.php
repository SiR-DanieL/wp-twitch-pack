<?php
/**
 * WP Twitch Pack
 *
 * @package     WP Twitch Pack
 * @author      Nicola Mustone
 * @license     GPL-3.0
 *
 * Plugin Name: WP Twitch Pack
 * Plugin URI:  https://wordpress.org/plugins/wp-twitch-pack/
 * Description: A pack of utilities for Twitch and WordPress, like follow buttons, video lists, channel management, etc.
 * Version:     1.0.0
 * Author:      Nicola Mustone
 * Author URI:  https://nicola.blog/
 * Text Domain: wp-twitch-pack
 * License:     GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class of the plugin WP Twitch Pack.
 */
class WP_Twitch_Pack {
	/**
	 * Loads classes, adds the required hooks and set up the HTTP client.
	 */
	public function __construct() {
		define( 'WP_TWITCH_PACK_VERSION', '1.0.0' );

		// Include shared classes.
		require_once __DIR__ . '/classes/class-twitch-pack-logger.php';
		require_once __DIR__ . '/classes/class-twitch-pack-http.php';
		require_once __DIR__ . '/classes/class-twitch-pack-status-widget.php';

		// Load admin class if on the Dashboard.
		if ( is_admin() ) {
			require_once __DIR__ . '/classes/class-twitch-pack-admin.php';
		}

		// Load settings.
		$this->_settings    = get_option( 'wp-twitch-pack-settings' );
		$this->_http_client = WP_Twitch_Pack_HTTP::instance();

		$this->load_textdomain();

		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'template_redirect', array( $this->_http_client, 'redirect_oauth' ) );

		// Load frontend features only if a Twitch channel is already connected.
		if ( ! empty( $this->_settings['code'] ) || ! empty( $this->_settings['token'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_shortcode( 'twitch_follow_button' , array( $this, 'print_follow_button' ) );
			add_shortcode( 'twitch_vods' , array( $this, 'print_twitch_vods' ) );
			add_shortcode( 'twitch_stream_status', array( $this, 'print_stream_status' ) );
		}
	}

	/**
	 * Prints the Twitch Follow button.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string
	 */
	public function print_follow_button( $atts = array() ) {
		$channel_name = esc_html( $this->_http_client->get_channel( 'display_name' ) );

		$atts = shortcode_atts( array(
			'button_text'          => sprintf( esc_html__( 'Follow %s on Twitch', 'wp-twitch-pack' ), $channel_name ),
			'button_text_followed' => sprintf( esc_html__( 'You are following %s!', 'wp-twitch-pack' ), $channel_name ),
		), $atts );

		if ( isset( $_GET['followed'] ) && 'yes' === sanitize_key( $_GET['followed'] ) ) {
			$html = '<p>' . esc_attr( $atts['button_text_followed'] ) . '</p>';
		} else {
			$oauth_url = $this->_http_client->generate_oauth_url( 'user_read user_follows_edit' );

			$html  = '<a class="wp-twitch-pack follow-button" href="' . esc_url( $oauth_url ) . '" title="' . esc_attr( $atts['button_text'] ) . '">';
			$html .= '<svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 134"><defs><style>.cls-1{fill:#fff;fill-rule:evenodd;}</style></defs><title>Glitch_White_RGB</title><path class="cls-1" d="M89,77l-9,23v94h32v17h18l17-17h26l35-35V77H89Zm107,76-20,20H144l-17,17V173H100V89h96v64Zm-20-41v35H164V112h12Zm-32,0v35H132V112h12Z" transform="translate(-80 -77)"/></svg>';
			$html .= esc_html( $atts['button_text'] ) . '</a>';
		}

		return apply_filters( 'wp_twitch_pack_follow_button_html', $html );
	}

	/**
	 * Prints the channel VODs.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string
	 */
	public function print_twitch_vods( $atts = array() ) {

		if ( isset( $atts['broadcast_type'] ) ) {
			switch ( $atts['broadcast_type'] ) {
				case 'highlight':
					unset( $atts['broadcast_type'] );
					$videos = $this->_http_client->get_channel_highlights( array( 'query' => $atts ) );
					break;
				case 'archive':
					unset( $atts['broadcast_type'] );
					$videos = $this->_http_client->get_channel_archive( array( 'query' => $atts ) );
					break;
				default:
					$videos = $this->_http_client->get_channel_videos( array( 'query' => $atts ) );
					break;
			}
		} else {
			$videos = $this->_http_client->get_channel_videos( array( 'query' => $atts ) );
		}

		$html   = '';

		if ( false !== $videos ) {
			foreach ( $videos as  $video ) {
				$video_html = '<div id="video-' . esc_attr( $video->_id ) . '" class="wp-twitch-pack video">';
					$video_html .= '<h3><a href="' . esc_url( $video->url ) . '" title="' . esc_attr( $video->title ) . '" target="_blank">' . esc_html( $video->title ) . '</a></h3>';
					$video_html .= '<div class="preview">';
						$video_html .= '<a href="' . esc_url( $video->url ) . '" title="' . esc_attr( $video->title ) . '" target="_blank">';
						$video_html .= '<img src="' . esc_url( $video->preview->medium ) . '" title="' . esc_attr( $video->title ) . '" alt="' . esc_attr__( 'Video Preview', 'wp-twitch-pack' ) . '" />';
						$video_html .= '</a>';
					$video_html .= '</div>';
					$video_html .= '<div class="description">';
						$video_html .= '<ul>';
							$video_html .= '<li class="game">' . esc_html__( 'Game:', 'wp-twitch-pack' ) . ' ' . esc_html( $video->game ) . '</li>';
							$video_html .= '<li class="date">' . esc_html__( 'Date:', 'wp-twitch-pack' ) . ' <time datetime="' . esc_attr( $video->recorded_at ) . '">' . date_i18n( get_option( 'date_format' ), strtotime( $video->recorded_at ) ) . '</time>';
							$video_html .= '<li class="length">' . esc_html__( 'Length:', 'wp-twitch-pack' ) . gmdate( ' H\h i\m', $video->length ) . '</li>';
							$video_html .= '<li class="views">' . esc_html__( 'Views:', 'wp-twitch-pack' ) . ' ' . absint( $video->views ) . '</li>';
						$video_html .= '</ul>';
					$video_html .= '</div>';
				$video_html .= '</div>';

				$html .= apply_filters( 'wp_twitch_pack_vod_html', $video_html, $video );
			}
		}

		return $html;
	}

	/**
	 * Prints the HTML for the Twitch status.
	 *
	 * @param  array $atts Shortcoe attributes.
	 * @return string
	 */
	public function print_stream_status( $atts ) {
		$atts = shortcode_atts( array(
			'wrap'           => 'div',
			'print_username' => 'yes',
			'print_game'     => 'no',
		), $atts );

		$stream  = $this->_http_client->get_stream_status();
		$channel = $this->_settings['channel'];
		$status  = 'live' === $data['status'] ? esc_html__( 'Live', 'wp-twitch-pack' ) : esc_html__( 'Offline', 'wp-twitch-pack' );
		$intro   = sprintf( esc_html__( '%s is', 'wp-twitch-staus' ), '<a href="' . esc_url( $channel->url ) . '" target="_blank">' . ( 'yes' === $atts['print_username'] ? $channel->display_name : esc_html__( 'Stream', 'wp-twitch-staus' ) ) . '</a>' );

		if ( 'yes' === $atts['print_game'] && 'live' === $data['status'] ) {
			$between = 'Creative' === $data['game'] ? esc_html__( 'streaming', 'wp-twitch-pack' ) : esc_html__( 'playing', 'wp-twitch-pack' );
			$game    = ' ' . $between . ' ' . $data['game'];
		} else {
			$game = '';
		}

		$html  = '';
		$html .= '<' . esc_html( $atts['wrap'] ) . ' class="wp-twitch-pack stream-status">';

		if ( 'div' === $atts['wrap'] ) {
			$html .= '<p>';
		}

		$html .= '<span class="wp-twitch-pack stream-status intro">' . $intro . ' </span>';
		$html .= '<span class="wp-twitch-pack stream-status status ' . esc_attr( strtolower( $status ) ) . '">' . esc_html( $status ) . $game . '</span>';

		if ( 'div' === $atts['wrap'] ) {
			$html .= '</p>';
		}

		$html .= '</' . esc_html( $atts['wrap'] ) . '>';

		return $html;
	}

	/**
	 * Registers the widget WP_Twitch_Pack_Status_Widget.
	 */
	public function register_widget() {
		register_widget( 'WP_Twitch_Pack_Status_Widget' );
	}


	/**
	 * Enqueues scripts and styles on the frontend.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'wp-twitch-pack', plugins_url( 'assets/style.css', __FILE__ ) );
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}
	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}


	/**
	 * Loads the plugin localization files.
	 */
	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-twitch-pack' );
		load_textdomain( 'wp-twitch-pack', WP_LANG_DIR . '/wp-twitch-videos/discord-post-' . $locale . '.mo' );
		load_plugin_textdomain( 'wp-twitch-pack', false, plugin_basename( __DIR__ ) . '/languages' );
	}
}

new WP_Twitch_Pack();
