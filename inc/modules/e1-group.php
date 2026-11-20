<?php
/**
 * গ্রুপড প্রোডাক্ট UI (টেমপ্লেটের group-table: নাম + দাম + পরিমাণ স্টেপার) এবং ট্রাস্ট ব্যাজ।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/** গ্রুপড চাইল্ড সারি। $min = ডিফল্টভাবে ১টি করে সিলেক্ট হওয়া সারির ইনডেক্স */
function hitscam_group_rows( array $vars, $min = 0, $compact = false ) {
	ob_start();
	echo '<table class="table group-table align-middle grp-table' . ( $compact ? ' grp-compact' : '' ) . '"><tbody>';
	foreach ( $vars as $i => $v ) {
		$q = ( $i === $min && $v['stock'] ) ? 1 : 0;
		echo '<tr class="grp-row' . ( $v['stock'] ? '' : ' na' ) . '" data-i="' . (int) $i . '" data-id="' . (int) $v['id'] . '" data-child="' . (int) ( $v['child'] ?? 0 ) . '" data-price="' . esc_attr( $v['price'] ) . '" data-max="' . (int) ( $v['max'] ?? 0 ) . '">';
		echo '<td class="g-name">' . esc_html( $v['label'] ) . ( $v['stock'] ? '' : ' <small class="text-muted">(স্টক শেষ)</small>' ) . '</td>';
		echo '<td class="g-price">' . ( $v['regular'] > $v['price'] ? '<del class="text-muted small">' . esc_html( hitscam_money( $v['regular'] ) ) . '</del> ' : '' ) . '<b class="text-brand">' . esc_html( hitscam_money( $v['price'] ) ) . '</b></td>';
		echo '<td class="g-qty"><div class="qty"><button type="button" class="qty-btn g-d" data-d="-1" aria-label="কমান"' . ( $v['stock'] ? '' : ' disabled' ) . '>−</button><span class="g-q">' . (int) $q . '</span><button type="button" class="qty-btn g-d" data-d="1" aria-label="বাড়ান"' . ( $v['stock'] ? '' : ' disabled' ) . '>+</button></div></td></tr>';
	}
	echo '</tbody></table>';
	return ob_get_clean();
}

if ( ! function_exists( 'hitscam_money' ) ) {
	function hitscam_money( $n ) {
		return '৳' . number_format( (float) $n, 0, '.', ',' );
	}
}

/** AJAX: গ্রুপড — একাধিক চাইল্ড একসাথে কার্টে */
function hitscam_ajax_add_group() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
	if ( ! is_array( $items ) || ! $items ) {
		wp_send_json_error( array( 'msg' => 'কমপক্ষে একটি আইটেম বেছে নিন।' ) );
	}
	$added = 0;
	foreach ( array_slice( $items, 0, 20 ) as $it ) {
		$id    = absint( $it['id'] ?? 0 );
		$child = absint( $it['child'] ?? 0 );
		$qty   = min( 99, max( 1, absint( $it['qty'] ?? 1 ) ) );
		$p     = wc_get_product( $child ?: $id );
		if ( ! $p || ! $p->is_purchasable() ) {
			continue;
		}
		$vid = 0;
		$att = array();
		if ( $child ) { // ভ্যারিয়েবল চাইল্ডের ভ্যারিয়েশন
			$vp = wc_get_product( $id );
			if ( ! $vp || (int) $vp->get_parent_id() !== $child ) {
				continue;
			}
			$vid = $id;
			$att = $vp->get_variation_attributes();
		}
		if ( WC()->cart->add_to_cart( $child ?: $id, $qty, $vid, $att ) ) {
			$added++;
		}
	}
	if ( ! $added ) {
		$errs = wc_get_notices( 'error' );
		wc_clear_notices();
		wp_send_json_error( array( 'msg' => $errs ? wp_strip_all_tags( $errs[0]['notice'] ) : 'কার্টে যোগ করা যায়নি।' ) );
	}
	WC()->cart->calculate_totals();
	ob_start();
	woocommerce_mini_cart();
	$mini = ob_get_clean();
	$frag = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content flex-grow-1">' . $mini . '</div>' ) );
	wp_send_json_success( array( 'fragments' => $frag, 'cart_hash' => WC()->cart->get_cart_hash(), 'redirect' => ! empty( $_POST['buy_now'] ) ? wc_get_checkout_url() : '', 'count' => WC()->cart->get_cart_contents_count() ) );
}
add_action( 'wp_ajax_hc_add_group', 'hitscam_ajax_add_group' );
add_action( 'wp_ajax_nopriv_hc_add_group', 'hitscam_ajax_add_group' );
