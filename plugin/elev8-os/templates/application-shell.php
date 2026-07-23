<?php
/**
 * Plugin-owned Elev8 OS application document.
 *
 * This intentionally does not call get_header() or get_footer(). The active
 * public WordPress theme continues to own public pages, while managed Elev8 OS
 * routes render inside a stable application boundary.
 *
 * @package Elev8OS
 */
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>
<body <?php body_class('elev8-os-application-document'); ?>>
<?php wp_body_open(); ?>
<main id="elev8-os-application-main" class="elev8-os-application-main" tabindex="-1">
    <?php
    while (have_posts()) {
        the_post();
        the_content();
    }
    ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
