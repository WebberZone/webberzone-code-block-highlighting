<?php
/**
 * Tag Processor class.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * HTML tag processor that also reports where the current tag sits in the document.
 *
 * @since 1.4.0
 */
class Tag_Processor extends \WP_HTML_Tag_Processor {

	/**
	 * Byte offset and length of the current tag in the unmodified document.
	 *
	 * @since 1.4.0
	 *
	 * @return array{0: int, 1: int}|null Start offset and length, or null when not on a tag.
	 */
	public function get_tag_span(): ?array {
		if ( ! $this->set_bookmark( 'wzcbh' ) ) {
			return null;
		}

		$span = $this->bookmarks['wzcbh'];
		$this->release_bookmark( 'wzcbh' );

		return array( (int) $span->start, (int) $span->length );
	}
}
