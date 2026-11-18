<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container pb-5"<?php echo ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ? ' style="max-width:1000px"' : ''; ?>>
	<?php if ( function_exists( 'hitscam_breadcrumbs' ) ) { hitscam_breadcrumbs(); } ?>
	<?php while ( have_posts() ) : the_post(); ?>
	<article <?php post_class( 'entry' ); ?>><?php if ( ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) : ?><h1 class="page-title mb-3"><?php the_title(); ?></h1><?php endif; ?><div class="entry-content"><?php the_content(); wp_link_pages(); ?></div></article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
