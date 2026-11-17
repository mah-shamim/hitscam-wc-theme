<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container pb-5">
	<?php if ( function_exists( 'hitscam_breadcrumbs' ) ) { hitscam_breadcrumbs(); } ?>
	<?php while ( have_posts() ) : the_post(); ?>
	<article <?php post_class( 'entry' ); ?>><h1 class="page-title mb-3"><?php the_title(); ?></h1><div class="entry-content"><?php the_content(); wp_link_pages(); ?></div></article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
