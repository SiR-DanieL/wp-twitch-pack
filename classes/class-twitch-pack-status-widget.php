<?php
/**
 * The widget Stream Status.
 *
 * @package WP Twitch Pack
 * @author  Nicola Mustone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class of the widget Stream Status.
 */
class WP_Twitch_Pack_Status_Widget extends WP_Widget {
	/**
	 * Sets up the widget data.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'   => 'wp-twitch-pack-status-widget',
			'description' => esc_html__( 'Shows the status of the connected Twitch channel.', 'wp-twitch-pack' ),
		);

		parent::__construct( 'wp_twitch_pack_status', esc_html__( 'Stream Status', 'wp-twitch-pack' ), $widget_ops );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title'];
		}

		echo do_shortcode( '[twitch_stream_status wrap="' . $instance['wrap'] . '" print_username="' . $instance['print_username'] . '" print_game="' . $instance['print_game'] . '"]' );

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title          = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Stream Status', 'wp-twitch-pack' );
		$wrap           = ! empty( $instance['wrap'] ) ? $instance['wrap'] : 'div';
		$print_username = ! empty( $instance['print_username'] ) ? $instance['print_username'] : 'yes';
		$print_game     = ! empty( $instance['print_game'] ) ? $instance['print_game'] : 'no';

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'wp-twitch-pack' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'wrap' ) ); ?>"><?php esc_attr_e( 'Wrapper:', 'wp-twitch-pack' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'wrap' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'wrap' ) ); ?>" type="text" value="<?php echo esc_attr( $wrap ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'print_username' ) ); ?>"><?php esc_attr_e( 'Show username?', 'wp-twitch-pack' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'print_username' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'print_username' ) ); ?>">
				<option value="yes" <?php selected( 'yes', esc_attr( $print_username ) ); ?>><?php esc_html_e( 'Yes', 'wp-twitch-pack' ); ?></option>
				<option value="no" <?php selected( 'no', esc_attr( $print_username ) ); ?>><?php esc_html_e( 'No', 'wp-twitch-pack' ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'print_game' ) ); ?>"><?php esc_attr_e( 'Show game?', 'wp-twitch-pack' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'print_game' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'print_game' ) ); ?>">
				<option value="yes" <?php selected( 'yes', esc_attr( $print_game ) ); ?>><?php esc_html_e( 'Yes', 'wp-twitch-pack' ); ?></option>
				<option value="no" <?php selected( 'no', esc_attr( $print_game ) ); ?>><?php esc_html_e( 'No', 'wp-twitch-pack' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                   = array();
		$instance['title']          = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['wrap']           = ( ! empty( $new_instance['wrap'] ) ) ? strip_tags( $new_instance['wrap'] ) : '';
		$instance['print_username'] = ( ! empty( $new_instance['print_username'] ) ) ? strip_tags( $new_instance['print_username'] ) : '';
		$instance['print_game']     = ( ! empty( $new_instance['print_game'] ) ) ? strip_tags( $new_instance['print_game'] ) : '';

		return $instance;
	}

}
