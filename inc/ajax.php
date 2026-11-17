<?php
/** AJAX/REST: কুইক ভিউ, উইশলিস্ট কার্ড, তুলনা টেবিল, Banglish-সহনশীল সার্চ। */
defined( 'ABSPATH' ) || exit;

if ( ! hitscam_is_wc() ) {
	return;
}

// ── সার্চ: Banglish → বাংলা + এডিট-দূরত্ব ১
function hitscam_search_dict() {
	return apply_filters( 'hitscam_search_dict', array(
		'kamera' => 'ক্যামেরা', 'camera' => 'ক্যামেরা', 'camra' => 'ক্যামেরা', 'cam' => 'ক্যামেরা', 'cctv' => 'সিসি', 'sisi' => 'সিসি', 'cc' => 'সিসি', 'ip' => 'আইপি', 'aipi' => 'আইপি',
		'memory' => 'মেমোরি', 'memori' => 'মেমোরি', 'memry' => 'মেমোরি', 'card' => 'কার্ড', 'kard' => 'কার্ড', 'ups' => 'ইউপিএস', 'mini' => 'মিনি', 'solar' => 'সোলার', 'sola' => 'সোলার',
		'phone' => 'ফোন', 'fon' => 'ফোন', 'smartphone' => 'স্মার্টফোন', 'laptop' => 'ল্যাপটপ', 'mount' => 'মাউন্ট', 'security' => 'সিকিউরিটি', 'sikiuriti' => 'সিকিউরিটি',
		'gimbal' => 'গিম্বল', 'accessories' => 'অ্যাক্সেসরিজ',
	) );
}

function hitscam_near( $a, $b ) {
	if ( $a === $b ) {
		return true;
	}
	return abs( strlen( $a ) - strlen( $b ) ) <= 1 && levenshtein( $a, $b ) <= 1;
}

/** টোকেন → বিকল্প শব্দ তালিকা */
function hitscam_expand_token( $t ) {
	$dict = hitscam_search_dict();
	$out  = array( $t );
	if ( isset( $dict[ $t ] ) ) {
		$out[] = $dict[ $t ];
	} elseif ( strlen( $t ) >= 4 && preg_match( '/^[a-z]+$/', $t ) ) {
		foreach ( $dict as $k => $v ) {
			if ( strlen( $k ) >= 4 && hitscam_near( $t, $k ) ) {
				$out[] = $v;
				$out[] = $k;
			}
		}
	}
	return array_values( array_unique( $out ) );
}

/** posts_search: প্রতিটি টোকেন (বা তার বিকল্প) শিরোনাম/সারাংশ/বিবরণ/SKU তে থাকতে হবে */
function hitscam_posts_search( $search, $q ) {
	global $wpdb;
	if ( ! $q->is_search() || 'product' !== $q->get( 'post_type' ) ) {
		return $search;
	}
	$s = trim( (string) $q->get( 's' ) );
	if ( '' === $s ) {
		return $search;
	}
	$tokens = array_filter( preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $s ) ) );
	$and    = array();
	foreach ( $tokens as $t ) {
		$or = array();
		foreach ( hitscam_expand_token( $t ) as $alt ) {
			$like = '%' . $wpdb->esc_like( $alt ) . '%';
			$or[] = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_sku' AND pm.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id JOIN {$wpdb->terms} te ON te.term_id = tt.term_id WHERE tr.object_id = {$wpdb->posts}.ID AND tt.taxonomy = 'product_cat' AND te.name LIKE %s))", $like, $like, $like, $like, $like ); // phpcs:ignore
		}
		$and[] = '(' . implode( ' OR ', $or ) . ')';
	}
	return $and ? ' AND ' . implode( ' AND ', $and ) . " AND {$wpdb->posts}.post_password = '' " : $search;
}
add_filter( 'posts_search', 'hitscam_posts_search', 10, 2 );

// হেডার সার্চ সবসময় product সার্চ
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_search() && ! $q->get( 'post_type' ) && isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) { // phpcs:ignore
		$q->set( 'post_type', 'product' );
	}
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'hitscam-theme/v1', '/suggest', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
		'callback'            => function ( WP_REST_Request $r ) {
			$q = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 's' => $r['q'], 'posts_per_page' => 6, 'no_found_rows' => true, 'suppress_filters' => false ) );
			$out = array();
			foreach ( $q->posts as $p ) {
				$pr    = wc_get_product( $p );
				$out[] = array( 'id' => $p->ID, 'name' => $pr->get_name(), 'url' => get_permalink( $p ), 'price' => wp_strip_all_tags( html_entity_decode( $pr->get_price_html() ) ), 'img' => get_the_post_thumbnail_url( $p, 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ) );
			}
			return rest_ensure_response( $out );
		},
	) );
} );

// ── কুইক ভিউ
function hitscam_ajax_check() {
	check_ajax_referer( 'hitscam', 'nonce' );
}
add_action( 'wp_ajax_hc_quickview', 'hitscam_ajax_quickview' );
add_action( 'wp_ajax_nopriv_hc_quickview', 'hitscam_ajax_quickview' );
function hitscam_ajax_quickview() {
	hitscam_ajax_check();
	$p = wc_get_product( absint( $_POST['id'] ?? 0 ) );
	if ( ! $p || 'publish' !== get_post_status( $p->get_id() ) ) {
		wp_send_json_error( 'not found', 404 );
	}
	ob_start();
	?>
	<div class="row g-3"><div class="col-md-6"><a href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>"><?php echo wp_kses_post( $p->get_image( 'woocommerce_single', array( 'class' => 'img-fluid rounded-3' ) ) ); ?></a></div>
	<div class="col-md-6"><h2 class="h4 fw-bold"><?php echo esc_html( $p->get_name() ); ?></h2>
	<div class="mb-2"><?php echo wp_kses_post( wc_get_rating_html( $p->get_average_rating() ) ); ?></div>
	<div class="prod-price mb-2"><?php echo wp_kses_post( $p->get_price_html() ); ?></div>
	<div class="mb-3 text-muted small"><?php echo wp_kses_post( wp_trim_words( $p->get_short_description() ?: $p->get_description(), 35 ) ); ?></div>
	<?php echo hitscam_stock_html( $p ); // phpcs:ignore ?>
	<div class="d-flex gap-2 mt-3"><?php if ( $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock() ) : ?>
		<button class="btn btn-brand ajax_add_to_cart add_to_cart_button" type="button" data-product_id="<?php echo (int) $p->get_id(); ?>" data-quantity="1" data-hc-close="1">অ্যাড টু কার্ট</button>
	<?php endif; ?><a class="btn btn-brand-outline" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">বিস্তারিত দেখুন</a></div></div></div>
	<?php
	wp_send_json_success( ob_get_clean() );
}

// ── উইশলিস্ট কার্ড
add_action( 'wp_ajax_hc_cards', 'hitscam_ajax_cards' );
add_action( 'wp_ajax_nopriv_hc_cards', 'hitscam_ajax_cards' );
function hitscam_ajax_cards() {
	hitscam_ajax_check();
	$ids = array_slice( array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) ), 0, 40 );
	if ( ! $ids ) {
		wp_send_json_success( '' );
	}
	$q = new WP_Query( array( 'post_type' => 'product', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => 40, 'no_found_rows' => true ) );
	ob_start();
	echo '<div class="row g-3">';
	global $product;
	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<div class="col-12 col-sm-6 col-lg-3">';
		wc_get_template_part( 'content', 'product' );
		echo '</div>';
	}
	echo '</div>';
	wp_reset_postdata();
	wp_send_json_success( ob_get_clean() );
}

// ── তুলনা টেবিল
add_action( 'wp_ajax_hc_compare', 'hitscam_ajax_compare' );
add_action( 'wp_ajax_nopriv_hc_compare', 'hitscam_ajax_compare' );
function hitscam_ajax_compare() {
	hitscam_ajax_check();
	$ids = array_slice( array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) ), 0, 3 );
	$ps  = array_filter( array_map( 'wc_get_product', $ids ) );
	if ( ! $ps ) {
		wp_send_json_success( '<p class="text-muted">তুলনার জন্য কোনো পণ্য বাছাই করা হয়নি। কার্ডের <i class="bi bi-arrow-left-right"></i> বাটনে ক্লিক করুন।</p>' );
	}
	$attrs = array();
	foreach ( $ps as $p ) {
		foreach ( $p->get_attributes() as $a ) {
			$label = wc_attribute_label( is_object( $a ) ? $a->get_name() : $a['name'], $p );
			$attrs[ $label ] = true;
		}
	}
	$row = function ( $label, $cb ) use ( $ps ) {
		$vals = array_map( $cb, $ps );
		$diff = count( array_unique( array_map( 'wp_strip_all_tags', $vals ) ) ) > 1 ? ' class="diff"' : '';
		return '<tr' . $diff . '><th scope="row">' . esc_html( $label ) . '</th><td>' . implode( '</td><td>', $vals ) . '</td></tr>';
	};
	ob_start();
	echo '<div class="table-responsive"><table class="table cmp-table align-middle"><thead><tr><th></th>';
	foreach ( $ps as $p ) {
		echo '<th><a href="' . esc_url( get_permalink( $p->get_id() ) ) . '">' . wp_kses_post( $p->get_image( 'thumbnail' ) ) . '<div>' . esc_html( $p->get_name() ) . '</div></a><button type="button" class="btn btn-sm btn-soft mt-1 cmp-remove" data-id="' . (int) $p->get_id() . '">সরান</button></th>';
	}
	echo '</tr></thead><tbody>';
	echo $row( 'দাম', fn( $p ) => wp_kses_post( $p->get_price_html() ) ); // phpcs:ignore
	echo $row( 'রেটিং', fn( $p ) => $p->get_average_rating() ? esc_html( hitscam_bn_digits( $p->get_average_rating() ) ) . ' (' . esc_html( hitscam_bn_digits( $p->get_review_count() ) ) . ')' : '—' ); // phpcs:ignore
	echo $row( 'স্টক', fn( $p ) => $p->is_in_stock() ? 'আছে' : 'শেষ' ); // phpcs:ignore
	echo $row( 'SKU', fn( $p ) => esc_html( $p->get_sku() ?: '—' ) ); // phpcs:ignore
	echo $row( 'ক্যাটেগরি', fn( $p ) => wp_kses_post( wc_get_product_category_list( $p->get_id() ) ?: '—' ) ); // phpcs:ignore
	foreach ( array_keys( $attrs ) as $label ) {
		echo $row( $label, function ( $p ) use ( $label ) { // phpcs:ignore
			foreach ( $p->get_attributes() as $a ) {
				$name = is_object( $a ) ? $a->get_name() : $a['name'];
				if ( wc_attribute_label( $name, $p ) === $label ) {
					return esc_html( $p->get_attribute( $name ) ?: '—' );
				}
			}
			return '—';
		} );
	}
	echo '<tr><th></th>';
	foreach ( $ps as $p ) {
		echo '<td><a class="btn btn-brand btn-sm" href="' . esc_url( $p->add_to_cart_url() ) . '">' . esc_html( $p->add_to_cart_text() ) . '</a></td>';
	}
	echo '</tr></tbody></table></div>';
	wp_send_json_success( ob_get_clean() );
}
