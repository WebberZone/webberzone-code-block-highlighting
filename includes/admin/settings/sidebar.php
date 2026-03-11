<?php
/**
 * Sidebar
 *
 * @package WebberZone\Code_Block_Highlighting
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="postbox-container">
	<div id="qlinksdiv" class="postbox meta-box-sortables">
		<h2 class="metabox-holder"><span><?php esc_html_e( 'Quick links', 'webberzone-code-block-highlighting' ); ?></span></h2>

		<div class="inside">
			<div id="quick-links">
				<ul class="subsub">
					<li>
						<a href="https://webberzone.com/plugins/webberzone-code-block-highlighting/" target="_blank"><?php esc_html_e( 'WebberZone Code Block Highlighting homepage', 'webberzone-code-block-highlighting' ); ?></a>
					</li>

					<li>
						<a href="https://webberzone.com/support/product/webberzone-code-block-highlighting/" target="_blank"><?php esc_html_e( 'Knowledge Base', 'webberzone-code-block-highlighting' ); ?></a>
					</li>

					<li>
						<a href="https://wordpress.org/support/plugin/webberzone-code-block-highlighting/" target="_blank"><?php esc_html_e( 'Support', 'webberzone-code-block-highlighting' ); ?></a>
					</li>

					<li>
						<a href="https://wordpress.org/support/plugin/webberzone-code-block-highlighting/reviews/" target="_blank"><?php esc_html_e( 'Reviews', 'webberzone-code-block-highlighting' ); ?></a>
					</li>

					<li>
						<a href="https://github.com/WebberZone/webberzone-code-block-highlighting" target="_blank"><?php esc_html_e( 'Github repository', 'webberzone-code-block-highlighting' ); ?></a>
					</li>

					<li>
						<a href="https://ajaydsouza.com/" target="_blank"><?php esc_html_e( "Ajay's blog", 'webberzone-code-block-highlighting' ); ?></a>
					</li>
				</ul>
			</div>
		</div><!-- /.inside -->
	</div><!-- /.postbox -->

	<div id="pluginsdiv" class="postbox meta-box-sortables">
		<h2 class="metabox-holder"><span><?php esc_html_e( 'WebberZone plugins', 'webberzone-code-block-highlighting' ); ?></span></h2>

		<div class="inside">
			<div id="quick-links">
				<ul class="subsub">
					<li><a href="https://webberzone.com/plugins/top-10/" target="_blank">Top 10</a></li>
					<li><a href="https://webberzone.com/plugins/contextual-related-posts/" target="_blank">Contextual Related Posts</a></li>
					<li><a href="https://webberzone.com/plugins/better-search/" target="_blank">Better Search</a></li>
					<li><a href="https://webberzone.com/plugins/knowledgebase/" target="_blank">Knowledge Base</a></li>
					<li><a href="https://webberzone.com/plugins/add-to-all/" target="_blank">WebberZone Snippetz</a></li>
					<li><a href="https://webberzone.com/webberzone-followed-posts/" target="_blank">Followed Posts</a></li>
					<li><a href="https://webberzone.com/plugins/popular-authors/" target="_blank">Popular Authors</a></li>
					<li><a href="https://webberzone.com/plugins/autoclose/" target="_blank">Auto-Close</a></li>
				</ul>
			</div>
		</div><!-- /.inside -->
	</div><!-- /.postbox -->

</div>

<div class="postbox-container">
	<div id="followdiv" class="postbox meta-box-sortables">
		<h2 class="metabox-holder"><span><?php esc_html_e( 'Follow us', 'webberzone-code-block-highlighting' ); ?></span></h2>

		<div class="inside" style="text-align: center">
			<a href="https://x.com/webberzone/" target="_blank"><img src="<?php echo esc_url( WZ_CBH_PLUGIN_URL . 'includes/admin/images/x.png' ); ?>" width="100" height="100" alt="X (Twitter)"></a>
			<a href="https://facebook.com/webberzone/" target="_blank"><img src="<?php echo esc_url( WZ_CBH_PLUGIN_URL . 'includes/admin/images/fb.png' ); ?>" width="100" height="100" alt="Facebook"></a>
		</div><!-- /.inside -->
	</div><!-- /.postbox -->
</div>
