<?php
/**
 * Tools page that converts code blocks saved by 1.2.3 and earlier.
 *
 * @package WebberZone\Code_Block_Highlighting\Admin
 */

namespace WebberZone\Code_Block_Highlighting\Admin;

use WebberZone\Code_Block_Highlighting\Code_Block_Migrator;
use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Tools page class.
 *
 * @since 1.3.0
 */
class Tools_Page {

	/**
	 * Page slug.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	const SLUG = 'wzcbh-convert-code-blocks';

	/**
	 * Posts scanned per request, to stay inside PHP time limits.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	const POSTS_PER_REQUEST = 200;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Register the page under Tools.
	 *
	 * @since 1.3.0
	 */
	public function add_page(): void {
		$hook = add_management_page(
			__( 'Convert Code Blocks', 'webberzone-code-block-highlighting' ),
			__( 'Convert Code Blocks', 'webberzone-code-block-highlighting' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			Hook_Registry::add_action( 'load-' . $hook, array( $this, 'handle_request' ) );
		}
	}

	/**
	 * Run one batch, then redirect so a refresh does not repeat it.
	 *
	 * @since 1.3.0
	 */
	public function handle_request(): void {
		if ( ! isset( $_POST['wzcbh_migrate_action'] ) ) {
			return;
		}

		check_admin_referer( self::SLUG );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'webberzone-code-block-highlighting' ) );
		}

		$dry_run  = 'convert' !== sanitize_key( wp_unslash( $_POST['wzcbh_migrate_action'] ) );
		$after_id = isset( $_POST['wzcbh_after_id'] ) ? absint( $_POST['wzcbh_after_id'] ) : 0;
		$previous = 0 === $after_id ? array() : $this->get_result();

		$stats = Code_Block_Migrator::migrate(
			array(
				'dry_run'  => $dry_run,
				'after_id' => $after_id,
				'limit'    => self::POSTS_PER_REQUEST,
			)
		);

		foreach ( array( 'scanned', 'changed', 'converted', 'skipped' ) as $key ) {
			$stats[ $key ] += (int) ( $previous[ $key ] ?? 0 );
		}
		$stats['skipped_ids'] = array_merge( (array) ( $previous['skipped_ids'] ?? array() ), $stats['skipped_ids'] );
		$stats['dry_run']     = $dry_run;

		set_transient( $this->get_result_key(), $stats, HOUR_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'wzcbh_result', 1, menu_page_url( self::SLUG, false ) ) );
		exit;
	}

	/**
	 * Render the page.
	 *
	 * @since 1.3.0
	 */
	public function render(): void {
		$result = isset( $_GET['wzcbh_result'] ) ? $this->get_result() : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Convert Code Blocks', 'webberzone-code-block-highlighting' ); ?></h1>

			<p><?php esc_html_e( 'Code blocks last saved with WebberZone Code Block Highlighting 1.2.3 or earlier store the language, file name and display options in their HTML. The core Code block cannot validate that markup, so these blocks show "This block contains unexpected or invalid content" if the plugin is deactivated.', 'webberzone-code-block-highlighting' ); ?></p>
			<p><?php esc_html_e( 'Converting moves those options into the block settings, exactly as opening and updating each post in the editor does. Nothing else in the post changes, and how the blocks look on your site stays the same.', 'webberzone-code-block-highlighting' ); ?></p>
			<p><strong><?php esc_html_e( 'Post content is updated in place without creating revisions. Back up your database before converting.', 'webberzone-code-block-highlighting' ); ?></strong></p>
			<?php if ( is_multisite() ) : ?>
				<p><?php esc_html_e( 'On a multisite network, this converts the posts of the current site only.', 'webberzone-code-block-highlighting' ); ?></p>
			<?php endif; ?>

			<?php
			if ( $result ) {
				$this->render_result( $result );
			}
			?>

			<form method="post" action="<?php echo esc_url( menu_page_url( self::SLUG, false ) ); ?>">
				<?php wp_nonce_field( self::SLUG ); ?>
				<p>
					<button type="submit" name="wzcbh_migrate_action" value="check" class="button"><?php esc_html_e( 'Check posts', 'webberzone-code-block-highlighting' ); ?></button>
					<button type="submit" name="wzcbh_migrate_action" value="convert" class="button button-primary"><?php esc_html_e( 'Convert code blocks', 'webberzone-code-block-highlighting' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the outcome of the last batch, with a button to continue if more remain.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $result Accumulated migration stats.
	 */
	protected function render_result( array $result ): void {
		$dry_run = ! empty( $result['dry_run'] );

		if ( $dry_run ) {
			$message = sprintf(
				/* translators: 1: posts scanned, 2: posts that would change, 3: code blocks that would be converted */
				__( 'Checked %1$s posts: %2$s would change, with %3$s code blocks to convert.', 'webberzone-code-block-highlighting' ),
				number_format_i18n( (int) $result['scanned'] ),
				number_format_i18n( (int) $result['changed'] ),
				number_format_i18n( (int) $result['converted'] )
			);
		} else {
			$message = sprintf(
				/* translators: 1: posts scanned, 2: posts changed, 3: code blocks converted */
				__( 'Checked %1$s posts: %2$s changed, with %3$s code blocks converted.', 'webberzone-code-block-highlighting' ),
				number_format_i18n( (int) $result['scanned'] ),
				number_format_i18n( (int) $result['changed'] ),
				number_format_i18n( (int) $result['converted'] )
			);
		}
		?>
		<div class="notice notice-<?php echo empty( $result['done'] ) ? 'info' : 'success'; ?> inline">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( ! empty( $result['skipped'] ) ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of code blocks */
							_n( '%s code block did not match what earlier versions saved and was left as it is. Open its post in the editor to review it:', '%s code blocks did not match what earlier versions saved and were left as they are. Open their posts in the editor to review them:', (int) $result['skipped'], 'webberzone-code-block-highlighting' ),
							number_format_i18n( (int) $result['skipped'] )
						)
					);
					?>
				</p>
				<ul class="ul-disc">
					<?php foreach ( array_slice( array_map( 'intval', (array) ( $result['skipped_ids'] ?? array() ) ), 0, 50 ) as $post_id ) : ?>
						<li><a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ? get_the_title( $post_id ) : '#' . $post_id ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( empty( $result['done'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( menu_page_url( self::SLUG, false ) ); ?>">
					<?php wp_nonce_field( self::SLUG ); ?>
					<input type="hidden" name="wzcbh_after_id" value="<?php echo esc_attr( (string) (int) $result['last_id'] ); ?>" />
					<p>
						<button type="submit" name="wzcbh_migrate_action" value="<?php echo $dry_run ? 'check' : 'convert'; ?>" class="button button-primary"><?php esc_html_e( 'Continue with the next posts', 'webberzone-code-block-highlighting' ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Transient key for the current user's results.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	protected function get_result_key(): string {
		return 'wzcbh_migrate_' . get_current_user_id();
	}

	/**
	 * The current user's accumulated results.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_result(): array {
		$result = get_transient( $this->get_result_key() );

		return is_array( $result ) ? $result : array();
	}
}
