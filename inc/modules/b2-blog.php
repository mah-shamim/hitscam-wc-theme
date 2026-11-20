<?php
/**
 * b2 — ব্লগ/গাইড: [hitscam_blog] তালিকা, সিঙ্গেল পোস্টে ব্যাজ+পড়ার সময়+FAQ+সংশ্লিষ্ট পণ্য+JSON-LD (Article/Breadcrumb/FAQPage)।
 * ডেমো গাইড পোস্ট inc/data/guide-posts.json থেকে একবার তৈরি হয়। টেমপ্লেট: blog.html, guide.html, posts.js।
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'hitscam_extra_pages', function ( $p ) {
	$p['blog'] = array( 'গাইড ও টিপস', '[hitscam_blog]' );
	return $p;
} );

/* ---------- ডেমো গাইড সিডার ---------- */
add_action( 'init', function () {
	if ( get_option( "hitscam_b2_seed" ) ) { return; }
	$f = HITSCAM_DIR . '/inc/data/guide-posts.json';
	$d = file_exists( $f ) ? json_decode( file_get_contents( $f ), true ) : array(); // phpcs:ignore
	update_option( 'hitscam_b2_seed', 1 );
	if ( ! is_array( $d ) ) { return; }
	foreach ( $d as $x ) {
		if ( get_page_by_path( $x['slug'], OBJECT, 'post' ) ) { continue; }
		$cat = term_exists( $x['cat'], 'category' ) ?: wp_insert_term( $x['cat'], 'category' );
		$id  = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $x['title'], 'post_name' => $x['slug'], 'post_excerpt' => $x['excerpt'], 'post_content' => $x['body'], 'post_date' => $x['date'] . ' 10:00:00', 'post_category' => is_wp_error( $cat ) ? array() : array( (int) ( is_array( $cat ) ? $cat['term_id'] : $cat ) ) ) );
		if ( $id && ! is_wp_error( $id ) ) { update_post_meta( $id, '_hc_faq', wp_json_encode( $x['faq'] ?? array() ) ); update_post_meta( $id, '_hc_mins', (int) ( $x['mins'] ?? 0 ) ); }
	}
}, 40 );

function hitscam_b2_mins( $id ) {
	$m = (int) get_post_meta( $id, '_hc_mins', true );
	return $m ?: max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) / 180 ) );
}
function hitscam_b2_faq( $id ) {
	$a = json_decode( (string) get_post_meta( $id, '_hc_faq', true ), true );
	return is_array( $a ) ? array_values( array_filter( $a, function ( $f ) { return ! empty( $f['q'] ) && ! empty( $f['a'] ); } ) ) : array();
}
function hitscam_b2_placeholder( $label ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"><rect width="1200" height="630" fill="#f8f9fa"/><text x="600" y="330" font-size="64" text-anchor="middle" fill="#e03131" font-family="sans-serif">' . esc_html( $label ) . '</text></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/* ---------- তালিকা ---------- */
add_shortcode( 'hitscam_blog', function () {
	$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 24, 'ignore_sticky_posts' => true ) );
	ob_start();
	echo '<p class="text-muted mb-4">ক্যামেরা কেনা ও বসানোর আগে যা জানা দরকার — সহজ বাংলায়।</p><div class="row g-3 mb-5" id="postList">';
	while ( $q->have_posts() ) {
		$q->the_post(); $id = get_the_ID(); $cats = get_the_category(); $cn = $cats ? $cats[0]->name : '';
		$img = has_post_thumbnail() ? get_the_post_thumbnail_url( $id, 'large' ) : hitscam_b2_placeholder( $cn ?: get_the_title() );
		echo '<div class="col-12 col-md-6"><a class="post-card" href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy"><div class="pc-body">' . ( $cn ? '<span class="badge bg-dark mb-2">' . esc_html( $cn ) . '</span>' : '' ) . '<h2 class="h5 fw-bold">' . esc_html( get_the_title() ) . '</h2><p class="text-muted mb-2">' . esc_html( wp_trim_words( get_the_excerpt(), 30 ) ) . '</p><small class="text-muted"><i class="bi bi-calendar3"></i> ' . esc_html( get_the_date() ) . ' · <i class="bi bi-clock"></i> ' . esc_html( hitscam_bn_digits( hitscam_b2_mins( $id ) ) ) . ' মিনিট</small></div></a></div>';
	}
	wp_reset_postdata();
	if ( ! $q->found_posts ) { echo '<div class="text-muted">এখনও কোনো গাইড নেই।</div>'; }
	echo '</div>';
	return ob_get_clean();
} );

/* ---------- সিঙ্গেল পোস্ট ---------- */
add_filter( 'the_content', function ( $c ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) { return $c; }
	$id = get_the_ID(); $cats = get_the_category();
	$h  = '<div class="mb-2">' . ( $cats ? '<span class="badge bg-dark me-2">' . esc_html( $cats[0]->name ) . '</span>' : '' ) . '<small class="text-muted"><i class="bi bi-clock"></i> ' . esc_html( hitscam_bn_digits( hitscam_b2_mins( $id ) ) ) . ' মিনিট পড়া</small></div><div class="article-body">' . $c . '</div>';
	$faq = hitscam_b2_faq( $id );
	if ( $faq ) {
		$h .= '<section class="my-4"><h2 class="h4 fw-bold mb-3">সচরাচর জিজ্ঞাসা</h2><div class="accordion" id="gFaq">';
		foreach ( $faq as $i => $f ) {
			$h .= '<div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button ' . ( $i ? 'collapsed' : '' ) . '" type="button" data-bs-toggle="collapse" data-bs-target="#gf' . (int) $i . '">' . esc_html( $f['q'] ) . '</button></h3><div id="gf' . (int) $i . '" class="accordion-collapse collapse ' . ( $i ? '' : 'show' ) . '" data-bs-parent="#gFaq"><div class="accordion-body">' . esc_html( $f['a'] ) . '</div></div></div>';
		}
		$h .= '</div></section>';
	}
	if ( function_exists( 'wc_get_product' ) ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_hc_related', true ) ) ) );
		if ( ! $ids ) { $ids = wc_get_products( array( 'limit' => 3, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'popularity' ) ); }
		if ( $ids ) {
			global $product; $keep = $product;
			ob_start();
			echo '<section class="my-4"><h2 class="h4 fw-bold mb-3">সংশ্লিষ্ট পণ্য</h2><div class="row g-3">';
			foreach ( array_slice( $ids, 0, 3 ) as $pid ) { $product = wc_get_product( $pid ); if ( $product ) { echo '<div class="col-12 col-sm-6 col-lg-4">'; wc_get_template_part( 'content', 'product' ); echo '</div>'; } }
			echo '</div></section>';
			$h .= ob_get_clean(); $product = $keep;
		}
	}
	return $h;
}, 20 );

add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) ) { return; }
	$id = get_the_ID(); $t = get_the_title( $id ); $u = get_permalink( $id );
	$g  = array(
		array( '@type' => 'Article', 'headline' => $t, 'description' => wp_strip_all_tags( get_the_excerpt( $id ) ), 'datePublished' => get_the_date( 'c', $id ), 'dateModified' => get_the_modified_date( 'c', $id ), 'author' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ), 'publisher' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ), 'mainEntityOfPage' => $u ),
		array( '@type' => 'BreadcrumbList', 'itemListElement' => array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ), array( '@type' => 'ListItem', 'position' => 2, 'name' => 'গাইড ও টিপস', 'item' => hitscam_page_url( 'blog' ) ), array( '@type' => 'ListItem', 'position' => 3, 'name' => $t ) ) ),
	);
	if ( has_post_thumbnail( $id ) ) { $g[0]['image'] = get_the_post_thumbnail_url( $id, 'large' ); }
	if ( $faq = hitscam_b2_faq( $id ) ) { $g[] = array( '@type' => 'FAQPage', 'mainEntity' => array_map( function ( $f ) { return array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) ); }, $faq ) ); }
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $g ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
} );

/* ---------- মেটাবক্স: FAQ ও সংশ্লিষ্ট পণ্য ---------- */
add_action( 'add_meta_boxes', function () { add_meta_box( 'hc_b2_post', 'গাইড: FAQ ও সংশ্লিষ্ট পণ্য', 'hitscam_b2_box', 'post', 'normal' ); } );
function hitscam_b2_box( $post ) {
	wp_nonce_field( 'hc_b2_post', 'hc_b2_n' );
	$lines = '';
	foreach ( hitscam_b2_faq( $post->ID ) as $f ) { $lines .= $f['q'] . ' | ' . $f['a'] . "\n"; }
	echo '<p><label><b>FAQ</b> (প্রতি লাইনে: প্রশ্ন | উত্তর)<br><textarea name="hc_faq" rows="5" style="width:100%">' . esc_textarea( $lines ) . '</textarea></label></p>';
	echo '<p><label><b>সংশ্লিষ্ট পণ্যের ID</b> (কমা দিয়ে, সর্বোচ্চ ৩; ফাঁকা = জনপ্রিয় পণ্য)<br><input type="text" name="hc_related" style="width:100%" value="' . esc_attr( get_post_meta( $post->ID, '_hc_related', true ) ) . '"></label></p>';
}
add_action( 'save_post_post', function ( $id ) {
	if ( ! isset( $_POST['hc_b2_n'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hc_b2_n'] ), 'hc_b2_post' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	$faq = array();
	foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['hc_faq'] ?? '' ) ) as $l ) { // phpcs:ignore
		$p = array_map( 'trim', explode( '|', $l, 2 ) );
		if ( 2 === count( $p ) && $p[0] && $p[1] ) { $faq[] = array( 'q' => sanitize_text_field( $p[0] ), 'a' => sanitize_text_field( $p[1] ) ); }
	}
	update_post_meta( $id, '_hc_faq', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $id, '_hc_related', implode( ',', array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['hc_related'] ?? '' ) ) ) ) ) ); // phpcs:ignore
} );
