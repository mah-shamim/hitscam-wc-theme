<?php
/** WooCommerce ইন্টিগ্রেশন: লেআউট র‍্যাপার, ফ্র্যাগমেন্ট, চেকআউট ফিল্ড, ফিল্টার। */
defined( 'ABSPATH' ) || exit;

if ( ! hitscam_is_wc() ) {
	return;
}

// ── লেআউট র‍্যাপার
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
add_action( 'woocommerce_before_main_content', function () {
	echo '<main id="main" class="container pb-5">';
	if ( ! is_front_page() ) {
		hitscam_breadcrumbs();
	}
}, 10 );
add_action( 'woocommerce_after_main_content', function () {
	echo '</main>';
}, 10 );

add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'loop_shop_per_page', fn() => 12 );
add_filter( 'loop_shop_columns', fn() => 4 );
add_filter( 'woocommerce_output_related_products_args', fn( $a ) => array_merge( $a, array( 'posts_per_page' => 4, 'columns' => 4 ) ) );
add_filter( 'woocommerce_pagination_args', function ( $a ) {
	$a['prev_text'] = '‹';
	$a['next_text'] = '›';
	return $a;
} );

/** ব্রেডক্রাম্ব (টেমপ্লেটের breadcrumb-bar স্টাইলে) */
function hitscam_breadcrumbs() {
	$crumbs = array( array( 'Home', home_url( '/' ) ) );
	if ( is_singular( 'product' ) ) {
		$terms = get_the_terms( get_the_ID(), 'product_cat' );
		$crumbs[] = array( 'শপ', wc_get_page_permalink( 'shop' ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$t = $terms[0];
			$crumbs[] = array( $t->name, get_term_link( $t ) );
		}
		$crumbs[] = array( get_the_title(), '' );
	} elseif ( is_product_category() || is_product_tag() ) {
		$crumbs[] = array( 'শপ', wc_get_page_permalink( 'shop' ) );
		$crumbs[] = array( single_term_title( '', false ), '' );
	} elseif ( is_shop() ) {
		$crumbs[] = array( 'শপ', '' );
	} elseif ( is_search() ) {
		$crumbs[] = array( 'খোঁজ: ' . get_search_query(), '' );
	} elseif ( is_singular() ) {
		$crumbs[] = array( get_the_title(), '' );
	} elseif ( is_archive() ) {
		$crumbs[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	}
	echo '<div class="breadcrumb-bar" aria-label="breadcrumb">';
	$out = array();
	foreach ( $crumbs as $c ) {
		$out[] = $c[1] ? '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>' : '<span>' . esc_html( $c[0] ) . '</span>';
	}
	echo implode( ' / ', $out ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

// ── কার্ট ফ্র্যাগমেন্ট: কাউন্টার ও ফ্রি-শিপিং বার
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$n = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$f['span.cart-count'] = '<span class="badge-count cart-count">' . (int) $n . '</span>';
	ob_start();
	hitscam_free_ship_bar();
	$f['div.fs-wrap'] = ob_get_clean();
	return $f;
} );

function hitscam_free_ship_bar() {
	$limit = (int) hitscam_opt( 'freeship', 5000 );
	echo '<div class="fs-wrap">';
	if ( $limit > 0 && WC()->cart && ! WC()->cart->is_empty() ) {
		$sub  = (float) WC()->cart->get_displayed_subtotal();
		$left = max( 0, $limit - $sub );
		$pct  = min( 100, round( $sub / $limit * 100 ) );
		echo '<div class="free-ship ' . ( $left > 0 ? 'need' : 'ok' ) . '"><div class="fs-msg small mb-1">';
		if ( $left > 0 ) {
			echo 'আর <b class="text-brand">' . wp_kses_post( wc_price( $left ) ) . '</b> কিনলেই ফ্রি ডেলিভারি!';
		} else {
			echo '<i class="bi bi-check-circle-fill text-success"></i> অভিনন্দন! আপনার অর্ডারে <b>ফ্রি ডেলিভারি</b>।';
		}
		echo '</div><div class="progress" style="height:6px" role="progressbar" aria-label="ফ্রি ডেলিভারি অগ্রগতি" aria-valuenow="' . (int) $pct . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-danger" style="width:' . (int) $pct . '%"></div></div></div>';
	}
	echo '</div>';
}
add_action( 'woocommerce_before_cart', 'hitscam_free_ship_bar', 5 );
add_action( 'woocommerce_before_checkout_form', 'hitscam_free_ship_bar', 5 );

// ── শিপিং: ফ্রি শিপিং থাকলে বাকি লুকানো (ক্লাসিক আচরণ)
add_filter( 'woocommerce_package_rates', function ( $rates ) {
	$free = array();
	foreach ( $rates as $id => $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			$free[ $id ] = $rate;
		}
	}
	return $free ? $free : $rates;
}, 100 );

// ── চেকআউট ফিল্ড: বাংলাদেশ-বান্ধব
add_filter( 'woocommerce_checkout_fields', function ( $f ) {
	unset( $f['billing']['billing_company'], $f['billing']['billing_address_2'], $f['shipping']['shipping_company'], $f['shipping']['shipping_address_2'] );
	$f['billing']['billing_first_name']['label'] = 'আপনার নাম';
	$f['billing']['billing_last_name']['required'] = false;
	$f['billing']['billing_last_name']['label']    = 'নামের শেষাংশ (ঐচ্ছিক)';
	$f['billing']['billing_phone']['label']        = 'মোবাইল নম্বর';
	$f['billing']['billing_phone']['placeholder']  = '01XXXXXXXXX';
	$f['billing']['billing_phone']['required']     = true;
	$f['billing']['billing_phone']['custom_attributes'] = array( 'inputmode' => 'tel', 'autocomplete' => 'tel' );
	$f['billing']['billing_phone']['priority']     = 25;
	$f['billing']['billing_email']['required']     = false;
	$f['billing']['billing_email']['label']        = 'ইমেইল (ঐচ্ছিক)';
	$f['billing']['billing_address_1']['label']    = 'পূর্ণ ঠিকানা (বাসা/রোড/এলাকা)';
	$f['billing']['billing_postcode']['required']  = false;
	$f['billing']['billing_city']['label']         = 'থানা/উপজেলা';
	if ( isset( $f['order']['order_comments'] ) ) {
		$f['order']['order_comments']['label']       = 'অর্ডার নোট (ঐচ্ছিক)';
		$f['order']['order_comments']['placeholder'] = 'ডেলিভারি সম্পর্কে বিশেষ নির্দেশনা…';
	}
	return $f;
} );
add_filter( 'woocommerce_default_address_fields', function ( $f ) {
	if ( isset( $f['state'] ) ) {
		$f['state']['label'] = 'জেলা';
	}
	return $f;
} );
add_filter( 'woocommerce_billing_fields', function ( $f ) {
	if ( isset( $f['billing_state'] ) ) {
		$f['billing_state']['label'] = 'জেলা';
	}
	return $f;
} );

/** বাংলাদেশি ফোন নম্বর: ০১৩–০১৯ + ৮ ডিজিট; +880/880 প্রিফিক্স গ্রহণযোগ্য (বাংলা অঙ্কও) */
function hitscam_normalize_bd_phone( $raw ) {
	$raw = strtr( (string) $raw, array( '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9' ) );
	$raw = preg_replace( '/[\s\-()]/', '', $raw );
	$raw = preg_replace( '/^\+?88/', '', $raw );
	return preg_match( '/^01[3-9]\d{8}$/', $raw ) ? $raw : '';
}
add_action( 'woocommerce_checkout_process', function () {
	$phone = isset( $_POST['billing_phone'] ) ? wp_unslash( $_POST['billing_phone'] ) : ''; // phpcs:ignore WordPress.Security
	if ( $phone && ! hitscam_normalize_bd_phone( $phone ) ) {
		wc_add_notice( 'সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)।', 'error' );
	}
} );
add_filter( 'woocommerce_process_checkout_field_billing_phone', function ( $v ) {
	$n = hitscam_normalize_bd_phone( $v );
	return $n ? $n : $v;
} );

// ── শপ ফিল্টার: স্টকে আছে + রেটিং + দাম (WC নিজেই min_price/max_price/rating_filter বোঝে)
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'product' ) || $q->is_tax( array( 'product_cat', 'product_tag' ) ) || ( $q->is_search() && 'product' === $q->get( 'post_type' ) ) ) ) {
		return;
	}
	if ( ! empty( $_GET['instock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array( 'key' => '_stock_status', 'value' => 'instock' );
		$q->set( 'meta_query', $mq );
	}
} );

// ── সিঙ্গেল প্রোডাক্ট: ট্রাস্ট লাইন + WhatsApp অর্ডার
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	$wa  = preg_replace( '/\D/', '', (string) hitscam_opt( 'whatsapp', '8801614747054' ) );
	$msg = rawurlencode( sprintf( "আমি এই পণ্যটি অর্ডার করতে চাই:\n%s\n%s", $product->get_name(), get_permalink( $product->get_id() ) ) );
	echo '<a class="btn btn-wa-outline btn-lg rounded-pill w-100 mt-2" id="pWa" href="https://wa.me/' . esc_attr( $wa ) . '?text=' . $msg . '" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp এ অর্ডার করুন</a>';
	echo '<ul class="meta-list mt-3"><li><i class="bi bi-truck text-brand"></i> সারাদেশে ডেলিভারি, ক্যাশ অন ডেলিভারি</li><li><i class="bi bi-shield-check text-brand"></i> অরিজিনাল পণ্য ও বিক্রয়োত্তর সাপোর্ট</li><li><i class="bi bi-arrow-repeat text-brand"></i> ৭ দিনের মধ্যে রিটার্ন/রিপ্লেসমেন্ট</li></ul>';
}, 35 );
