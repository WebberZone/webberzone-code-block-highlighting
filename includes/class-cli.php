<?php
/**
 * WP-CLI commands.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

namespace WebberZone\Code_Block_Highlighting;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manage WebberZone Code Block Highlighting.
 *
 * @since 1.3.0
 */
class CLI {

	/**
	 * Convert code blocks saved by version 1.2.3 or earlier to the core Code block's markup.
	 *
	 * Older versions wrote the language, file name, line options and max height
	 * into the block HTML, which the core Code block cannot validate once this
	 * plugin is deactivated. This moves those values into the block attributes,
	 * exactly as re-saving the post in the editor does. Other blocks are left
	 * untouched, and running it again changes nothing.
	 *
	 * Post content is rewritten in place without creating revisions. Back up the
	 * database first, or run with --dry-run to see what would change.
	 *
	 * On multisite, run it once per site with --url.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--post_type=<post_types>]
	 * : Comma-separated post types to scan. Default: all except revisions.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs to scan.
	 *
	 * [--verbose]
	 * : List every post that changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wzcbh migrate-code-blocks --dry-run
	 *     wp wzcbh migrate-code-blocks --post_type=post,page
	 *     wp site list --field=url | xargs -I % wp wzcbh migrate-code-blocks --url=%
	 *
	 * @subcommand migrate-code-blocks
	 *
	 * @since 1.3.0
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function migrate_code_blocks( array $args, array $assoc_args ): void {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$verbose = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );

		$stats = Code_Block_Migrator::migrate(
			array(
				'dry_run'    => $dry_run,
				'post_types' => self::parse_list( $assoc_args['post_type'] ?? '' ),
				'ids'        => array_map( 'intval', self::parse_list( $assoc_args['ids'] ?? '' ) ),
				'on_post'    => static function ( int $post_id, array $counts ) use ( $verbose, $dry_run ): void {
					if ( $verbose ) {
						\WP_CLI::log(
							sprintf(
								'%s post %d: %d block(s)%s.',
								$dry_run ? 'Would convert' : 'Converted',
								$post_id,
								$counts['converted'],
								$counts['skipped'] ? sprintf( ', %d skipped', $counts['skipped'] ) : ''
							)
						);
					}
				},
			)
		);

		$summary = sprintf(
			'%1$d post(s) scanned, %2$d %3$s, %4$d code block(s) %5$s.',
			$stats['scanned'],
			$stats['changed'],
			$dry_run ? 'would change' : 'changed',
			$stats['converted'],
			$dry_run ? 'to convert' : 'converted'
		);

		if ( $stats['skipped'] ) {
			\WP_CLI::warning(
				sprintf(
					'%1$d code block(s) did not match what earlier versions saved and were left as they are. Open these posts in the editor to review them: %2$s',
					$stats['skipped'],
					implode( ', ', $stats['skipped_ids'] )
				)
			);
		}

		\WP_CLI::success( $summary );
	}

	/**
	 * Split a comma-separated option value.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $value Option value.
	 * @return string[]
	 */
	private static function parse_list( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
