<?php
/**
 * Main class for the admin settings of WP Twitch Pack.
 *
 * @package WP Twitch Pack
 * @author  Nicola Mustone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class for the admin settings of WP Twitch Pack.
 */
class WP_Twitch_Pack_Admin {
	/**
	 * Plugin settings.
	 *
	 * @var array
	 * @access private
	 */
	private $_settings = array();

	/**
	 * WP_Twitch_Pack_HTTP instance.
	 *
	 * @var WP_Twitch_Pack_HTTP
	 * @access private
	 */
	private $_http_client = null;

	/**
	 * Instance of WP_Twitch_Pack_Logger.
	 *
	 * @var WP_Twitch_Pack_Logger
	 * @access private
	 */
	private $_log = null;

	/**
	 * Adds menu item and page and inits the settings.
	 */
	public function __construct() {
		$this->_http_client = WP_Twitch_Pack_HTTP::instance();
		$this->_log         = new WP_Twitch_Pack_Logger();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		$this->_settings = wp_parse_args( get_option( 'wp-twitch-pack-settings' ), array(
			'client_id'      => '',
			'client_secret'  => '',
			'code'           => '',
			'token'          => '',
		) );
	}

	/**
	 * Adds the Settings menu item
	 */
	public function admin_menu() {
		add_options_page(
			__( 'Twitch Pack', 'wp-twitch-pack' ),
			__( 'Twitch Pack', 'wp-twitch-pack' ),
			'manage_options',
			'wp-twitch-pack',
			array( $this, 'settings_page_contents' )
		);
	}
	/**
	 * Prints the Settings page
	 */
	public function settings_page_contents() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'WP Twitch Pack Settings', 'wp-twitch-pack' ); ?></h2>

			<form method="post" action="options.php">
				<?php wp_nonce_field( 'update-options' ); ?>
				<?php settings_fields( 'wp-twitch-pack-settings' ); ?>
				<?php do_settings_sections( 'wp-twitch-pack-settings' ); ?>

				<?php
				if ( isset( $this->_settings['channel'] ) ) :
				?>
				<h3><?php esc_html_e( 'Channel Info', 'wp-twitch-pack' ); ?></h3>

				<p><?php esc_html_e( 'Here are the info of the Twitch channel connected to this site.', 'wp-twitch-pack' ); ?></p>
				<dl>
					<dt><strong><?php esc_html_e( 'Name', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo esc_html( $this->_settings['channel']->display_name ); ?> &lt;<?php echo esc_html( $this->_settings['channel']->email ); ?>&gt;</dd>

					<dt><strong><?php esc_html_e( 'URL', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><a href="<?php echo esc_url( $this->_settings['channel']->url ); ?>" target="_blank"><?php echo esc_url( $this->_settings['channel']->url ); ?></a></dd>

					<dt><strong><?php esc_html_e( 'ID', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo absint( $this->_settings['channel']->_id ); ?></dd>

					<dt><strong><?php esc_html_e( 'Twitch Partner', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo ( true === (bool) $this->_settings['channel']->partner ? esc_html__( 'Yes', 'wp-twitch-pack' ) : esc_html__( 'No', 'wp-twitch-pack' ) ); ?></dd>
				</dl>

				<h3><?php esc_html_e( 'Channel Stats', 'wp-twitch-pack' ); ?></h3>

				<p><?php esc_html_e( 'Here are the stats of the Twitch channel connected to this site.', 'wp-twitch-pack' ); ?></p>

				<dl>
					<dt><strong><?php esc_html_e( 'Total Followers', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo number_format( absint( $this->_settings['channel']->followers ) ); ?></dd>

					<dt><strong><?php esc_html_e( 'Followers From Site', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo number_format( absint( get_option( 'wp-twitch-pack-followers-from-site' ) ) ); ?></dd>

					<dt><strong><?php esc_html_e( 'Views', 'wp-twitch-pack' ); ?></strong></dt>
					<dd><?php echo number_format( absint( $this->_settings['channel']->views ) ); ?></dd>
				</dl>
				<?php
				endif;
				?>

				<p class="submit">
					<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-twitch-pack' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers all the settigns
	 */
	public function admin_init() {
		// Delete options if disconnecting.
		if ( isset( $_GET['action'] ) ) {
			switch ( sanitize_key( $_GET['action'] ) ) {
				case 'disconnect_client':
					$this->_disconnect_twitch();
					break;
				case 'delete_cache':
					$this->delete_cache();
					break;
				case 'update_channel_stats':
					$this->_update_twitch_channel_data( true );
					break;
			}
		}

		// Update auth code before to request the token.
		if ( isset( $_GET['code'] ) && empty( $this->_settings['code'] ) ) {
			$this->_settings['code'] = sanitize_key( $_GET['code'] );
			update_option( 'wp-twitch-pack-settings', $this->_settings );
		}

		// If we have the code but not the token, get it.
		if ( ! empty( $this->_settings['code'] ) && empty( $this->_settings['token'] ) ) {
			$access_token = $this->_http_client->get_oauth_token( $this->_settings['code'] );

			if ( false !== $access_token ) {
				$this->_settings['token'] = sanitize_key( $access_token );
				update_option( 'wp-twitch-pack-settings', $this->_settings );
			}
		}

		// General Settings.
		register_setting(
			'wp-twitch-pack-settings',
			'wp-twitch-pack-settings',
			array( $this, 'validate_settings' )
		);

		// Options.
		add_settings_section( 'auth', __( 'Authentication', 'wp-twitch-pack' ), array( $this, 'print_auth_section' ), 'wp-twitch-pack-settings' );

		add_settings_field(
			'client_id',
			__( 'Client ID', 'wp-twitch-pack' ),
			array( $this, 'settings_field_client_id' ),
			'wp-twitch-pack-settings',
			'auth'
		);

		add_settings_field(
			'client_secret',
			__( 'Client Secret', 'wp-twitch-pack' ),
			array( $this, 'settings_field_client_secret' ),
			'wp-twitch-pack-settings',
			'auth'
		);

		if ( ! empty( $this->_settings['code'] ) || ! empty( $this->_settings['token'] ) ) {
			$this->_update_twitch_channel_data();

			add_settings_field( 'update_channel_stats', __( 'Update Channel Stats', 'wp-twitch-pack' ), array( $this, 'settings_field_update_channel_stats' ), 'wp-twitch-pack-settings', 'auth' );
			add_settings_field( 'delete_cache', __( 'Delete Cache', 'wp-twitch-pack' ), array( $this, 'settings_field_delete_cache' ), 'wp-twitch-pack-settings', 'auth' );
			add_settings_field( 'disconnect_client', __( 'Disconnect', 'wp-twitch-pack' ), array( $this, 'settings_field_disconnect_client' ), 'wp-twitch-pack-settings', 'auth' );
		}

		if ( ! empty( $this->_settings['client_id'] ) && ! empty( $this->_settings['client_secret'] ) && empty( $this->_settings['code'] ) ) {
			add_settings_field( 'authorize_app', __( 'Authorize', 'wp-twitch-pack' ), array( $this, 'settings_field_authorize_app' ), 'wp-twitch-pack-settings', 'auth' );
		}
	}

	/**
	 * Prints some info for the settings section 'auth'.
	 */
	public function print_auth_section() {
		echo '<p>' . sprintf( esc_html( '%1$sRegister a new app on Twitch%2$s to get the Client ID and Secret to use here for the authentication.', 'wp-twitch-pack' ), '<a href="https://www.twitch.tv/kraken/oauth2/clients/new">', '</a>' ) . '</p>';
	}

	/**
	 * Prints the Create App button in the settings
	 */
	public function settings_field_authorize_app() {
		$oauth_url = $this->_http_client->generate_oauth_url( 'channel_read' );
		?>
		<a href="<?php echo esc_url( $oauth_url ); ?>" class="button button-primary"><?php esc_html_e( 'Authorize Twitch.tv App', 'wp-twitch-pack' ); ?></a>
		<p class="description"><?php esc_html_e( 'Authorize this site to use your Twitch.tv App.', 'wp-twitch-pack' ); ?></p>
		<?php
	}

	/**
	 * Prints the Client ID field in the settings
	 */
	public function settings_field_client_id() {
		?>
		<input type="text" class="regular-text" id="website" name="wp-twitch-pack-settings[client_id]" value="<?php echo esc_attr( $this->_settings['client_id'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Write your WordPress.com App client ID here.', 'wp-twitch-pack' ); ?></p>
		<?php
	}

	/**
	 * Prints the Client Secret field in the settings
	 */
	public function settings_field_client_secret() {
		?>
		<input type="password" class="regular-text" id="website" name="wp-twitch-pack-settings[client_secret]" value="<?php echo esc_attr( $this->_settings['client_secret'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Write your WordPress.com App client secret here.', 'wp-twitch-pack' ); ?></p>
		<?php
	}

	/**
	 * Prints the Update Channel Stats button in the settings.
	 */
	public function settings_field_update_channel_stats() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-twitch-pack&action=update_channel_stats' ) ) ?>" class="button"><?php esc_html_e( 'Update Channel Stats', 'wp-twitch-pack' ); ?></a>
		<p class="description"><?php esc_html_e( 'Update the channel stats below by clicking on this button.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Delete Cache button in the settings.
	 */
	public function settings_field_delete_cache() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-twitch-pack&action=delete_cache' ) ) ?>" class="button"><?php esc_html_e( 'Delete Cached Contents', 'wp-twitch-pack' ); ?></a>
		<p class="description"><?php esc_html_e( 'WP Twitch Pack saves some content in the cache to save resources. Delete the cache by clicking on this button.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Disconnect button in the settings.
	 */
	public function settings_field_disconnect_client() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-twitch-pack&action=disconnect_client' ) ) ?>" class="button primary"><?php esc_html_e( 'Disconnect & Remove Authorization for Twitch.tv', 'wp-twitch-pack' ); ?></a>
		<p class="description"><?php esc_html_e( 'Disconnect this site from Twitch.tv.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Validates and escapes the settings
	 *
	 * @param  array $settings The settings array before to save them.
	 * @return array
	 */
	public function validate_settings( $settings ) {
		if ( isset( $settings['client_id'] ) ) {
			$settings['client_id'] = esc_html( $settings['client_id'] );
		}

		if ( isset( $settings['client_secret'] ) ) {
			$settings['client_secret'] = esc_html( $settings['client_secret'] );
		}

		return $settings;
	}

	/**
	 * Deletes the cached content.
	 */
	public function delete_cache() {
		wp_cache_delete( 'wp-twitch-pack-stream' );
		wp_cache_delete( 'wp-twitch-pack-videos-archive' );
		wp_cache_delete( 'wp-twitch-pack-videos-highlight' );

		$this->_log->info( esc_html__( 'Cache deleted.', 'wp-twitch-pack' ) );
	}

	/**
	 * Deletes connection options when disconnecting Twitch.tv.
	 *
	 * @access private
	 */
	private function _disconnect_twitch() {
		$this->_settings['code']    = '';
		$this->_settings['token']   = '';
		$this->_settings['channel'] = null;

		update_option( 'wp-twitch-pack-settings', $this->_settings );

		$this->_log->info( esc_html__( 'Removed authorization for Twitch.', 'wp-twitch-pack' ) );
	}

	/**
	 * Updates the data for the channel currently connected to the site.
	 *
	 * @access private
	 */
	private function _update_twitch_channel_data( $force = false ) {
		if ( true === $force || ( empty( $this->_settings['channel'] ) || ! isset( $this->_settings['channel_last_update'] ) || ( time() > $this->_settings['channel_last_update'] + ( HOUR_IN_SECONDS * 6 ) ) ) ) {
			$this->_settings['channel']             = $this->_http_client->get_channel();
			$this->_settings['channel_last_update'] = time();
			update_option( 'wp-twitch-pack-settings', $this->_settings );

			$this->_log->info( esc_html__( 'Updated channel stats.', 'wp-twitch-pack' ) );
		}
	}
}

new WP_Twitch_Pack_Admin();
