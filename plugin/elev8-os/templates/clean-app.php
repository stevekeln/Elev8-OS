<?php
if (!defined('ABSPATH')) { exit; }
status_header(200);
nocache_headers();
?><!doctype html>
<html <?php language_attributes(); ?> class="elev8-clean-app-document">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo esc_html(Elev8_OS_Clean_App_Module::title()); ?> | Elev8 OS</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main class="elev8-clean-app__main" id="elev8-main-content">
    <?php Elev8_OS_Clean_App_Module::render_screen(); ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
