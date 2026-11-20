<?php
/**
 * a2 — অর্ডার ট্র্যাক [hitscam_track], কুরিয়ার ট্র্যাকিং (Pathao/Steadfast/RedX), সেলফ-সার্ভিস (সময়সীমার মধ্যে বাতিল/ঠিকানা-ফোন বদল)।
 * টেমপ্লেট: track-order.html + support.js (অর্ডার সেলফ-সার্ভিস)।
 */
defined( 'ABSPATH' ) || exit;

function hitscam_a2_couriers() {
	return apply_filters( 'hitscam_couriers', array(
		'pathao'    => array( 'Pathao', 'https://merchant.pathao.com/tracking?consignment_id={id}&phone={phone}' ),
		'steadfast' => array( 'Steadfast', 'https://steadfast.com.bd/t/{id}' ),
		'redx'      => array( 'RedX', 'https://redx.com.bd/track-parcel/?trackingId={id}' ),
	) );
}
function hitscam_a2_window() { return max( 0, (int) apply_filters( 'hitscam_cancel_minutes', (int) get_option( 'hitscam_cancel_min', 60 ) ) ); }

/* ---------- শর্টকোড ---------- */
add_shortcode( 'hitscam_track', function () {
	$pre = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$st  = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( 'BD' ) : array();
	ob_start(); ?>
<div class="track-wrap" data-track data-districts="<?php echo esc_attr( wp_json_encode( $st ) ); ?>" data-window="<?php echo (int) hitscam_a2_window(); ?>">
	<p class="text-muted">অর্ডার নম্বর ও ফোন নাম্বার দিয়ে আপনার অর্ডারের বর্তমান অবস্থা জানুন।</p>
	<form class="panel mb-4" id="trackForm" novalidate>
		<div class="row g-3">
			<div class="col-12 col-md-5"><label class="form-label">অর্ডার নম্বর *</label><input class="form-control form-control-lg" name="orderId" inputmode="numeric" placeholder="যেমন: 538" value="<?php echo $pre ? (int) $pre : ''; ?>" required><div class="invalid-feedback">অর্ডার নম্বর লিখুন</div></div>
			<div class="col-12 col-md-5"><label class="form-label">ফোন নাম্বার *</label><input class="form-control form-control-lg" name="phone" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" required><div class="invalid-feedback">সঠিক ফোন নাম্বার দিন</div></div>
			<div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-brand btn-lg w-100"><i class="bi bi-search"></i> ট্র্যাক</button></div>
		</div>
	</form>
	<div id="trackResult" style="display:none">
		<div class="row g-3 mb-3">
			<div class="col-6 col-md-3"><div class="or-stat"><small>অর্ডার নং</small><b id="tId"></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>তারিখ</small><b id="tDate"></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>মোট</small><b id="tTotal"></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>পেমেন্ট</small><b id="tPay"></b></div></div>
		</div>
		<div class="panel mb-3"><h5 class="fw-bold">📍 অর্ডার ট্র্যাকিং</h5><p class="mb-3">বর্তমান স্ট্যাটাস: <span class="status-pill" id="tStatus"></span></p><ul class="timeline timeline-h mt-3 mb-0" id="tLine"></ul></div>
		<div class="mb-3 text-end"><button type="button" class="btn btn-soft rounded-pill reorder-btn" id="tReorder" data-id=""><i class="bi bi-arrow-repeat"></i> আবার অর্ডার করুন</button></div>
		<div class="panel mb-3" id="tCourier" style="display:none"><h5 class="fw-bold mb-2"><i class="bi bi-truck text-brand"></i> কুরিয়ার ট্র্যাকিং</h5>
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><b id="tCName"></b> · কনসাইনমেন্ট <b class="user-select-all" id="tCCn"></b></div><a class="btn btn-brand btn-sm rounded-pill" id="tCLink" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> কুরিয়ারের সাইটে ট্র্যাক করুন</a></div></div>
		<div class="panel my-3" id="selfServ" style="display:none"></div>
		<div class="panel mb-5"><h5 class="fw-bold mb-3">🛍️ আপনার প্রোডাক্ট (<span id="tCount"></span>)</h5><div id="tItems"></div><hr style="border-style:dashed">
			<div class="totals-row"><span>Subtotal:</span><span id="tSub"></span></div><div class="totals-row"><span>Shipping:</span><span id="tShip"></span></div><div class="totals-row total"><span>Total:</span><span id="tTotal2" class="text-brand"></span></div></div>
	</div>
</div>
	<?php
	return ob_get_clean();
} );

/* ---------- অর্ডার ডেটা (JSON) ---------- */
function hitscam_a2_money( $n ) { return html_entity_decode( wp_strip_all_tags( wc_price( $n ) ), ENT_QUOTES, 'UTF-8' ); }

function hitscam_a2_order_payload( WC_Order $o ) {
	$status = $o->get_status(); $cur = 1;
	if ( $o->get_meta( '_hc_reconfirmed' ) ) { $cur = 2; }
	$cid = $o->get_meta( '_hc_consignment' ); $ck = $o->get_meta( '_hc_courier' );
	if ( $cid || in_array( $status, array( 'shipped', 'out-for-delivery' ), true ) ) { $cur = 3; }
	if ( 'completed' === $status ) { $cur = 4; }
	$cr = null; $co = hitscam_a2_couriers();
	if ( $cid && $ck && isset( $co[ $ck ] ) ) {
		$cr = array( 'name' => $co[ $ck ][0], 'cn' => $cid, 'url' => str_replace( array( '{id}', '{phone}' ), array( rawurlencode( $cid ), rawurlencode( $o->get_billing_phone() ) ), $co[ $ck ][1] ) );
	}
	$items = array();
	foreach ( $o->get_items() as $it ) {
		$p = $it->get_product();
		$items[] = array( 'name' => $it->get_name(), 'img' => $p ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '', 'qty' => $it->get_quantity(), 'total' => hitscam_a2_money( $it->get_total() ) );
	}
	$left = hitscam_a2_cancel_left( $o );
	return array(
		'id' => $o->get_order_number(), 'oid' => $o->get_id(), 'date' => wc_format_datetime( $o->get_date_created(), 'F j, Y' ),
		'total' => hitscam_a2_money( $o->get_total() ), 'pay' => ( $o->get_payment_method_title() ?: '—' ),
		'status' => 'cancelled' === $status ? 'Cancelled' : ( $o->get_meta( '_hc_reconfirmed' ) && 'processing' === $status ? 'Confirmed' : wc_get_order_status_name( $status ) ),
		'cancelled' => 'cancelled' === $status, 'cur' => $cur, 'courier' => $cr, 'items' => $items,
		'sub' => hitscam_a2_money( $o->get_subtotal() ), 'ship' => (float) $o->get_shipping_total() > 0 ? hitscam_a2_money( $o->get_shipping_total() ) : 'Free shipping',
		'manage' => $left, 'cancelReason' => (string) $o->get_meta( '_hc_cancel_reason' ),
		'addr' => array( 'road' => $o->get_billing_address_1(), 'thana' => $o->get_billing_city(), 'state' => $o->get_billing_state(), 'phone' => $o->get_billing_phone() ),
		'rma' => hitscam_page_url( 'rma' ) . '?o=' . $o->get_id(),
	);
}

/** বাকি মিনিট (বাতিল/বদল সম্ভব হলে >0, নইলে 0) */
function hitscam_a2_cancel_left( WC_Order $o ) {
	if ( ! in_array( $o->get_status(), array( 'pending', 'processing', 'on-hold' ), true ) || $o->get_meta( '_hc_consignment' ) ) { return 0; }
	$w = hitscam_a2_window(); $t = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
	return (int) max( 0, ceil( ( $w * 60 - ( time() - $t ) ) / 60 ) );
}

function hitscam_a2_get_order() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! hitscam_a2_limit( 'trk', 40, 600 ) ) { wp_send_json_error( array( 'msg' => 'কিছুক্ষণ পরে চেষ্টা করুন।' ) ); }
	$key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
	$o   = null;
	if ( $key && ( $oid = absint( $_POST['oid'] ?? 0 ) ) && ( $x = wc_get_order( $oid ) ) && hash_equals( $x->get_order_key(), $key ) ) { $o = $x; }
	if ( ! $o ) { $o = hitscam_find_order( wp_unslash( $_POST['orderId'] ?? '' ), wp_unslash( $_POST['phone'] ?? '' ) ); }
	if ( ! $o ) { wp_send_json_error( array( 'msg' => 'অর্ডার নম্বর ও ফোন নাম্বার মিলছে না' ) ); }
	return $o;
}

add_action( 'wp_ajax_hc_track', function () { $o = hitscam_a2_get_order(); wp_send_json_success( hitscam_a2_order_payload( $o ) ); } );
add_action( 'wp_ajax_nopriv_hc_track', function () { $o = hitscam_a2_get_order(); wp_send_json_success( hitscam_a2_order_payload( $o ) ); } );

/* ---------- সেলফ-সার্ভিস: বাতিল / ঠিকানা বদল ---------- */
function hitscam_a2_ajax_cancel() {
	$o = hitscam_a2_get_order();
	if ( ! hitscam_a2_cancel_left( $o ) ) { wp_send_json_error( array( 'msg' => 'বাতিলের সময় শেষ। WhatsApp এ জানান।' ) ); }
	$reasons = array( 'ভুল পণ্য বেছেছি', 'দাম/অন্য জায়গায় কম পেয়েছি', 'ডেলিভারি ঠিকানা ভুল', 'আর দরকার নেই', 'অন্য কারণ' );
	$r = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ); $r = in_array( $r, $reasons, true ) ? $r : 'অন্য কারণ';
	$o->update_meta_data( '_hc_cancel_reason', $r );
	$o->update_status( 'cancelled', 'গ্রাহক নিজে বাতিল করেছেন: ' . $r );
	do_action( 'hitscam_order_self_cancelled', $o, $r );
	wp_send_json_success( hitscam_a2_order_payload( wc_get_order( $o->get_id() ) ) );
}
add_action( 'wp_ajax_hc_ss_cancel', 'hitscam_a2_ajax_cancel' );
add_action( 'wp_ajax_nopriv_hc_ss_cancel', 'hitscam_a2_ajax_cancel' );

function hitscam_a2_ajax_edit() {
	$o = hitscam_a2_get_order();
	if ( ! hitscam_a2_cancel_left( $o ) ) { wp_send_json_error( array( 'msg' => 'সম্পাদনার সময় শেষ।' ) ); }
	$road = sanitize_text_field( wp_unslash( $_POST['road'] ?? '' ) ); $thana = sanitize_text_field( wp_unslash( $_POST['thana'] ?? '' ) );
	$dist = sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) ); $ph = hitscam_normalize_bd_phone( wp_unslash( $_POST['newphone'] ?? '' ) );
	if ( ! $road || ! $thana ) { wp_send_json_error( array( 'msg' => 'রোড ও থানা লিখুন' ) ); }
	if ( ! $ph ) { wp_send_json_error( array( 'msg' => 'সঠিক মোবাইল নাম্বার দিন' ) ); }
	foreach ( array( 'billing', 'shipping' ) as $t ) {
		$o->{"set_{$t}_address_1"}( $road ); $o->{"set_{$t}_city"}( $thana );
		if ( $dist ) { $o->{"set_{$t}_state"}( $dist ); }
	}
	$o->set_billing_phone( $ph );
	$o->add_order_note( 'গ্রাহক ঠিকানা/ফোন বদলেছেন।' );
	$o->save();
	wp_send_json_success( hitscam_a2_order_payload( $o ) );
}
add_action( 'wp_ajax_hc_ss_edit', 'hitscam_a2_ajax_edit' );
add_action( 'wp_ajax_nopriv_hc_ss_edit', 'hitscam_a2_ajax_edit' );

/** অর্ডার-রিসিভড পেজে সেলফ-সার্ভিস মাউন্ট */
add_filter( 'the_content', function ( $c ) {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) && in_the_loop() && is_main_query() ) {
		$st = WC()->countries->get_states( 'BD' );
		$o = wc_get_order( absint( get_query_var( 'order-received' ) ) ); $pid = 0;
		if ( $o ) { foreach ( $o->get_items() as $it ) { $pid = $it->get_product_id(); break; } }
		$c .= '<div id="hcSelfMount" data-pid="' . (int) $pid . '" data-districts="' . esc_attr( wp_json_encode( $st ) ) . '" hidden></div>';
	}
	return $c;
}, 99 );

/* ---------- অ্যাডমিন: কুরিয়ার মেটাবক্স ---------- */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $s ) {
		add_meta_box( 'hc_courier', 'কুরিয়ার (HitsCam)', 'hitscam_a2_courier_box', $s, 'side', 'default' );
	}
} );
function hitscam_a2_courier_box( $post_or_order ) {
	$o = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $o ) { return; }
	wp_nonce_field( 'hc_courier_save', 'hc_courier_n' );
	$cur = $o->get_meta( '_hc_courier' );
	echo '<p><label>কুরিয়ার<br><select name="hc_courier" style="width:100%"><option value="">—</option>';
	foreach ( hitscam_a2_couriers() as $k => $c ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $c[0] ) . '</option>'; }
	echo '</select></label></p><p><label>কনসাইনমেন্ট নং<br><input type="text" name="hc_consignment" style="width:100%" value="' . esc_attr( $o->get_meta( '_hc_consignment' ) ) . '"></label></p>';
}
add_action( 'woocommerce_process_shop_order_meta', 'hitscam_a2_courier_save' );
function hitscam_a2_courier_save( $id ) {
	if ( ! isset( $_POST['hc_courier_n'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hc_courier_n'] ), 'hc_courier_save' ) || ! current_user_can( 'edit_shop_orders' ) ) { return; }
	$o = wc_get_order( $id ); if ( ! $o ) { return; }
	$k = sanitize_key( wp_unslash( $_POST['hc_courier'] ?? '' ) ); $c = sanitize_text_field( wp_unslash( $_POST['hc_consignment'] ?? '' ) );
	$o->update_meta_data( '_hc_courier', isset( hitscam_a2_couriers()[ $k ] ) ? $k : '' );
	$o->update_meta_data( '_hc_consignment', $c );
	$o->save_meta_data();
}
