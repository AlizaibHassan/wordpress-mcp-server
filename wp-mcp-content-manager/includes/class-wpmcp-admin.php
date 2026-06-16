<?php
/**
 * Admin settings screen: shows the MCP endpoint URL and API key, lets the
 * admin rotate the key, and gives copy-paste connection instructions.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings UI under Settings → WP MCP.
 */
class WPMCP_Admin {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( WPMCP_PLUGIN_FILE ),
			array( $this, 'settings_link' )
		);
	}

	/**
	 * Add the settings menu item.
	 */
	public function add_menu() {
		add_options_page(
			__( 'WP MCP Server By AZ', 'wp-mcp-content-manager' ),
			__( 'WP MCP', 'wp-mcp-content-manager' ),
			'manage_options',
			'wpmcp',
			array( $this, 'render' )
		);
	}

	/**
	 * Add a Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function settings_link( $links ) {
		$url      = admin_url( 'options-general.php?page=wpmcp' );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'wp-mcp-content-manager' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Handle settings save, key rotation and log clearing.
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['wpmcp_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'wpmcp_settings' );

		$action = sanitize_key( wp_unslash( $_POST['wpmcp_action'] ) );

		if ( 'rotate' === $action ) {
			WPMCP_Auth::rotate_key();
			$this->notice( __( 'A new API key has been generated — copy it from the API Key box above now; it will not be shown again.', 'wp-mcp-content-manager' ) );
		} elseif ( 'clear_log' === $action ) {
			WPMCP_Logger::clear();
			$this->notice( __( 'Audit log cleared.', 'wp-mcp-content-manager' ) );
		} elseif ( 'save_settings' === $action ) {
			WPMCP_Settings::update(
				array(
					'enabled'       => isset( $_POST['enabled'] ),
					'allow_write'   => isset( $_POST['allow_write'] ),
					'allow_delete'  => isset( $_POST['allow_delete'] ),
					'oauth_enabled' => isset( $_POST['oauth_enabled'] ),
					'audit_log'     => isset( $_POST['audit_log'] ),
					'rate_limit'    => isset( $_POST['rate_limit'] ) ? (int) $_POST['rate_limit'] : 60,
				)
			);
			$this->notice( __( 'Settings saved.', 'wp-mcp-content-manager' ) );
		}
	}

	/**
	 * Queue an admin notice.
	 *
	 * @param string $message Message text.
	 */
	private function notice( $message ) {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render() {
		$endpoint = rest_url( WPMCP_REST_NAMESPACE . '/mcp' );
		$reveal   = WPMCP_Auth::peek_reveal(); // Full key, shown only once after generation/rotation.
		$hint     = WPMCP_Auth::get_hint();
		$acf      = function_exists( 'get_field' );

		$key_for_cmd     = $reveal ? $reveal : 'YOUR_API_KEY';
		$claude_code_cmd = sprintf(
			'claude mcp add --transport http wordpress %s --header "Authorization: Bearer %s"',
			$endpoint,
			$key_for_cmd
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP MCP Server By AZ', 'wp-mcp-content-manager' ); ?></h1>
			<p><?php esc_html_e( 'Connect Claude to this WordPress site to read and update pages, posts and ACF fields over the Model Context Protocol.', 'wp-mcp-content-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'MCP Endpoint URL', 'wp-mcp-content-manager' ); ?></th>
					<td><code style="user-select:all;"><?php echo esc_html( $endpoint ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API Key', 'wp-mcp-content-manager' ); ?></th>
					<td>
						<?php if ( $reveal ) : ?>
							<code style="user-select:all;font-size:14px;"><?php echo esc_html( $reveal ); ?></code>
							<p class="description" style="color:#b32d2e;font-weight:600;">
								⚠️ <?php esc_html_e( 'Copy this key now — for security it is stored only as a hash and will NOT be shown again. If you lose it, click "Rotate API Key" to generate a new one.', 'wp-mcp-content-manager' ); ?>
							</p>
						<?php else : ?>
							<code style="user-select:all;"><?php echo esc_html( $hint ); ?></code>
							<p class="description"><?php esc_html_e( 'For security, only a masked preview is shown. The full key is stored as a SHA-256 hash and revealed once at generation. Click "Rotate API Key" to generate a new key you can copy.', 'wp-mcp-content-manager' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ACF detected', 'wp-mcp-content-manager' ); ?></th>
					<td><?php echo $acf ? '✅ ' . esc_html__( 'Yes — ACF fields are editable.', 'wp-mcp-content-manager' ) : '⚠️ ' . esc_html__( 'No — install Advanced Custom Fields to edit ACF data.', 'wp-mcp-content-manager' ); ?></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Connect with Claude Code', 'wp-mcp-content-manager' ); ?></h2>
			<p><?php esc_html_e( 'Run this command in your terminal:', 'wp-mcp-content-manager' ); ?></p>
			<textarea readonly rows="3" style="width:100%;font-family:monospace;" onclick="this.select();"><?php echo esc_textarea( $claude_code_cmd ); ?></textarea>

			<h2><?php esc_html_e( 'Connect with the Claude desktop/web app (custom connector)', 'wp-mcp-content-manager' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Open Claude → Settings → Connectors → Add custom connector.', 'wp-mcp-content-manager' ); ?></li>
				<li><?php printf( esc_html__( 'Set the URL to: %s', 'wp-mcp-content-manager' ), '<code>' . esc_html( $endpoint ) . '</code>' ); ?></li>
				<li><?php esc_html_e( 'Add an HTTP header named "Authorization" with the value "Bearer <your-key>".', 'wp-mcp-content-manager' ); ?></li>
			</ol>

			<form method="post">
				<?php wp_nonce_field( 'wpmcp_settings' ); ?>
				<input type="hidden" name="wpmcp_action" value="rotate" />
				<p>
					<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Generate a new API key? Existing connections will stop working until updated.', 'wp-mcp-content-manager' ) ); ?>');">
						<?php esc_html_e( 'Rotate API Key', 'wp-mcp-content-manager' ); ?>
					</button>
				</p>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Settings & Safety Controls', 'wp-mcp-content-manager' ); ?></h2>
			<?php $s = WPMCP_Settings::all(); ?>
			<form method="post">
				<?php wp_nonce_field( 'wpmcp_settings' ); ?>
				<input type="hidden" name="wpmcp_action" value="save_settings" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'MCP server enabled', 'wp-mcp-content-manager' ); ?></th>
						<td><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'] ); ?> /> <?php esc_html_e( 'Accept MCP requests', 'wp-mcp-content-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow write operations', 'wp-mcp-content-manager' ); ?></th>
						<td><label><input type="checkbox" name="allow_write" <?php checked( $s['allow_write'] ); ?> /> <?php esc_html_e( 'Permit create/update tools (content, ACF, media, SEO)', 'wp-mcp-content-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow delete operations', 'wp-mcp-content-manager' ); ?></th>
						<td><label><input type="checkbox" name="allow_delete" <?php checked( $s['allow_delete'] ); ?> /> <?php esc_html_e( 'Permit deleting/trashing content (off by default)', 'wp-mcp-content-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OAuth connector', 'wp-mcp-content-manager' ); ?></th>
						<td><label><input type="checkbox" name="oauth_enabled" <?php checked( $s['oauth_enabled'] ); ?> /> <?php esc_html_e( 'Allow one-click OAuth connection from the Claude app', 'wp-mcp-content-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Audit logging', 'wp-mcp-content-manager' ); ?></th>
						<td><label><input type="checkbox" name="audit_log" <?php checked( $s['audit_log'] ); ?> /> <?php esc_html_e( 'Record tool calls (secrets redacted)', 'wp-mcp-content-manager' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate limit', 'wp-mcp-content-manager' ); ?></th>
						<td>
							<input type="number" name="rate_limit" min="0" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" class="small-text" />
							<?php esc_html_e( 'requests per minute per IP (0 = unlimited)', 'wp-mcp-content-manager' ); ?>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-mcp-content-manager' ); ?></button></p>
			</form>

			<hr />
			<h2><?php esc_html_e( 'One-click OAuth connection (Claude app)', 'wp-mcp-content-manager' ); ?></h2>
			<?php if ( $s['oauth_enabled'] ) : ?>
				<p><?php esc_html_e( 'In the Claude desktop/web app, add a custom connector with the URL below. Claude registers itself automatically and will ask you to sign in to WordPress and approve access — no API key needed.', 'wp-mcp-content-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Connector URL', 'wp-mcp-content-manager' ); ?></th>
						<td><code style="user-select:all;"><?php echo esc_html( $endpoint ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Discovery', 'wp-mcp-content-manager' ); ?></th>
						<td><code style="user-select:all;"><?php echo esc_html( home_url( '/.well-known/oauth-protected-resource' ) ); ?></code></td>
					</tr>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'OAuth connector is disabled. Enable it above to allow one-click connection from the Claude app.', 'wp-mcp-content-manager' ); ?></p>
			<?php endif; ?>

			<hr />
			<h2><?php esc_html_e( 'Recent Activity', 'wp-mcp-content-manager' ); ?></h2>
			<?php $log = WPMCP_Logger::read( 50 ); ?>
			<?php if ( empty( $log ) ) : ?>
				<p><?php esc_html_e( 'No activity recorded yet.', 'wp-mcp-content-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time (UTC)', 'wp-mcp-content-manager' ); ?></th>
							<th><?php esc_html_e( 'Tool', 'wp-mcp-content-manager' ); ?></th>
							<th><?php esc_html_e( 'Actor', 'wp-mcp-content-manager' ); ?></th>
							<th><?php esc_html_e( 'IP', 'wp-mcp-content-manager' ); ?></th>
							<th><?php esc_html_e( 'Outcome', 'wp-mcp-content-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['time'] ); ?></td>
								<td><code><?php echo esc_html( $row['tool'] ); ?></code></td>
								<td><?php echo esc_html( $row['actor'] ); ?></td>
								<td><?php echo esc_html( $row['ip'] ); ?></td>
								<td><?php echo esc_html( $row['outcome'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" style="margin-top:12px;">
					<?php wp_nonce_field( 'wpmcp_settings' ); ?>
					<input type="hidden" name="wpmcp_action" value="clear_log" />
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear Log', 'wp-mcp-content-manager' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
