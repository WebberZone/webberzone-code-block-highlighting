<?php
/**
 * Converts code blocks saved by 1.2.3 and earlier to core's markup.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

namespace WebberZone\Code_Block_Highlighting;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Code block migrator.
 *
 * Mirrors the editor's deprecations in includes/blocks/src/js/index.js, so a
 * converted block is byte-for-byte what the editor writes when it re-saves it.
 *
 * @since 1.3.0
 */
class Code_Block_Migrator {

	/**
	 * Attributes the old save functions wrote on `<pre>`.
	 *
	 * @since 1.3.0
	 *
	 * @var string[]
	 */
	const PRE_ATTRIBUTES = array( 'data-title', 'title', 'data-start', 'data-line' );

	/**
	 * The max-height declarations the old save function appended to `<pre>`.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	const MAX_HEIGHT_PATTERN = '/(?:^|;)\s*max-height:\s*\d+px;\s*overflow-y:\s*auto(?=;|$)/i';

	/**
	 * Posts read per database query.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * The plugin's block attributes, in the order index.js registers them.
	 *
	 * @since 1.3.0
	 *
	 * @var string[]
	 */
	const ATTRIBUTE_ORDER = array( 'language', 'lineNumbers', 'lineNumbersStart', 'wordWrap', 'highlightLines', 'maxHeight', 'downloadButton', 'colorScheme', 'title', '_legacyTitle' );

	/**
	 * Convert every legacy core/code block in a post's content.
	 *
	 * Only the matched code block spans are rewritten; everything else in the
	 * content is left byte-identical.
	 *
	 * @since 1.3.0
	 *
	 * @param  string                            $content Post content.
	 * @param  array{converted:int, skipped:int} $counts  Running block counts, updated in place.
	 * @return string Converted content.
	 */
	public static function convert_content( string $content, array &$counts ): string {
		$counts += array(
			'converted' => 0,
			'skipped'   => 0,
		);

		if ( false === strpos( $content, '<!-- wp:code' ) && false === strpos( $content, '<!-- wp:core/code' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'#<!--\s+wp:(?:core/)?code(?=\s)(?:\s+\{.*?\})?\s+-->.*?<!--\s+/wp:(?:core/)?code\s+-->#s',
			static function ( array $matches ) use ( &$counts ): string {
				$blocks = parse_blocks( $matches[0] );

				if ( 1 !== count( $blocks ) || 'core/code' !== $blocks[0]['blockName'] ) {
					return $matches[0];
				}

				$block = self::convert_block( $blocks[0] );

				if ( null === $block ) {
					return $matches[0];
				}

				if ( false === $block ) {
					++$counts['skipped'];
					return $matches[0];
				}

				++$counts['converted'];
				return serialize_block( $block );
			},
			$content
		);
	}

	/**
	 * Convert one parsed core/code block.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>|null|false Converted block, null when already in core's format,
	 *                                         false when it could not be converted cleanly.
	 */
	public static function convert_block( array $block ) {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$html  = (string) ( $block['innerHTML'] ?? '' );

		if ( ! empty( $block['innerBlocks'] ) || ! self::is_legacy( $html, $attrs ) ) {
			return null;
		}

		// The editor only migrates markup its old save would reproduce; anything
		// else would lose options that live only in the HTML.
		if ( ! self::matches_old_save( $html, $attrs ) ) {
			return false;
		}

		$custom    = self::get_custom_classes( $attrs );
		$processor = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag( array( 'tag_name' => 'pre' ) ) ) {
			return false;
		}

		$title = $processor->get_attribute( 'data-title' );
		if ( ! is_string( $title ) || '' === $title ) {
			$title = $processor->get_attribute( 'title' );
		}

		foreach ( self::get_managed_classes( $processor, $custom ) as $class_name ) {
			$processor->remove_class( $class_name );
		}

		foreach ( self::PRE_ATTRIBUTES as $name ) {
			$processor->remove_attribute( $name );
		}

		$style = $processor->get_attribute( 'style' );
		if ( is_string( $style ) && self::has_max_height( $style ) ) {
			$style = self::strip_max_height( $style );

			if ( '' === $style ) {
				$processor->remove_attribute( 'style' );
			} else {
				$processor->set_attribute( 'style', $style );
			}
		}

		if ( ! $processor->next_tag( array( 'tag_name' => 'code' ) ) ) {
			return false;
		}

		$language = $processor->get_attribute( 'lang' );
		$processor->remove_attribute( 'lang' );
		$processor->remove_attribute( 'class' );

		// Removed attributes leave their surrounding whitespace behind.
		$new_html = self::escape_code( self::tidy_opening_tag( self::tidy_opening_tag( $processor->get_updated_html(), 'pre' ), 'code' ) );

		if ( self::is_legacy( $new_html, $attrs ) ) {
			return false;
		}

		if ( is_string( $language ) && '' !== $language ) {
			$attrs['language'] = $language;
		}
		if ( is_string( $title ) && '' !== $title ) {
			$attrs['title'] = $title;
		}

		$block['attrs']        = self::order_attributes( $attrs );
		$block['innerHTML']    = $new_html;
		$block['innerContent'] = array( $new_html );

		return $block;
	}

	/**
	 * Order attributes the way the editor serializes them.
	 *
	 * The editor writes attributes in registration order: core's and block
	 * supports' first, then the plugin's. Other keys keep their relative order.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed> $attrs Block attributes.
	 * @return array<string, mixed>
	 */
	protected static function order_attributes( array $attrs ): array {
		$ordered = array_diff_key( $attrs, array_flip( self::ATTRIBUTE_ORDER ) );

		foreach ( self::ATTRIBUTE_ORDER as $name ) {
			if ( array_key_exists( $name, $attrs ) ) {
				$ordered[ $name ] = $attrs[ $name ];
			}
		}

		return $ordered;
	}

	/**
	 * Whether a block's saved HTML carries markup the old save functions wrote.
	 *
	 * @since 1.3.0
	 *
	 * @param  string               $html  Block inner HTML.
	 * @param  array<string, mixed> $attrs Block comment attributes.
	 * @return bool
	 */
	public static function is_legacy( string $html, array $attrs = array() ): bool {
		$processor = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag( array( 'tag_name' => 'pre' ) ) ) {
			return false;
		}

		if ( self::get_managed_classes( $processor, self::get_custom_classes( $attrs ) ) ) {
			return true;
		}

		foreach ( self::PRE_ATTRIBUTES as $name ) {
			if ( null !== $processor->get_attribute( $name ) ) {
				return true;
			}
		}

		$style = $processor->get_attribute( 'style' );
		if ( is_string( $style ) && self::has_max_height( $style ) ) {
			return true;
		}

		if ( ! $processor->next_tag( array( 'tag_name' => 'code' ) ) ) {
			return false;
		}

		$code_class = $processor->get_attribute( 'class' );

		// An empty class matches core's save, which writes none.
		return null !== $processor->get_attribute( 'lang' ) || ( is_string( $code_class ) && '' !== trim( $code_class, " \t\n\r\0\x0B" ) );
	}

	/**
	 * Whether the HTML is exactly what one of the old save functions wrote for these attributes.
	 *
	 * Covers this plugin's save up to 1.2.3 and the Code Syntax Block plugin's.
	 *
	 * @since 1.3.0
	 *
	 * @param  string               $html  Block inner HTML.
	 * @param  array<string, mixed> $attrs Block comment attributes.
	 * @return bool
	 */
	protected static function matches_old_save( string $html, array $attrs ): bool {
		$processor = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag( array( 'tag_name' => 'pre' ) ) ) {
			return false;
		}

		$managed = self::get_managed_classes( $processor, self::get_custom_classes( $attrs ) );
		sort( $managed );

		$found = array(
			'data-start' => $processor->get_attribute( 'data-start' ),
			'data-line'  => $processor->get_attribute( 'data-line' ),
			'title'      => $processor->get_attribute( 'title' ),
		);

		$style      = $processor->get_attribute( 'style' );
		$max_height = is_string( $style ) && preg_match( '/(?:^|;)\s*max-height:\s*(\d+)px;\s*overflow-y:\s*auto(?=;|$)/i', $style, $matches ) ? (int) $matches[1] : null;
		$has_title  = null !== $processor->get_attribute( 'data-title' );

		if ( ! $processor->next_tag( array( 'tag_name' => 'code' ) ) ) {
			return false;
		}

		$language   = $processor->get_attribute( 'lang' );
		$language   = is_string( $language ) ? $language : '';
		$code_class = $processor->get_attribute( 'class' );
		$code_class = is_string( $code_class ) && '' !== trim( $code_class, " \t\n\r\0\x0B" ) ? $code_class : null;

		$line_numbers = ! empty( $attrs['lineNumbers'] );
		$start        = (int) ( $attrs['lineNumbersStart'] ?? 0 );
		$lines        = (string) ( $attrs['highlightLines'] ?? '' );
		$lang_class   = '' !== $language ? 'language-' . $language : '';

		$plugin_classes = array_values( array_filter( array( $lang_class, $line_numbers ? 'line-numbers' : '', ! empty( $attrs['wordWrap'] ) ? 'word-wrap' : '' ) ) );
		sort( $plugin_classes );

		$expected_start = $line_numbers && $start && 1 !== $start ? (string) $start : null;
		$expected_line  = '' !== $lines ? $lines : null;
		$expected_max   = ! empty( $attrs['maxHeight'] ) ? (int) $attrs['maxHeight'] : null;
		$expected_code  = '' !== $lang_class ? $lang_class : null;

		$plugin_save = $managed === $plugin_classes
			&& null === $found['title']
			&& $expected_start === $found['data-start']
			&& $expected_line === $found['data-line']
			&& $expected_max === $max_height
			&& $expected_code === $code_class;

		if ( $plugin_save ) {
			return true;
		}

		$csb_class    = implode( ' ', array_filter( array( $lang_class, $line_numbers ? 'line-numbers' : '' ) ) );
		$expected_csb = '' !== $csb_class ? $csb_class : null;

		return array() === $managed
			&& ! $has_title
			&& null === $found['data-start']
			&& null === $found['data-line']
			&& null === $max_height
			&& $expected_csb === $code_class;
	}

	/**
	 * Convert legacy code blocks across posts, in batches.
	 *
	 * Writes `post_content` directly: wp_update_post() would run kses for users
	 * without `unfiltered_html` (WP-CLI, multisite site admins) and strip code.
	 *
	 * @since 1.3.0
	 *
	 * @param array{dry_run?:bool, post_types?:string[], ids?:int[], after_id?:int, limit?:int, on_post?:callable} $args {
	 *     Optional. Migration arguments.
	 *
	 *     @type bool     $dry_run    Count without writing. Default false.
	 *     @type string[] $post_types Post types to scan. Default all except revisions.
	 *     @type int[]    $ids        Only these post IDs.
	 *     @type int      $after_id   Resume after this post ID. Default 0.
	 *     @type int      $limit      Stop after scanning this many posts; 0 for no limit.
	 *     @type callable $on_post    Called with ( int $post_id, array $post_counts ) for each post that changes or has skipped blocks.
	 * }
	 * @return array{scanned:int, changed:int, converted:int, skipped:int, skipped_ids:int[], last_id:int, done:bool}
	 */
	public static function migrate( array $args = array() ): array {
		global $wpdb;

		$dry_run    = ! empty( $args['dry_run'] );
		$post_types = array_filter( array_map( 'sanitize_key', (array) ( $args['post_types'] ?? array() ) ) );
		$ids        = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );
		$limit      = max( 0, (int) ( $args['limit'] ?? 0 ) );
		$on_post    = $args['on_post'] ?? null;

		$stats = array(
			'scanned'     => 0,
			'changed'     => 0,
			'converted'   => 0,
			'skipped'     => 0,
			'skipped_ids' => array(),
			'last_id'     => max( 0, (int) ( $args['after_id'] ?? 0 ) ),
			'done'        => false,
		);

		$where  = "post_type <> 'revision' AND post_status <> 'auto-draft' AND post_content LIKE %s";
		$params = array( '%' . $wpdb->esc_like( 'wp:code' ) . '%' );

		if ( $post_types ) {
			$where .= ' AND post_type IN (' . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ')';
			$params = array_merge( $params, $post_types );
		}
		if ( $ids ) {
			$where .= ' AND ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $ids );
		}

		while ( true ) {
			$batch = $limit ? min( self::BATCH_SIZE, $limit - $stats['scanned'] ) : self::BATCH_SIZE;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID > %d AND {$where} ORDER BY ID ASC LIMIT %d",
					array_merge( array( $stats['last_id'] ), $params, array( $batch ) )
				)
			);
			// phpcs:enable

			if ( ! $rows ) {
				$stats['done'] = true;
				break;
			}

			foreach ( $rows as $row ) {
				++$stats['scanned'];
				$stats['last_id'] = (int) $row->ID;

				$counts  = array(
					'converted' => 0,
					'skipped'   => 0,
				);
				$content = self::convert_content( (string) $row->post_content, $counts );

				$stats['converted'] += $counts['converted'];
				$stats['skipped']   += $counts['skipped'];

				if ( $counts['skipped'] ) {
					$stats['skipped_ids'][] = (int) $row->ID;
				}

				$changed = $content !== $row->post_content;

				if ( $changed ) {
					++$stats['changed'];
				}

				if ( $on_post && ( $changed || $counts['skipped'] ) ) {
					$on_post( (int) $row->ID, $counts );
				}

				if ( ! $changed ) {
					continue;
				}

				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => (int) $row->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					clean_post_cache( (int) $row->ID );
				}
			}

			if ( count( $rows ) < $batch ) {
				$stats['done'] = true;
				break;
			}

			if ( $limit && $stats['scanned'] >= $limit ) {
				break;
			}
		}

		return $stats;
	}

	/**
	 * Class names on the block's comment `className`, which the user set.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed> $attrs Block comment attributes.
	 * @return string[]
	 */
	protected static function get_custom_classes( array $attrs ): array {
		$classes = preg_split( '/\s+/', (string) ( $attrs['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $classes ) ? $classes : array();
	}

	/**
	 * Plugin-managed classes on the current tag that the user did not set.
	 *
	 * @since 1.3.0
	 *
	 * @param  \WP_HTML_Tag_Processor $processor Processor positioned on `<pre>`.
	 * @param  string[]               $custom    Classes from the comment `className`.
	 * @return string[]
	 */
	protected static function get_managed_classes( \WP_HTML_Tag_Processor $processor, array $custom ): array {
		$managed = array();

		foreach ( $processor->class_list() as $class_name ) {
			if ( preg_match( '/^(?:line-numbers|word-wrap|language-.+)$/', $class_name ) && ! in_array( $class_name, $custom, true ) ) {
				$managed[] = $class_name;
			}
		}

		return $managed;
	}

	/**
	 * Apply the escaping core's Code block save applies to its content.
	 *
	 * Mirrors `escape()` in the core/code block's utils.js: `[` so shortcodes do
	 * not run, and the protocol of a URL alone on its line so it is not autolinked.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $html Block inner HTML.
	 * @return string
	 */
	protected static function escape_code( string $html ): string {
		return (string) preg_replace_callback(
			'#(<code\b[^>]*>)(.*?)(</code>)#is',
			static function ( array $matches ): string {
				$content = str_replace( '[', '&#91;', $matches[2] );
				$content = (string) preg_replace( '/^(\s*https?:)\/\/([^\s<>"]+\s*)$/m', '$1&#47;&#47;$2', $content, 1 );

				return $matches[1] . $content . $matches[3];
			},
			$html,
			1
		);
	}

	/**
	 * Re-join the first opening tag's attributes with single spaces.
	 *
	 * Attribute values are kept verbatim; only the gaps between them change.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $html Block inner HTML.
	 * @param  string $tag  Tag name.
	 * @return string
	 */
	protected static function tidy_opening_tag( string $html, string $tag ): string {
		return (string) preg_replace_callback(
			'/<' . $tag . '\b([^>]*)>/i',
			static function ( array $matches ) use ( $tag ): string {
				preg_match_all( '/([^\s=>\/]+)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/', $matches[1], $parts, PREG_SET_ORDER );

				$attributes = '';
				foreach ( $parts as $part ) {
					$attributes .= ' ' . $part[1] . ( isset( $part[2] ) ? '=' . ltrim( (string) substr( ltrim( $part[2], " \t\n\r\0\x0B" ), 1 ), " \t\n\r\0\x0B" ) : '' );
				}

				return '<' . $tag . $attributes . '>';
			},
			$html,
			1
		);
	}

	/**
	 * Whether an inline style carries the max-height the old save function appended.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $style Inline style.
	 * @return bool
	 */
	protected static function has_max_height( string $style ): bool {
		return (bool) preg_match( self::MAX_HEIGHT_PATTERN, $style );
	}

	/**
	 * Remove the max-height declarations the old save function appended.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $style Inline style.
	 * @return string
	 */
	protected static function strip_max_height( string $style ): string {
		$stripped = preg_replace( self::MAX_HEIGHT_PATTERN, '', $style );

		return trim( (string) $stripped, " \t\n\r\0\x0B;" );
	}
}
