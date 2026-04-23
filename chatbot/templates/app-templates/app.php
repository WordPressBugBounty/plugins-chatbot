<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
get_header();
echo do_shortcode('[wpwbot_app]');
get_footer();