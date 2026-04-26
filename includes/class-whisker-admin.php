<?php
/**
 * Whisker admin UI.
 *
 * @package WhiskerExamplePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin settings page and actions.
 */
class Whisker_Admin {
	/**
	 * License manager.
	 *
	 * @var Whisker_License
	 */
	private $license;

	/**
	 * Constructor.
	 *
	 * @param Whisker_License $license License manager.
	 */
	public function __construct( Whisker_License $license ) {
		$this->license = $license;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_whisker_example_plugin_license_save', array( $this, 'handle_save' ) );
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Whisker License', 'whisker-example-plugin' ),
			__( 'Whisker License', 'whisker-example-plugin' ),
			'manage_options',
			'whisker-example-plugin-license',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_whisker-example-plugin-license' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'whisker-example-plugin-admin',
			WHISKER_EXAMPLE_PLUGIN_URL . 'assets/admin.css',
			array(),
			WHISKER_EXAMPLE_PLUGIN_VERSION
		);
	}

	/**
	 * Process settings form submit.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'whisker-example-plugin' ) );
		}

		check_admin_referer( 'whisker_example_plugin_license_action', 'whisker_example_plugin_nonce' );

		$license_key = isset( $_POST['whisker_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['whisker_license_key'] ) ) : '';
		$action      = isset( $_POST['whisker_license_action'] ) ? sanitize_key( wp_unslash( $_POST['whisker_license_action'] ) ) : 'activate';

		$this->license->set_license_key( $license_key );

		$result = array();
		if ( 'deactivate' === $action ) {
			// Deactivation request can fail silently in remote API, we still clear local cache and revalidate.
			$api = new Whisker_API();
			$api->deactivate( $license_key, home_url() );
			$result = $this->license->validate( true );
		} elseif ( 'validate' === $action ) {
			$result = $this->license->validate( true );
		} else {
			$result = $this->license->activate();
		}

		$notice_payload = array(
			'type'    => 'success',
			'code'    => 'success',
			'message' => __( 'License activated successfully.', 'whisker-example-plugin' ),
		);

		if ( 'deactivate' === $action ) {
			$notice_payload['message'] = __( 'License deactivated.', 'whisker-example-plugin' );
		} elseif ( 'validate' === $action ) {
			$notice_payload['message'] = __( 'License validated.', 'whisker-example-plugin' );
		}

		if ( ! empty( $result['error_code'] ) ) {
			$notice_payload['type']    = 'error';
			$notice_payload['code']    = sanitize_key( $result['error_code'] );
			$notice_payload['message'] = $this->map_error_message( $result['error_code'], $result['error_msg'] );
		}
		set_transient( 'whisker_example_plugin_admin_notice', $notice_payload, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=whisker-example-plugin-license' ) );
		exit;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status      = $this->license->get_status_data();
		$license_key = $this->license->get_license_key();
		$notice      = get_transient( 'whisker_example_plugin_admin_notice' );
		delete_transient( 'whisker_example_plugin_admin_notice' );

		if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
			$type = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'error' : 'updated';
			add_settings_error( 'whisker_license', (string) $notice['code'], (string) $notice['message'], $type );
		}
		?>
		<div class="wrap whisker-license-wrap">
			<h1><?php echo esc_html__( 'Whisker License', 'whisker-example-plugin' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Connect your plugin to Whisker to enable premium features.', 'whisker-example-plugin' ); ?>
			</p>
			<?php settings_errors( 'whisker_license' ); ?>

			<div class="whisker-license-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'whisker_example_plugin_license_action', 'whisker_example_plugin_nonce' ); ?>
					<input type="hidden" name="action" value="whisker_example_plugin_license_save" />

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="whisker_license_key"><?php echo esc_html__( 'License Key', 'whisker-example-plugin' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="whisker_license_key"
										name="whisker_license_key"
										class="regular-text code"
										value="<?php echo esc_attr( $license_key ); ?>"
										placeholder="WHISKER-XXXX-XXXX-XXXX-XXXX"
									/>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="whisker-license-actions">
						<button type="submit" name="whisker_license_action" value="activate" class="button button-primary">
							<?php echo esc_html__( 'Activate License', 'whisker-example-plugin' ); ?>
						</button>
						<button type="submit" name="whisker_license_action" value="validate" class="button">
							<?php echo esc_html__( 'Validate Now', 'whisker-example-plugin' ); ?>
						</button>
						<button type="submit" name="whisker_license_action" value="deactivate" class="button">
							<?php echo esc_html__( 'Deactivate', 'whisker-example-plugin' ); ?>
						</button>
					</div>
				</form>

				<hr />

				<h2><?php echo esc_html__( 'License Status', 'whisker-example-plugin' ); ?></h2>
				<ul class="whisker-license-meta">
					<li><strong><?php echo esc_html__( 'Status:', 'whisker-example-plugin' ); ?></strong> <?php echo esc_html( $status['status'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Last checked:', 'whisker-example-plugin' ); ?></strong> <?php echo esc_html( $status['last_checked'] ? $status['last_checked'] : __( 'Never', 'whisker-example-plugin' ) ); ?></li>
					<li><strong><?php echo esc_html__( 'Expires at:', 'whisker-example-plugin' ); ?></strong> <?php echo esc_html( $status['expires_at'] ? $status['expires_at'] : __( 'N/A', 'whisker-example-plugin' ) ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Convert error codes into readable admin messages.
	 *
	 * @param string|null $error_code Error code.
	 * @param string|null $fallback   Fallback message.
	 * @return string
	 */
	private function map_error_message( $error_code, $fallback ) {
		switch ( $error_code ) {
			case 'invalid_key':
				return __( 'License key is invalid.', 'whisker-example-plugin' );
			case 'product_mismatch':
				return __( 'License key does not belong to this plugin product.', 'whisker-example-plugin' );
			case 'limit_reached':
				return __( 'Activation limit reached for this license.', 'whisker-example-plugin' );
			case 'network_error':
				return __( 'Could not reach Whisker API. Please try again.', 'whisker-example-plugin' );
			default:
				return $fallback ? $fallback : __( 'An unknown licensing error occurred.', 'whisker-example-plugin' );
		}
	}
}
