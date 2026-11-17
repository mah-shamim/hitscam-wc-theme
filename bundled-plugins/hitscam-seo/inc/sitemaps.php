<?php
/** XML সাইটম্যাপ: index, posts/pages/products, taxonomies, News, Video, RSS */
defined( 'ABSPATH' ) || exit;

const HCSEO_SM_LIMIT = 1000;

function hcseo_sitemap_rewrites() {
	add_rewrite_rule( '^sitemap_index\.xml$', 'index.php?hcseo_sm=index', 'top' );
	add_rewrite_rule( '^sitemap-(post|tax)-([a-z0-9_\-]+?)-?([0-9]*)\.xml$', 'index.php?hcseo_sm=$matches[1]&hcseo_o=$matches[2]&hcseo_pg=$matches[3]', 'top' );
	add_rewrite_rule( '^sitemap-(news|video|rss)\.xml$', 'index.php?hcseo_sm=$matches[1]', 'top' );
	add_rewrite_rule( '^([a-f0-9]{16,64})\.txt$', 'index.php?hcseo_key=$matches[1]', 'top' );
}
add_action( 'init', 'hcseo_sitemap_rewrites' );
add_filter( 'query_vars', function ( $v ) { return array_merge( $v, array( 'hcseo_sm', 'hcseo_o', 'hcseo_pg', 'hcseo_key' ) ); } );
add_filter( 'wp_sitemaps_enabled', function ( $e ) { return hcseo_opt( 'sm_enable' ) ? false : $e; } );
add_filter( 'redirect_canonical', function ( $r ) { return get_query_var( 'hcseo_sm' ) ? false : $r; } );

function hcseo_csv( $k ) { return array_values( array_filter( array_map( 'trim', explode( ',', (string) hcseo_opt( $k ) ) ) ) ); }

function hcseo_xml_head( $extra = '' ) {
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
}
function hcseo_u( $s ) { return esc_url( $s, null, 'db' ); }

function hcseo_indexable_args( $type, $page, $per = HCSEO_SM_LIMIT ) {
	return array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $per, 'paged' => $page, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => false, 'has_password' => false,
		'meta_query' => array( 'relation' => 'OR', array( 'key' => '_hcseo_noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => '_hcseo_noindex', 'value' => '1', 'compare' => '!=' ) ) );
}

add_action( 'template_redirect', function () {
	$key = get_query_var( 'hcseo_key' );
	if ( $key ) {
		if ( hcseo_opt( 'in_key' ) && hash_equals( (string) hcseo_opt( 'in_key' ), $key ) ) { header( 'Content-Type: text/plain; charset=utf-8' ); echo $key; exit; }
		global $wp_query; $wp_query->set_404(); status_header( 404 ); return;
	}
	$sm = get_query_var( 'hcseo_sm' );
	if ( ! $sm || ! hcseo_opt( 'sm_enable' ) ) { return; }
	$ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
	hcseo_xml_head();
	if ( 'index' === $sm ) {
		echo "<sitemapindex $ns>\n";
		foreach ( hcseo_csv( 'sm_types' ) as $pt ) {
			if ( ! post_type_exists( $pt ) ) { continue; }
			$q = new WP_Query( hcseo_indexable_args( $pt, 1, 1 ) + array( 'fields' => 'ids' ) );
			if ( ! $q->found_posts ) { continue; }
			$pages = (int) ceil( $q->found_posts / HCSEO_SM_LIMIT );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$last = get_posts( hcseo_indexable_args( $pt, 1, 1 ) + array( 'fields' => 'ids' ) );
				echo '<sitemap><loc>' . hcseo_u( home_url( "/sitemap-post-$pt-$i.xml" ) ) . '</loc>' . ( $last ? '<lastmod>' . esc_html( get_post_modified_time( 'c', true, $last[0] ) ) . '</lastmod>' : '' ) . "</sitemap>\n";
			}
		}
		foreach ( hcseo_csv( 'sm_tax' ) as $tx ) { if ( taxonomy_exists( $tx ) && wp_count_terms( array( 'taxonomy' => $tx, 'hide_empty' => true ) ) ) { echo '<sitemap><loc>' . hcseo_u( home_url( "/sitemap-tax-$tx-1.xml" ) ) . "</loc></sitemap>\n"; } }
		foreach ( array( 'news' => 'sm_news', 'video' => 'sm_video', 'rss' => 'sm_rss' ) as $n => $opt ) { if ( hcseo_opt( $opt ) ) { echo '<sitemap><loc>' . hcseo_u( home_url( "/sitemap-$n.xml" ) ) . "</loc></sitemap>\n"; } }
		echo '</sitemapindex>';
		exit;
	}
	if ( 'post' === $sm ) {
		$pt = sanitize_key( get_query_var( 'hcseo_o' ) ); $pg = max( 1, (int) get_query_var( 'hcseo_pg' ) );
		if ( ! in_array( $pt, hcseo_csv( 'sm_types' ), true ) ) { echo "<urlset $ns/>"; exit; }
		$img = hcseo_opt( 'sm_images' );
		echo "<urlset $ns" . ( $img ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '' ) . ">\n";
		if ( 'page' === $pt && 1 === $pg ) { echo '<url><loc>' . hcseo_u( home_url( '/' ) ) . "</loc><changefreq>daily</changefreq><priority>1.0</priority></url>\n"; }
		foreach ( get_posts( hcseo_indexable_args( $pt, $pg ) ) as $p ) {
			echo '<url><loc>' . hcseo_u( get_permalink( $p ) ) . '</loc><lastmod>' . esc_html( get_post_modified_time( 'c', true, $p ) ) . '</lastmod>';
			if ( $img ) {
				$ids = array_filter( array_merge( array( get_post_thumbnail_id( $p ) ), 'product' === $pt && ( $pr = wc_get_product( $p->ID ) ) ? $pr->get_gallery_image_ids() : array() ) );
				foreach ( array_slice( $ids, 0, 8 ) as $iid ) { if ( $u = wp_get_attachment_url( $iid ) ) { echo '<image:image><image:loc>' . hcseo_u( $u ) . '</image:loc></image:image>'; } }
			}
			echo "</url>\n";
		}
		echo '</urlset>';
		exit;
	}
	if ( 'tax' === $sm ) {
		$tx = sanitize_key( get_query_var( 'hcseo_o' ) );
		echo "<urlset $ns>\n";
		if ( in_array( $tx, hcseo_csv( 'sm_tax' ), true ) ) {
			foreach ( (array) get_terms( array( 'taxonomy' => $tx, 'hide_empty' => true, 'number' => HCSEO_SM_LIMIT ) ) as $t ) {
				if ( is_wp_error( $t ) || get_term_meta( $t->term_id, '_hcseo_noindex', true ) ) { continue; }
				echo '<url><loc>' . hcseo_u( get_term_link( $t ) ) . "</loc></url>\n";
			}
		}
		echo '</urlset>';
		exit;
	}
	if ( 'news' === $sm && hcseo_opt( 'sm_news' ) ) {
		echo "<urlset $ns xmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\">\n";
		$pub = hcseo_opt( 'sm_news_pub' ) ?: get_bloginfo( 'name' );
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1000, 'date_query' => array( array( 'after' => '48 hours ago' ) ) ) ) as $p ) {
			if ( get_post_meta( $p->ID, '_hcseo_noindex', true ) ) { continue; }
			echo '<url><loc>' . hcseo_u( get_permalink( $p ) ) . '</loc><news:news><news:publication><news:name>' . esc_html( $pub ) . '</news:name><news:language>' . esc_html( substr( get_locale(), 0, 2 ) ) . '</news:language></news:publication><news:publication_date>' . esc_html( get_post_time( 'c', true, $p ) ) . '</news:publication_date><news:title>' . esc_html( get_the_title( $p ) ) . "</news:title></news:news></url>\n";
		}
		echo '</urlset>';
		exit;
	}
	if ( 'video' === $sm && hcseo_opt( 'sm_video' ) ) {
		echo "<urlset $ns xmlns:video=\"http://www.google.com/schemas/sitemap-video/1.1\">\n";
		foreach ( get_posts( array( 'post_type' => hcseo_csv( 'sm_types' ), 'post_status' => 'publish', 'posts_per_page' => 500, 's' => 'youtu' ) ) as $p ) {
			if ( ! preg_match_all( '~(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{11})~', $p->post_content, $m ) ) { continue; }
			foreach ( array_unique( $m[1] ) as $vid ) {
				echo '<url><loc>' . hcseo_u( get_permalink( $p ) ) . '</loc><video:video><video:thumbnail_loc>' . hcseo_u( "https://i.ytimg.com/vi/$vid/hqdefault.jpg" ) . '</video:thumbnail_loc><video:title>' . esc_html( get_the_title( $p ) ) . '</video:title><video:description>' . esc_html( wp_trim_words( wp_strip_all_tags( $p->post_content ), 30 ) ?: get_the_title( $p ) ) . '</video:description><video:player_loc>' . hcseo_u( "https://www.youtube.com/embed/$vid" ) . "</video:player_loc></video:video></url>\n";
			}
		}
		echo '</urlset>';
		exit;
	}
	if ( 'rss' === $sm && hcseo_opt( 'sm_rss' ) ) {
		echo '<rss version="2.0"><channel><title>' . esc_html( get_bloginfo( 'name' ) ) . '</title><link>' . hcseo_u( home_url( '/' ) ) . '</link><description>' . esc_html( get_bloginfo( 'description' ) ) . "</description>\n";
		foreach ( get_posts( array( 'post_type' => hcseo_csv( 'sm_types' ), 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'modified' ) ) as $p ) {
			echo '<item><title>' . esc_html( get_the_title( $p ) ) . '</title><link>' . hcseo_u( get_permalink( $p ) ) . '</link><pubDate>' . esc_html( get_post_modified_time( 'r', true, $p ) ) . "</pubDate></item>\n";
		}
		echo '</channel></rss>';
		exit;
	}
	echo "<urlset $ns/>";
	exit;
} );

// robots.txt: Sitemap লাইন
add_filter( 'robots_txt', function ( $out ) {
	if ( hcseo_opt( 'sm_enable' ) && false === strpos( $out, 'sitemap_index.xml' ) ) { $out .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n"; }
	return $out;
}, 99 );
