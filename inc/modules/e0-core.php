<?php
/**
 * e0 — HitsCam অ্যাডমিন মেনু, ট্রাস্ট ব্যাজ (এক্সটেনসিবল), পণ্য-ভিত্তিক বাল্ক ছাড়।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/* ---------- টপ-লেভেল মেনু: অন্য মডিউলগুলো add_submenu_page('hitscam-home', …) দিয়ে যোগ করে ---------- */
add_action( 'admin_menu', function () {
	add_menu_page( 'HitsCam', 'HitsCam', 'manage_woocommerce', 'hitscam-home', 'hitscam_e0_home', 'dashicons-store', 56 );
}, 5 );

function hitscam_e0_home() {
	echo '<div class="wrap"><h1>HitsCam — কনফিগারেশন</h1><p>থিমের সব এক্সটেনসিবল সেটিংস এখানে। বাঁ পাশের সাবমেনু থেকে বেছে নিন:</p><ul style="list-style:disc;margin-left:1.5em">';
	global $submenu;
	foreach ( (array) ( $submenu['hitscam-home'] ?? array() ) as $s ) {
		if ( 'hitscam-home' === $s[2] ) { continue; }
		echo '<li><a href="' . esc_url( admin_url( false !== strpos( $s[2], '.php' ) ? $s[2] : 'admin.php?page=' . $s[2] ) ) . '">' . wp_kses_post( $s[0] ) . '</a></li>';
	}
	echo '</ul></div>';
}

/** টেক্সট-রিপিটার অপশন সেভ/রেন্ডার হেল্পার (এক লাইন = এক রো, "|" দিয়ে ভাগ) */
function hitscam_e0_lines( $opt, $default = '' ) {
	$raw  = (string) get_option( $opt, $default );
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $l ) {
		$l = trim( $l );
		if ( '' !== $l && '#' !== $l[0] ) { $rows[] = array_map( 'trim', explode( '|', $l ) ); }
	}
	return $rows;
}

/** জেনেরিক টেক্সট-সেটিংস পেজ বানানোর হেল্পার */
function hitscam_e0_textarea_page( $slug, $title, $opt, $default, $help ) {
	add_submenu_page( 'hitscam-home', $title, $title, 'manage_woocommerce', $slug, function () use ( $slug, $title, $opt, $default, $help ) {
		if ( isset( $_POST['hc_e0_save'] ) && check_admin_referer( 'hc_e0_' . $slug ) ) {
			update_option( $opt, sanitize_textarea_field( wp_unslash( $_POST['val'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
		}
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1><form method="post">';
		wp_nonce_field( 'hc_e0_' . $slug );
		echo '<div style="max-width:900px">' . wp_kses_post( $help ) . '</div><p><textarea name="val" rows="14" style="width:100%;max-width:900px;font-family:monospace">' . esc_textarea( (string) get_option( $opt, $default ) ) . '</textarea></p>';
		echo '<p><button class="button button-primary" name="hc_e0_save" value="1">সেভ</button> <a class="button" href="' . esc_url( add_query_arg( 'hc_reset', $opt ) ) . '" onclick="return confirm(\'ডিফল্টে ফিরবেন?\')">ডিফল্টে ফিরুন</a></p></form></div>';
	}, 30 );
	add_action( 'admin_init', function () use ( $opt ) {
		if ( isset( $_GET['hc_reset'] ) && $_GET['hc_reset'] === $opt && current_user_can( 'manage_woocommerce' ) ) {
			delete_option( $opt );
			wp_safe_redirect( remove_query_arg( 'hc_reset' ) );
			exit;
		}
	} );
}

/* ---------- ট্রাস্ট ব্যাজ ---------- */
const HITSCAM_TRUST_DEFAULT = "# আইকন (Bootstrap Icons)|শিরোনাম|সাব-টেক্সট|কোথায় দেখাবে (product,cart,checkout,home,thankyou — ফাঁকা = শুধু product)\nbi-truck|Fast delivery|সারা দেশে|product,cart\nbi-shield-check|Secure payment|১০০% নিরাপদ|product,cart,checkout\nbi-arrow-repeat|7-day return|সহজ রিটার্ন|product\nbi-headset|Online Support 24/7|সবসময় পাশে|product,checkout";
add_action( 'admin_menu', function () {
	hitscam_e0_textarea_page( 'hitscam-trust', 'ট্রাস্ট ব্যাজ', 'hitscam_trust', HITSCAM_TRUST_DEFAULT,
		'<p>প্রতি লাইনে একটি ব্যাজ: <code>আইকন|শিরোনাম|সাব-টেক্সট|পেজ১,পেজ২</code>। পেজ: <code>product, cart, checkout, home, shop, thankyou</code>। যত খুশি লাইন যোগ করুন; <code>#</code> দিয়ে শুরু হলে কমেন্ট। আইকনের নাম <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap Icons</a> থেকে। কোড থেকে যোগ করতে <code>add_filter(\'hitscam_trust_badges\', …)</code>।</p>' );
}, 20 );

function hitscam_trust_badges( $place = 'product' ) {
	$out = array();
	foreach ( hitscam_e0_lines( 'hitscam_trust', HITSCAM_TRUST_DEFAULT ) as $r ) {
		$pages = isset( $r[3] ) && '' !== $r[3] ? array_map( 'trim', explode( ',', $r[3] ) ) : array( 'product' );
		if ( count( $r ) >= 2 && ( in_array( $place, $pages, true ) || in_array( 'all', $pages, true ) ) ) {
			$out[] = array( 'icon' => $r[0], 'title' => $r[1], 'sub' => $r[2] ?? '' );
		}
	}
	return apply_filters( 'hitscam_trust_badges', $out, $place );
}

function hitscam_trust_row( $place = 'product', $class = 'mb-4' ) {
	$b = hitscam_trust_badges( $place );
	if ( ! $b ) { return; }
	echo '<div class="row g-3 trust-row ' . esc_attr( $class ) . ' m-slider s-md" data-autoslide>';
	foreach ( $b as $t ) {
		echo '<div class="col-6 col-lg-3"><div class="t-item"><i class="bi ' . esc_attr( $t['icon'] ) . '"></i><div><b>' . esc_html( $t['title'] ) . '</b><small class="text-muted">' . esc_html( $t['sub'] ) . '</small></div></div></div>';
	}
	echo '</div>';
}
add_action( 'woocommerce_after_cart_table', function () { hitscam_trust_row( 'cart', 'mt-3' ); } );
add_action( 'woocommerce_after_checkout_form', function () { hitscam_trust_row( 'checkout', 'mt-4' ); } );

/* ---------- পণ্য মেটাবক্স: বাল্ক ছাড় ---------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hc_pbulk', 'HitsCam — বেশি কিনলে বেশি ছাড়', function ( $post ) {
		wp_nonce_field( 'hc_pbulk', 'hc_pbulk_n' );
		$v = (string) get_post_meta( $post->ID, '_hc_bulk', true );
		echo '<p><input type="text" name="_hc_bulk" value="' . esc_attr( $v ) . '" class="widefat" placeholder="যেমন 3:5,6:8,12:12"></p><p class="description">ফরম্যাট <code>পরিমাণ:ছাড়%</code> কমা দিয়ে। ফাঁকা = গ্লোবাল সেটিং (' . esc_html( hitscam_opt( 'bulk', '5:3,10:6,20:10' ) ) . ')। <code>off</code> লিখলে এই পণ্যে বাল্ক ছাড় বন্ধ।</p>';
	}, 'product', 'side', 'default' );
} );
add_action( 'save_post_product', function ( $id ) {
	if ( ! isset( $_POST['hc_pbulk_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hc_pbulk_n'] ) ), 'hc_pbulk' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	update_post_meta( $id, '_hc_bulk', preg_replace( '/[^0-9:.,a-zA-Z]/', '', wp_unslash( $_POST['_hc_bulk'] ?? '' ) ) );
} );
