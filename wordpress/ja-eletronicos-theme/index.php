<?php
defined('ABSPATH') || exit;
get_header();
while (have_posts()) : the_post(); ?>
<article <?php post_class(); ?>>
<?php if (!function_exists('is_product') || !is_product()) : ?><h1 class="native-title"><?php the_title(); ?></h1><?php endif; ?>
<?php the_content(); ?></article>
<?php endwhile;
get_footer();
