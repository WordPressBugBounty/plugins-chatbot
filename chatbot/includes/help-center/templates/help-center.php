<?php
/**
 * Help Center page wrapper: the theme's header and footer around the Help
 * Center view. Block themes get their header and footer template parts; classic
 * themes get get_header() and get_footer(). A theme can replace this file with
 * wpbot-help-center/help-center.php.
 *
 * The view is rendered by \QuantumCloud\HelpCenter\Front\Front::html().
 *
 * @package QuantumCloud\HelpCenter
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	// Render everything before wp_head(), as WordPress's block template canvas does, so the styles and
	// script modules the header and footer blocks enqueue (and the import map) are printed in the head.
	$qcld_wpbot_help_center_header = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
	$qcld_wpbot_help_center_main   = \QuantumCloud\HelpCenter\Front\Front::html();
	$qcld_wpbot_help_center_footer = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php
	echo $qcld_wpbot_help_center_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The theme's header template part.
	echo '<main id="wpbot-hc-main" class="wpbot-hc-main">' . $qcld_wpbot_help_center_main . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the view.
	echo $qcld_wpbot_help_center_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The theme's footer template part.
	?>
</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
} else {
	get_header();
	echo '<main id="wpbot-hc-main" class="wpbot-hc-main">' . \QuantumCloud\HelpCenter\Front\Front::html() . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the view.
	get_footer();
}//end if
