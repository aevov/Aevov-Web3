<?php

get_header();

while (have_posts()) {
    the_post();
    ?>
    <article <?php post_class(); ?>>
        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
}

get_footer();
