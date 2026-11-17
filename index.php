<?php
/** ফলব্যাক: ব্লগ/আর্কাইভ/সার্চ (প্রোডাক্ট ছাড়া) */
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container pb-5">
	<div class="breadcrumb-bar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <span><?php echo is_search() ? esc_html( 'খোঁজ: ' . get_search_query() ) : esc_html( wp_strip_all_tags( get_the_archive_title() ?: 'ব্লগ' ) ); ?></span></div>
	<div class="row g-4"><div class="col-lg-9">
	<?php if ( have_posts() ) : ?><div class="row g-3">
		<?php while ( have_posts() ) : the_post(); ?>
		<div class="col-md-6"><article <?php post_class( 'panel h-100' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large', array( 'class' => 'img-fluid rounded-3 mb-2' ) ); ?></a><?php endif; ?>
			<h2 class="h5 fw-bold"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<div class="small text-muted mb-2"><?php echo esc_html( get_the_date() ); ?></div>
			<p class="mb-0"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p></article></div>
		<?php endwhile; ?></div>
		<div class="mt-4"><?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?></div>
	<?php else : ?><div class="panel"><p class="mb-0">কিছু পাওয়া যায়নি।</p></div><?php endif; ?>
	</div><aside class="col-lg-3"><?php dynamic_sidebar( 'sidebar-blog' ); ?></aside></div>
</main>
<?php get_footer(); ?>
