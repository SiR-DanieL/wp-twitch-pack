<?php
/**
 * WP Twitch Pack logger class. Useful for debugging and logging purposes.
 *
 * @package WP Twitch Pack
 * @author  Nicola Mustone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Provides logging capabilities for debugging purposes.
 */
class WP_Twitch_Pack_Logger {
	/**
	 * Constructor for the logger.
	 */
	public function __construct() {
		$this->_log_file = WP_CONTENT_DIR . '/wp-twitch-pack.log';
		$this->_fp       = $this->_open();
	}

	/**
	 * Destroys the log file handler.
	 */
	public function __destruct() {
		if ( false !== $this->_fp ) {
			fclose( $this->_fp );
		}
	}

	/**
	 * Sets up the file handler and opens the stream.
	 *
	 * @return object
	 * @access private
	 */
	private function _open() {
		$fp = fopen( $this->_log_file, 'a' );

		if ( ! $fp ) {
			return false;
		}

		return $fp;
	}

	/**
	 * Logs a new message in the log file.
	 *
	 * @param  string $level   The level of message to log.
	 * @param  string $message The message to log.
	 * @param  string $context The context of the message.
	 */
	public function log( $level, $message, $context ) {
		$message = $this->_format_message( $level, $message, $context );
		$this->write( $message );
	}

	/**
	 * Writes a message in the log.
	 *
	 * @param  string $message The message to write.
	 */
	public function write( $message ) {
		if ( false !== $this->_fp ) {
			fwrite( $this->_fp, $message );
		}
	}

	/**
	 * Alias for WP_Twitch_Pack_Logger::log with level 'error'.
	 *
	 * @param  string $message The message to write.
	 * @param  string $context The context of the message.
	 * @see WP_Twitch_Pack_Logger::log
	 */
	public function error( $message, $context = '' ) {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Alias for WP_Twitch_Pack_Logger::log with level 'warning'.
	 *
	 * @param  string $message The message to write.
	 * @param  string $context The context of the message.
	 * @see WP_Twitch_Pack_Logger::log
	 */
	public function warning( $message, $context = '' ) {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Alias for WP_Twitch_Pack_Logger::log with level 'info'.
	 *
	 * @param  string $message The message to write.
	 * @param  string $context The context of the message.
	 * @see WP_Twitch_Pack_Logger::log
	 */
	public function info( $message, $context = '' ) {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Alias for WP_Twitch_Pack_Logger::log with level 'debug'.
	 *
	 * @param  string $message The message to write.
	 * @param  string $context The context of the message.
	 * @see WP_Twitch_Pack_Logger::log
	 */
	public function debug( $message, $context = '' ) {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Formats a message before to write it into the log file.
	 *
	 * @param  string $level   The level of the message.
	 * @param  string $message The message to write.
	 * @param  string $context The context of the message.
	 * @return string          The formatted message.
	 * @access private
	 */
	private function _format_message( $level, $message, $context ) {
		$message = '[' . strtoupper( $level ) . ' - ' . date( 'Y-m-d H:i:s', time() ) . '] ' . $message . ' ' . $context;
		return $message . PHP_EOL;
	}
}
