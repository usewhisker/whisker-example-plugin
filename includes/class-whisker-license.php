<?php
/**
 * Whisker license manager.
 *
 * @package WhiskerExamplePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles license storage, activation, validation, and status state.
 */
class Whisker_License {
	/**
	 * Option key for license key.
	 *
	 * @var string
	 */
	private $license_key_option = 'whisker_example_plugin_license_key';

	/**
	 * Option key for persisted status.
	 *
	 * @var string
	 */
	private $license_status_option = 'whisker_example_plugin_license_status';

	/**
	 * Transient key for validation cache.
	 *
	 * @var string
	 */
	private $validation_transient = 'whisker_example_plugin_license_validation_cache';

	/**
	 * API client.
	 *
	 * @var Whisker_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Whisker_API $api API client.
	 */
	public function __construct( Whisker_API $api ) {
		$this->api = $api;
	}

	/**
	 * Get stored license key.
	 *
	 * @return string
	 */
	public function get_license_key() {
		$value = get_option( $this->license_key_option, '' );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Set license key and clear stale cache.
	 *
	 * @param string $license_key License key.
	 * @return void
	 */
	public function set_license_key( $license_key ) {
		$key = sanitize_text_field( (string) $license_key );
		update_option( $this->license_key_option, $key );
		delete_transient( $this->validation_transient );
	}

	/**
	 * Activate stored license key.
	 *
	 * @return array<string,mixed>
	 */
	public function activate() {
		$license_key = $this->get_license_key();
		if ( '' === $license_key ) {
			return $this->store_and_return_error( 'invalid_key', 'Please enter a license key before activation.' );
		}

		$result = $this->api->activate( $license_key, $this->get_site_url() );

		return $this->store_result( $result );
	}

	/**
	 * Validate stored license key with optional force refresh.
	 *
	 * @param bool $force Force remote check.
	 * @return array<string,mixed>
	 */
	public function validate( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( $this->validation_transient );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$license_key = $this->get_license_key();
		if ( '' === $license_key ) {
			return $this->store_and_return_error( 'invalid_key', 'No license key configured.' );
		}

		$result = $this->api->validate( $license_key, $this->get_site_url() );
		$stored = $this->store_result( $result );

		set_transient( $this->validation_transient, $stored, 12 * HOUR_IN_SECONDS );

		return $stored;
	}

	/**
	 * Determine if license is currently active.
	 *
	 * @return bool
	 */
	public function is_active() {
		$status = $this->get_status_data();

		if ( $this->is_status_stale( $status['last_checked'] ) ) {
			$license_key = $this->get_license_key();
			if ( '' !== $license_key ) {
				$result = $this->api->validate( $license_key, $this->get_site_url() );
				if ( is_array( $result ) && ! empty( $result['success'] ) ) {
					$status = $this->store_result( $result );
					set_transient( $this->validation_transient, $status, 12 * HOUR_IN_SECONDS );
				}
			}
		}

		return ! empty( $status['valid'] ) && in_array( $status['status'], array( 'active', 'trialing' ), true );
	}

	/**
	 * Get status array for UI display.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status_data() {
		$default = array(
			'valid'        => false,
			'status'       => 'inactive',
			'expires_at'   => null,
			'last_checked' => null,
			'error_code'   => null,
			'error_msg'    => null,
		);

		$value = get_option( $this->license_status_option, $default );
		return is_array( $value ) ? wp_parse_args( $value, $default ) : $default;
	}

	/**
	 * Persist API result and normalize into status state.
	 *
	 * @param array<string,mixed> $result API result.
	 * @return array<string,mixed>
	 */
	private function store_result( $result ) {
		$current = $this->get_status_data();
		$now     = current_time( 'mysql' );

		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			$error_code = isset( $result['error']['code'] ) ? sanitize_key( $result['error']['code'] ) : 'request_failed';
			$error_msg  = isset( $result['error']['message'] ) ? sanitize_text_field( $result['error']['message'] ) : 'Request failed.';

			$updated = array(
				'valid'        => false,
				'status'       => 'inactive',
				'expires_at'   => $current['expires_at'],
				'last_checked' => $now,
				'error_code'   => $error_code,
				'error_msg'    => $error_msg,
			);

			update_option( $this->license_status_option, $updated );
			return $updated;
		}

		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		$updated = array(
			'valid'        => ! empty( $data['valid'] ),
			'status'       => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'inactive',
			'expires_at'   => isset( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
			'last_checked' => $now,
			'error_code'   => null,
			'error_msg'    => null,
		);

		update_option( $this->license_status_option, $updated );
		return $updated;
	}

	/**
	 * Convenience helper for direct local errors.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return array<string,mixed>
	 */
	private function store_and_return_error( $code, $message ) {
		$result = array(
			'success' => false,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);

		return $this->store_result( $result );
	}

	/**
	 * Check if status is older than cache window.
	 *
	 * @param string|null $last_checked Last checked value.
	 * @return bool
	 */
	private function is_status_stale( $last_checked ) {
		if ( empty( $last_checked ) ) {
			return true;
		}

		$last_timestamp = strtotime( (string) $last_checked );
		if ( false === $last_timestamp ) {
			return true;
		}

		return ( time() - $last_timestamp ) > ( 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Normalize site URL for localhost development.
	 *
	 * @return string
	 */
	private function get_site_url() {
		$site_url = home_url();
		if ( false !== strpos( $site_url, 'localhost' ) ) {
			return 'http://localhost';
		}

		return $site_url;
	}
}
