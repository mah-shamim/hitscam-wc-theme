<?php
/** থিম-স্তরের ফিচার: বাল্ক ছাড়, স্টক লেবেল। (ট্র্যাকিং/SEO/API আলাদা প্লাগইনে) */
defined( 'ABSPATH' ) || exit;

if ( ! hitscam_is_wc() ) {
	return;
}

/** পরিমাণ অনুযায়ী ছাড়ের শতাংশ */
function hitscam_bulk_pct( $qty ) {
	$pct = 0;
	foreach ( hitscam_bulk_tiers() as $t ) {
		if ( $qty >= $t[0] ) {
			$pct = $t[1];
		}
	}
	return $pct;
}

/** পরের ধাপ: [min, pct] বা null */
function hitscam_next_tier( $qty ) {
	foreach ( hitscam_bulk_tiers() as $t ) {
		if ( $qty < $t[0] ) {
			return $t;
		}
	}
	return null;
}

add_action( 'woocommerce_cart_calculate_fees', function ( WC_Cart $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	if ( ! hitscam_bulk_tiers() ) {
		return;
	}
	$discount = 0;
	foreach ( $cart->get_cart() as $item ) {
		$pct = hitscam_bulk_pct( (int) $item['quantity'] );
		if ( $pct > 0 ) {
			$discount += (float) $item['line_subtotal'] * $pct / 100;
		}
	}
	if ( $discount > 0 ) {
		$cart->add_fee( 'বাল্ক ছাড়', -round( $discount ), false );
	}
} );

// কার্টে পরের ধাপের ইঙ্গিত
add_filter( 'woocommerce_cart_item_name', function ( $name, $item ) {
	if ( ! is_cart() || ! hitscam_bulk_tiers() ) {
		return $name;
	}
	$n = hitscam_next_tier( (int) $item['quantity'] );
	if ( $n ) {
		$name .= '<div class="small text-success mt-1"><i class="bi bi-tag-fill"></i> আর ' . hitscam_bn_digits( $n[0] - (int) $item['quantity'] ) . ' পিস নিলে ' . hitscam_bn_digits( rtrim( rtrim( (string) $n[1], '0' ), '.' ) ) . '% ছাড়</div>';
	}
	return $name;
}, 10, 2 );

// প্রোডাক্ট পেজে বাল্ক টেবিল (সিম্পল/ভ্যারিয়েবল)
function hitscam_bulk_box() {
	$t = hitscam_bulk_tiers();
	if ( ! $t ) {
		return;
	}
	echo '<div class="bulk-box mt-3"><div class="small fw-bold mb-1"><i class="bi bi-boxes"></i> বেশি কিনলে বেশি ছাড়</div><div class="d-flex flex-wrap gap-2">';
	foreach ( $t as $row ) {
		echo '<span class="bulk-chip">' . esc_html( hitscam_bn_digits( $row[0] ) ) . '+ পিস → <b>' . esc_html( hitscam_bn_digits( rtrim( rtrim( (string) $row[1], '0' ), '.' ) ) ) . '%</b></span>';
	}
	echo '</div></div>';
}

/** স্টক লেবেল (লো/শেষ/আছে) */
function hitscam_stock_html( WC_Product $p ) {
	if ( ! $p->is_in_stock() ) {
		return '<div class="stock out"><i class="bi bi-x-circle"></i> স্টক শেষ</div>';
	}
	if ( $p->managing_stock() && $p->get_stock_quantity() !== null && $p->get_stock_quantity() <= (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) ) {
		return '<div class="stock low"><i class="bi bi-exclamation-triangle-fill"></i> মাত্র ' . esc_html( hitscam_bn_digits( $p->get_stock_quantity() ) ) . 'টি বাকি</div>';
	}
	return '<div class="stock ok"><i class="bi bi-check-circle-fill"></i> স্টকে আছে</div>';
}
