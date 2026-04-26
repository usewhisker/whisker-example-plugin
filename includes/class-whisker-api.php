<?php
/**
 * Whisker API client.
 *
 * @package WhiskerExamplePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles HTTP calls to Whisker License API.
 */
class Whisker_API {
	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 15;

	/**
	 * Max retry count for transient network failures.
	 *
	 * @var int
	 */
	private $max_retries = 1;

	/**
	 * Activate a license for a site.
	 *
	 * @param string $license_key License key.
	 * @param string $site_url    Site URL.
	 * @return array<string,mixed>
	 */
	public function activate( $license_key, $site_url ) {
		return $this->request( 'activate', $license_key, $site_url );
	}

	/**
	 * Validate a license for a site.
	 *
	 * @param string $license_key License key.
	 * @param string $site_url    Site URL.
	 * @return array<string,mixed>
	 */
	public function validate( $license_key, $site_url ) {
		return $this->request( 'validate', $license_key, $site_url );
	}

	/**
	 * Deactivate a license for a site.
	 *
	 * @param string $license_key License key.
	 * @param string $site_url    Site URL.
	 * @return array<string,mixed>
	 */
	public function deactivate( $license_key, $site_url ) {
		return $this->request( 'deactivate', $license_key, $site_url );
	}

	/**
	 * Internal request runner.
	 *
	 * @param string $path        Endpoint path.
	 * @param string $license_key License key.
	 * @param string $site_url    Site URL.
	 * @return array<string,mixed>
	 */
	private function request( $path, $license_key, $site_url ) {
		$url = trailingslashit( WHISKER_API_BASE ) . ltrim( $path, '/' );
		$site = $this->normalize_site_url( (string) $site_url );

		$payload = array(
			'product_key' => WHISKER_PRODUCT_KEY,
			'license_key' => (string) $license_key,
			'site_url'    => $site,
		);

		$args = array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			for ( $attempt = 0; $attempt < $this->max_retries; $attempt++ ) {
				$response = wp_remote_post( $url, $args );
				if ( ! is_wp_error( $response ) ) {
					break;
				}
			}
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'network_error',
					'message' => $response->get_error_message(),
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'invalid_response',
					'message' => 'Whisker API returned an unexpected response format.',
				),
			);
		}

		if ( $status_code >= 400 ) {
			return array(
				'success' => false,
				'error'   => array(
					'code'    => isset( $decoded['error']['code'] ) ? sanitize_key( $decoded['error']['code'] ) : 'request_failed',
					'message' => isset( $decoded['error']['message'] ) ? sanitize_text_field( $decoded['error']['message'] ) : 'Request failed.',
				),
			);
		}

		return $decoded;
	}

	/**
	 * Normalize site URL for local environments.
	 *
	 * @param string $site_url Site URL.
	 * @return string
	 */
	private function normalize_site_url( $site_url ) {
		$site = strtolower( trim( $site_url ) );
		if ( false !== strpos( $site, 'localhost' ) ) {
			return 'http://localhost';
		}

		return $site_url;
	}
}
