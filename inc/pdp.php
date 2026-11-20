<?php
/** কার্ড অপশন/ইনফো, ভ্যারিয়েশন ডেটা, কার্ড-অ্যাড (অর্ডার নাউ সহ), লিড (স্টক/দাম অ্যালার্ট)। */
defined( 'ABSPATH' ) || exit;

if ( ! hitscam_is_wc() ) {
	return;
}

/** অ্যাট্রিবিউট নাম → আইকন */
function hitscam_attr_icon( $name ) {
	$n   = strtolower( $name );
	$map = array( 'video|resolution|ভিডিও|কোয়ালিটি|display|screen' => 'bi-camera-video', 'wifi|connect|কানেক্ট|network|sim|4g|bluetooth' => 'bi-wifi', 'storage|memory|স্টোরেজ|কার্ড|capacity|ram|rom' => 'bi-sd-card', 'power|battery|পাওয়ার|ব্যাটারি|volt|solar|সোলার' => 'bi-lightning-charge', 'warranty|ওয়ারেন্টি|guarantee' => 'bi-shield-check', 'app|অ্যাপ|android|ios' => 'bi-phone', 'color|colour|রং' => 'bi-palette', 'size|সাইজ|dimension' => 'bi-aspect-ratio', 'night|নাইট|vision' => 'bi-moon-stars', 'audio|মাইক|sound' => 'bi-mic' );
	foreach ( $map as $re => $ic ) {
		if ( preg_match( '/' . $re . '/u', $n ) ) {
			return $ic;
		}
	}
	return 'bi-check2-circle';
}

/** কার্ডের info তালিকা: ভ্যারিয়েশন-নয় এমন অ্যাট্রিবিউট [icon, text] */
function hitscam_card_info( WC_Product $p, $max = 4 ) {
	$out = array();
	foreach ( $p->get_attributes() as $a ) {
		if ( ! is_object( $a ) || $a->get_variation() || ! $a->get_visible() ) {
			continue;
		}
		$vals = $a->is_taxonomy() ? wc_get_product_terms( $p->get_id(), $a->get_name(), array( 'fields' => 'names' ) ) : $a->get_options();
		$txt  = trim( implode( ' / ', array_map( 'wp_strip_all_tags', (array) $vals ) ) );
		if ( '' !== $txt ) {
			$out[] = array( hitscam_attr_icon( wc_attribute_label( $a->get_name() ) ), $txt );
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/** ভ্যারিয়েবল প্রোডাক্টের ভ্যারিয়েশন তালিকা (কার্ড/PDP উভয়ের জন্য) */
function hitscam_variations( WC_Product $p ) {
	if ( $p->is_type( 'grouped' ) ) { // গ্রুপড: টেমপ্লেটের মতো — প্রতিটি চাইল্ড একটি অপশন (রেডিও), বেছে নেওয়াটাই কার্টে যায়
		$out = array();
		foreach ( $p->get_children() as $cid ) {
			$cp = wc_get_product( $cid );
			// ক্যাটালগে "লুকানো" চাইল্ডও অপশন হবে (গ্রুপের চাইল্ড সাধারণত লুকানো থাকে); শুধু পাবলিশড হলেই হয়
			if ( ! $cp || 'publish' !== $cp->get_status() ) {
				continue;
			}
			$img = $cp->get_image_id() ? wp_get_attachment_image_url( $cp->get_image_id(), 'full' ) : '';
			if ( $cp->is_type( 'variable' ) ) { // ভ্যারিয়েবল চাইল্ড: প্রতিটি ভ্যারিয়েশন আলাদা অপশন
				foreach ( $cp->get_available_variations() as $v ) {
					$vp = wc_get_product( $v['variation_id'] );
					if ( ! $vp ) {
						continue;
					}
					$lbl = array();
					foreach ( $vp->get_variation_attributes() as $k => $val ) {
						$tax   = str_replace( 'attribute_', '', $k );
						$term  = $val && taxonomy_exists( $tax ) ? get_term_by( 'slug', $val, $tax ) : null;
						$lbl[] = $term ? $term->name : $val;
					}
					$out[] = array(
						'id' => $vp->get_id(), 'child' => $cp->get_id(), 'label' => $cp->get_name() . ( array_filter( $lbl ) ? ' — ' . implode( ' / ', array_filter( $lbl ) ) : '' ), 'price' => (float) $vp->get_price(), 'regular' => (float) $vp->get_regular_price(), 'html' => $vp->get_price_html(),
						'stock' => $vp->is_in_stock() && $vp->is_purchasable(), 'attrs' => array( 'group' => (string) $vp->get_id() ), 'image' => ! empty( $v['image']['full_src'] ) ? $v['image']['full_src'] : (string) $img, 'sku' => $vp->get_sku(), 'max' => $vp->managing_stock() ? (int) $vp->get_stock_quantity() : 0, 'grp' => 1,
					);
				}
				continue;
			}
			$out[] = array(
				'id' => $cp->get_id(), 'child' => 0, 'label' => $cp->get_name(), 'price' => (float) $cp->get_price(), 'regular' => (float) $cp->get_regular_price(), 'html' => $cp->get_price_html(),
				'stock' => $cp->is_in_stock() && $cp->is_purchasable(), 'attrs' => array( 'group' => (string) $cp->get_id() ), 'image' => (string) $img, 'sku' => $cp->get_sku(), 'max' => $cp->managing_stock() ? (int) $cp->get_stock_quantity() : 0, 'grp' => 1,
			);
		}
		return $out;
	}
	if ( ! $p->is_type( 'variable' ) ) {
		return array();
	}
	$out = array();
	foreach ( $p->get_available_variations() as $v ) {
		$vp = wc_get_product( $v['variation_id'] );
		if ( ! $vp ) {
			continue;
		}
		$lbl = array();
		foreach ( $vp->get_variation_attributes() as $k => $val ) {
			if ( '' === $val ) {
				continue;
			}
			$tax   = str_replace( 'attribute_', '', $k );
			$term  = taxonomy_exists( $tax ) ? get_term_by( 'slug', $val, $tax ) : null;
			$lbl[] = $term ? $term->name : $val;
		}
		$out[] = array(
			'id'      => $vp->get_id(),
			'label'   => $lbl ? implode( ' / ', $lbl ) : ( '#' . $vp->get_id() ),
			'price'   => (float) $vp->get_price(),
			'regular' => (float) $vp->get_regular_price(),
			'html'    => $vp->get_price_html(),
			'stock'   => $vp->is_in_stock(),
			'attrs'   => $v['attributes'],
			'image'   => ! empty( $v['image']['full_src'] ) ? $v['image']['full_src'] : '',
			'sku'     => $vp->get_sku(),
			'max'     => $vp->managing_stock() ? (int) $vp->get_stock_quantity() : 0,
		);
	}
	return $out;
}

/** পণ্য কার্টে কতটি আছে */
function hitscam_in_cart_qty( $product_id ) {
	$n = 0;
	if ( WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $it ) {
			if ( (int) $it['product_id'] === (int) $product_id ) {
				$n += (int) $it['quantity'];
			}
		}
	}
	return $n;
}

/* ---------- AJAX: কার্ডে/PDP-তে অ্যাড (এবং অর্ডার নাউ) ---------- */
function hitscam_ajax_add() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$pid = absint( $_POST['product_id'] ?? 0 );
	$vid = absint( $_POST['variation_id'] ?? 0 );
	$qty = max( 1, absint( $_POST['quantity'] ?? 1 ) );
	$p   = wc_get_product( $pid );
	if ( ! $p || ! $p->is_purchasable() ) {
		wp_send_json_error( array( 'msg' => 'পণ্যটি কেনা যাচ্ছে না।' ) );
	}
	$attrs = array();
	if ( $vid ) {
		$vp = wc_get_product( $vid );
		if ( ! $vp || (int) $vp->get_parent_id() !== $pid ) {
			wp_send_json_error( array( 'msg' => 'অপশন সঠিক নয়।' ) );
		}
		$attrs = $vp->get_variation_attributes();
		foreach ( (array) ( $_POST['attrs'] ?? array() ) as $k => $v ) {
			$k = sanitize_title( wp_unslash( $k ) );
			if ( isset( $attrs[ $k ] ) && '' === $attrs[ $k ] ) {
				$attrs[ $k ] = sanitize_text_field( wp_unslash( $v ) );
			}
		}
	} elseif ( $p->is_type( 'variable' ) ) {
		wp_send_json_error( array( 'msg' => 'অপশন বাছাই করুন।' ) );
	}
	$ok = WC()->cart->add_to_cart( $pid, $qty, $vid, $attrs );
	if ( ! $ok ) {
		$errs = wc_get_notices( 'error' );
		wc_clear_notices();
		wp_send_json_error( array( 'msg' => $errs ? wp_strip_all_tags( $errs[0]['notice'] ) : 'কার্টে যোগ করা যায়নি।' ) );
	}
	foreach ( array_filter( array_map( 'absint', (array) ( $_POST['extra'] ?? array() ) ) ) as $x ) {
		$xp = wc_get_product( $x );
		if ( $xp && $xp->is_type( 'simple' ) && $xp->is_purchasable() && $xp->is_in_stock() ) {
			WC()->cart->add_to_cart( $x, 1 );
		}
	}
	WC()->cart->calculate_totals();
	ob_start();
	woocommerce_mini_cart();
	$mini = ob_get_clean();
	$frag = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content flex-grow-1">' . $mini . '</div>' ) );
	wp_send_json_success( array(
		'fragments' => $frag,
		'cart_hash' => WC()->cart->get_cart_hash(),
		'redirect'  => ! empty( $_POST['buy_now'] ) ? wc_get_checkout_url() : '',
		'count'     => WC()->cart->get_cart_contents_count(),
	) );
}
add_action( 'wp_ajax_hc_add', 'hitscam_ajax_add' );
add_action( 'wp_ajax_nopriv_hc_add', 'hitscam_ajax_add' );

/* ---------- AJAX: মিনি-কার্ডে পরিমাণ পরিবর্তন ---------- */
function hitscam_ajax_cart_qty() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
	$d   = (int) ( $_POST['d'] ?? 0 );
	$it  = WC()->cart->get_cart_item( $key );
	if ( ! $it ) {
		wp_send_json_error();
	}
	$q = (int) $it['quantity'] + $d;
	if ( $q <= 0 ) {
		WC()->cart->remove_cart_item( $key );
	} else {
		WC()->cart->set_quantity( $key, $q, true );
	}
	WC()->cart->calculate_totals();
	ob_start();
	woocommerce_mini_cart();
	$mini = ob_get_clean();
	$frag = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content flex-grow-1">' . $mini . '</div>' ) );
	wp_send_json_success( array( 'fragments' => $frag, 'cart_hash' => WC()->cart->get_cart_hash() ) );
}
add_action( 'wp_ajax_hc_cart_qty', 'hitscam_ajax_cart_qty' );
add_action( 'wp_ajax_nopriv_hc_cart_qty', 'hitscam_ajax_cart_qty' );

/* ---------- লিড: স্টকে এলে জানান / দাম কমলে জানান ---------- */
add_action( 'init', function () {
	register_post_type( 'hc_lead', array(
		'labels' => array( 'name' => 'অ্যালার্ট লিড', 'singular_name' => 'লিড' ), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'woocommerce',
		'supports' => array( 'title', 'editor' ), 'capability_type' => 'post', 'map_meta_cap' => true,
	) );
} );
function hitscam_ajax_lead() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$phone = preg_replace( '/[\s-]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	if ( ! preg_match( '/^(?:\+?88)?01[3-9]\d{8}$/', $phone ) ) {
		wp_send_json_error( array( 'msg' => 'সঠিক বাংলাদেশি নাম্বার দিন' ) );
	}
	$pid  = absint( $_POST['product_id'] ?? 0 );
	$type = 'price' === ( $_POST['type'] ?? '' ) ? 'দাম কমলে' : 'স্টকে এলে';
	$tgt  = isset( $_POST['target'] ) ? (float) $_POST['target'] : 0;
	$id   = wp_insert_post( array( 'post_type' => 'hc_lead', 'post_status' => 'publish', 'post_title' => $type . ' — ' . $phone . ' — ' . get_the_title( $pid ), 'post_content' => $tgt ? 'টার্গেট দাম: ' . $tgt : '' ) );
	if ( $id ) {
		update_post_meta( $id, '_phone', $phone );
		update_post_meta( $id, '_product', $pid );
		do_action( 'hitscam_lead_created', $id, $type, $phone, $pid, $tgt );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_hc_lead', 'hitscam_ajax_lead' );
add_action( 'wp_ajax_nopriv_hc_lead', 'hitscam_ajax_lead' );

/* ---------- ফ্র্যাগমেন্ট: "কার্টে আছে" বার ও কার্ডের 'ভিউ কার্ট' অবস্থা ---------- */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$ids = array();
	foreach ( WC()->cart->get_cart() as $it ) {
		$ids[ (int) $it['product_id'] ] = ( $ids[ (int) $it['product_id'] ] ?? 0 ) + (int) $it['quantity'];
	}
	$f['div.hc-incart-data'] = '<div class="hc-incart-data" hidden data-ids="' . esc_attr( wp_json_encode( (object) $ids ) ) . '"></div>';
	return $f;
} );

/* ---------- অর্ডার রিসিভড: রিকনফার্ম / আবার SMS / আবার অর্ডার ---------- */
function hitscam_order_from_post() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$o = wc_get_order( absint( $_POST['order'] ?? 0 ) );
	if ( ! $o || ! hash_equals( (string) $o->get_order_key(), (string) wp_unslash( $_POST['key'] ?? '' ) ) ) {
		wp_send_json_error( array( 'msg' => 'অর্ডার যাচাই হয়নি।' ) );
	}
	return $o;
}
add_action( 'wp_ajax_hc_reconfirm', 'hitscam_ajax_reconfirm' );
add_action( 'wp_ajax_nopriv_hc_reconfirm', 'hitscam_ajax_reconfirm' );
function hitscam_ajax_reconfirm() {
	$o = hitscam_order_from_post();
	if ( ! $o->get_meta( '_hc_reconfirmed' ) ) {
		$o->update_meta_data( '_hc_reconfirmed', time() );
		$o->add_order_note( 'গ্রাহক অর্ডার রিকনফার্ম করেছেন (অর্ডার রিসিভড পেজ থেকে)।' );
		$o->save();
		do_action( 'hitscam_order_reconfirmed', $o );
		hitscam_try_sms( $o->get_billing_phone(), 'HitsCam: ধন্যবাদ! আপনার অর্ডার #' . $o->get_order_number() . ' রিকনফার্ম হয়েছে। শীঘ্রই প্যাকেজিং শুরু হবে।' );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_hc_resend_sms', 'hitscam_ajax_resend_sms' );
add_action( 'wp_ajax_nopriv_hc_resend_sms', 'hitscam_ajax_resend_sms' );
function hitscam_ajax_resend_sms() {
	$o = hitscam_order_from_post();
	$last = (int) $o->get_meta( '_hc_sms_last' );
	if ( time() - $last < 15 ) { wp_send_json_error( array( 'msg' => 'একটু পরে আবার চেষ্টা করুন।' ) ); }
	$o->update_meta_data( '_hc_sms_last', time() ); $o->save();
	hitscam_try_sms( $o->get_billing_phone(), 'HitsCam: আপনার অর্ডার #' . $o->get_order_number() . ' গ্রহণ করা হয়েছে। আমাদের প্রতিনিধি কল করবেন।' );
	wp_send_json_success();
}
add_action( 'wp_ajax_hc_reorder', 'hitscam_ajax_reorder' );
add_action( 'wp_ajax_nopriv_hc_reorder', 'hitscam_ajax_reorder' );
function hitscam_ajax_reorder() {
	$o = hitscam_order_from_post();
	foreach ( $o->get_items() as $it ) {
		$pid = $it->get_product_id(); $vid = $it->get_variation_id();
		$p = wc_get_product( $vid ?: $pid );
		if ( $p && $p->is_purchasable() && $p->is_in_stock() ) {
			WC()->cart->add_to_cart( $pid, $it->get_quantity(), $vid, $vid ? $p->get_variation_attributes() : array() );
		}
	}
	wp_send_json_success( array( 'redirect' => wc_get_cart_url() ) );
}
