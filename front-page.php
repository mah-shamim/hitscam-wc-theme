<?php
/** হোম পেজ: হিরো, ট্রাস্ট, ক্যাটেগরি, ট্রেন্ডিং, বেস্ট সেলার, নতুন */
defined( 'ABSPATH' ) || exit;
get_header();
$wc = hitscam_is_wc();
?>
<main id="main">
<?php if ( ! $wc ) : ?>
	<div class="container py-5"><div class="alert alert-warning">শপ দেখাতে WooCommerce প্রয়োজন — ড্যাশবোর্ড থেকে ইনস্টল করুন।</div></div>
<?php else :
	$feat = wc_get_products( array( 'limit' => 3, 'featured' => true, 'status' => 'publish' ) );
	if ( ! $feat ) {
		$feat = wc_get_products( array( 'limit' => 3, 'status' => 'publish', 'orderby' => 'date' ) );
	}
	$classes = array( 'hero-1', 'hero-2', 'hero-3' );
?>
<section class="container pt-3">
	<div class="owl-carousel owl-theme hero-owl">
	<?php foreach ( $feat as $i => $p ) : ?>
		<div class="hero-slide <?php echo esc_attr( $classes[ $i % 3 ] ); ?>"><div class="row align-items-center w-100"><div class="col-md-7">
			<span class="badge bg-danger mb-2"><?php echo $p->is_on_sale() ? 'অফার' : 'নতুন'; ?></span>
			<h1><?php echo esc_html( $p->get_name() ); ?></h1><p class="lead"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $p->get_short_description() ), 18 ) ); ?></p>
			<a href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>" class="btn btn-brand btn-lg">এখনই কিনুন</a></div>
			<div class="col-md-5 d-none d-md-block text-center"><?php echo wp_kses_post( $p->get_image( 'woocommerce_single', array( 'style' => 'max-height:300px;width:auto;object-fit:contain', 'loading' => 0 === $i ? 'eager' : 'lazy' ) ) ); ?></div></div></div>
	<?php endforeach; ?>
	<?php if ( ! $feat ) : ?>
		<div class="hero-slide hero-1"><div class="row align-items-center w-100"><div class="col-md-7"><h1><?php bloginfo( 'name' ); ?></h1><p class="lead"><?php bloginfo( 'description' ); ?></p><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn btn-brand btn-lg">শপে যান</a></div></div></div>
	<?php endif; ?>
	</div>
</section>

<section class="container section pb-0"><div class="row g-3 trust-row">
	<?php foreach ( array( array( 'bi-truck', 'সারাদেশে ডেলিভারি', 'দ্রুত ও নিরাপদ' ), array( 'bi-shield-check', 'অরিজিনাল পণ্য', 'ওয়ারেন্টিসহ' ), array( 'bi-cash-coin', 'ক্যাশ অন ডেলিভারি', 'হাতে পেয়ে পেমেন্ট' ), array( 'bi-arrow-repeat', '৭ দিনে রিটার্ন', 'ঝামেলাহীন' ) ) as $t ) : ?>
	<div class="col-6 col-md-3"><div class="t-item"><i class="bi <?php echo esc_attr( $t[0] ); ?>"></i><div><b><?php echo esc_html( $t[1] ); ?></b><small><?php echo esc_html( $t[2] ); ?></small></div></div></div>
	<?php endforeach; ?></div></section>

<?php
$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( (int) get_option( 'default_product_cat' ) ), 'number' => 8 ) );
if ( $cats && ! is_wp_error( $cats ) ) : ?>
<section class="container section"><div class="section-head"><h2>ক্যাটেগরি</h2></div><div class="row g-3">
	<?php foreach ( $cats as $c ) :
		$tid = get_term_meta( $c->term_id, 'thumbnail_id', true );
		$img = $tid ? wp_get_attachment_image_url( $tid, 'medium' ) : wc_placeholder_img_src( 'medium' ); ?>
	<div class="col-6 col-md-3"><a class="cat-card" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy"><span><?php echo esc_html( $c->name ); ?></span><small><?php echo esc_html( hitscam_bn_digits( $c->count ) ); ?>টি পণ্য</small></a></div>
	<?php endforeach; ?></div></section>
<?php endif;

$rows = array(
	array( 'ট্রেন্ডিং', array( 'limit' => 4, 'orderby' => 'popularity', 'status' => 'publish', 'stock_status' => 'instock', 'visibility' => 'catalog' ) ),
	array( 'নতুন এসেছে', array( 'limit' => 4, 'orderby' => 'date', 'status' => 'publish', 'stock_status' => 'instock', 'visibility' => 'catalog' ) ),
	array( 'অফার', array( 'limit' => 4, 'include' => array_slice( wc_get_product_ids_on_sale(), 0, 4 ) ?: array( 0 ), 'status' => 'publish', 'stock_status' => 'instock' ) ),
);
foreach ( $rows as $r ) :
	$list = wc_get_products( $r[1] );
	if ( ! $list ) { continue; } ?>
<section class="container section"><div class="section-head"><h2><?php echo esc_html( $r[0] ); ?></h2><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">সব দেখুন →</a></div><div class="row g-3">
	<?php
	global $post, $product;
	foreach ( $list as $p ) {
		$post    = get_post( $p->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		$product = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( ! $p->is_visible() ) { continue; }
		echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3">';
		wc_get_template_part( 'content', 'product' );
		echo '</div>';
	}
	wp_reset_postdata(); ?>
</div></section>
<?php endforeach; endif; ?>
<?php
while ( have_posts() ) { the_post(); if ( get_the_content() ) { echo '<section class="container section">'; the_content(); echo '</section>'; } }
?>
</main>
<?php get_footer(); ?>
