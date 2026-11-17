<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container pb-5">
	<?php while ( have_posts() ) : the_post(); ?>
	<div class="breadcrumb-bar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <span><?php the_title(); ?></span></div>
	<article <?php post_class( 'entry' ); ?>><h1 class="page-title mb-1"><?php the_title(); ?></h1><div class="small text-muted mb-3"><?php echo esc_html( get_the_date() ); ?></div>
		<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'img-fluid rounded-3 mb-3' ) ); } ?>
		<div class="entry-content"><?php the_content(); ?></div></article>
	<?php comments_template(); endwhile; ?>
</main>
<?php get_footer(); ?>
