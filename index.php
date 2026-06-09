<?php
/**
 * Fallback template - bắt buộc để pass WP Theme Check.
 * Elementor Pro Theme Builder sẽ tiếp quản toàn bộ layout.
 */
get_header();

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        the_content();
    endwhile;
endif;

get_footer();
