<?php
/**
 * WooCommerce সক্রিয় হলে একবার: বাংলাদেশ-ডিফল্ট, পেজ, মেনু, COD, ফ্রি শিপিং জোন।
 * সবকিছু idempotent — বারবার চললেও ডুপ্লিকেট হয় না, ব্যবহারকারীর বদলানো সেটিং ওভাররাইট করে না (শুধু প্রথমবার)।
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( get_option( 'hitscam_store_setup_pending' ) && hitscam_is_wc() && current_user_can( 'manage_options' ) ) {
		delete_option( 'hitscam_store_setup_pending' );
		hitscam_run_store_setup();
	}
} );

// WooCommerce আগে থেকেই চালু থাকলে (প্লাগইন ইনস্টল করার দরকার হয়নি) থিম অ্যাক্টিভেশনে সেটআপ ফ্ল্যাগ
add_action( 'after_switch_theme', function () {
	if ( ! get_option( 'hitscam_store_setup_done' ) ) {
		update_option( 'hitscam_store_setup_pending', 1 );
	}
} );

function hitscam_run_store_setup() {
	if ( get_option( 'hitscam_store_setup_done' ) ) {
		return;
	}
	// ১) বাংলাদেশ ডিফল্ট
	if ( ! get_option( 'woocommerce_store_address' ) ) {
		update_option( 'woocommerce_default_country', 'BD:BD-13' ); // ঢাকা
		update_option( 'woocommerce_currency', 'BDT' );
		update_option( 'woocommerce_price_num_decimals', 0 );
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', array( 'BD' ) );
		update_option( 'woocommerce_ship_to_countries', '' );
		update_option( 'woocommerce_specific_ship_to_countries', array( 'BD' ) );
		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
		update_option( 'woocommerce_enable_reviews', 'yes' );
		update_option( 'timezone_string', 'Asia/Dhaka' );
		update_option( 'WPLANG', get_option( 'WPLANG' ) ); // ভাষা বদলাই না
	}
	// ২) ক্যাশ অন ডেলিভারি চালু
	$cod = (array) get_option( 'woocommerce_cod_settings', array() );
	if ( empty( $cod['enabled'] ) ) {
		$cod['enabled']      = 'yes';
		$cod['title']        = 'ক্যাশ অন ডেলিভারি';
		$cod['description']  = 'পণ্য হাতে পেয়ে টাকা দিন।';
		update_option( 'woocommerce_cod_settings', $cod );
	}
	// ৩) পেজ (WC নিজের: shop, cart, checkout, myaccount, refund policy…)
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::create_pages();
	}
	// ক্লাসিক (শর্টকোড) কার্ট/চেকআউট: BD ফিল্ড কাস্টমাইজেশন (ফোন বাধ্যতামূলক ইত্যাদি) ব্লক-চেকআউটে কাজ করে না
	foreach ( array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ) as $k => $sc ) {
		$pid = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( $k ) : 0;
		if ( $pid > 0 && false !== strpos( (string) get_post_field( 'post_content', $pid ), 'wp:woocommerce' ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_content' => $sc ) );
		}
	}
	// ক্লাসিক শর্টকোড চেকআউট/কার্ট: BD ফোন/জেলা ফিল্ড ফিল্টার ব্লক-চেকআউটে কাজ করে না
	foreach ( array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ) as $k => $sc ) {
		$pid = wc_get_page_id( $k );
		if ( $pid > 0 && false !== strpos( (string) get_post_field( 'post_content', $pid ), 'wp:woocommerce' ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_content' => $sc ) );
		}
	}
	$pages = array(
		'track-order' => array( 'অর্ডার ট্র্যাক', '[woocommerce_order_tracking]', '' ),
		'compare'     => array( 'পণ্য তুলনা', '', 'page-templates/compare.php' ),
		'faq'         => array( 'FAQ ও হেল্প সেন্টার', '[hitscam_faq]', 'page-templates/faq.php' ),
		'contact'     => array( 'যোগাযোগ', '[hitscam_contact]', '' ),
		'wishlist'    => array( 'উইশলিস্ট', '', 'page-templates/wishlist.php' ),
	);
	$ids = array();
	foreach ( $pages as $slug => $p ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$ids[ $slug ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $p[0], 'post_name' => $slug, 'post_content' => $p[1] ) );
		if ( $id && ! is_wp_error( $id ) ) {
			if ( $p[2] ) {
				update_post_meta( $id, '_wp_page_template', $p[2] );
			}
			$ids[ $slug ] = $id;
		}
	}
	update_option( 'hitscam_page_ids', $ids );
	if ( ! get_option( 'page_on_front' ) ) {
		$home = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'হোম', 'post_name' => 'home', 'post_content' => '' ) );
		if ( $home && ! is_wp_error( $home ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
		}
	}
	// ৪) প্রধান মেনু
	if ( ! has_nav_menu( 'primary' ) ) {
		$m = wp_create_nav_menu( 'প্রধান মেনু' );
		if ( ! is_wp_error( $m ) ) {
			$items = array(
				array( 'হোম', home_url( '/' ) ),
				array( 'সব প্রোডাক্ট', wc_get_page_permalink( 'shop' ) ),
				array( 'পণ্য তুলনা', isset( $ids['compare'] ) ? get_permalink( $ids['compare'] ) : '' ),
				array( 'অর্ডার ট্র্যাক', isset( $ids['track-order'] ) ? get_permalink( $ids['track-order'] ) : '' ),
				array( 'যোগাযোগ', isset( $ids['contact'] ) ? get_permalink( $ids['contact'] ) : '' ),
				array( 'FAQ', isset( $ids['faq'] ) ? get_permalink( $ids['faq'] ) : '' ),
			);
			foreach ( $items as $it ) {
				if ( $it[1] ) {
					wp_update_nav_menu_item( $m, 0, array( 'menu-item-title' => $it[0], 'menu-item-url' => $it[1], 'menu-item-status' => 'publish' ) );
				}
			}
			$loc            = get_theme_mod( 'nav_menu_locations', array() );
			$loc['primary'] = $m;
			set_theme_mod( 'nav_menu_locations', $loc );
		}
	}
	// ৫) ফ্রি শিপিং (ঢাকা ভেতর/বাইরে ফ্ল্যাট) — শুধু শিপিং জোন একেবারে না থাকলে
	if ( class_exists( 'WC_Shipping_Zones' ) && ! WC_Shipping_Zones::get_zones() ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'বাংলাদেশ' );
		$zone->add_location( 'BD', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'flat_rate' );
		$zone->add_shipping_method( 'free_shipping' );
		foreach ( $zone->get_shipping_methods() as $method ) {
			if ( 'flat_rate' === $method->id ) {
				$opt = get_option( $method->get_instance_option_key(), array() );
				$opt['title'] = 'স্ট্যান্ডার্ড ডেলিভারি';
				$opt['cost']  = '120';
				update_option( $method->get_instance_option_key(), $opt );
			} elseif ( 'free_shipping' === $method->id ) {
				$opt = get_option( $method->get_instance_option_key(), array() );
				$opt['title']      = 'ফ্রি ডেলিভারি';
				$opt['requires']   = 'min_amount';
				$opt['min_amount'] = (string) (int) hitscam_opt( 'freeship', 5000 );
				update_option( $method->get_instance_option_key(), $opt );
			}
		}
	}
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( 'woocommerce_store_pages_only', 'no' );
	update_option( 'hitscam_store_setup_done', 1 );
	update_option( "woocommerce_queue_flush_rewrite_rules", "yes" );
	delete_option( "rewrite_rules" );
}
