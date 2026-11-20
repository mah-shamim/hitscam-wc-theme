<?php
/**
 * a2 — পেমেন্ট ও চেকআউট গার্ড: COD + অগ্রিম, বিকাশ/নগদ (TrxID + স্লিপ), অনলাইন পেমেন্ট (প্লাগেবল ডেমো গেটওয়ে),
 * ফেক-অর্ডার গার্ড (ফোন/দিন লিমিট, উচ্চমূল্য COD এ অগ্রিম বাধ্যতামূলক, হানিপট)। টেমপ্লেট: app.js (PAY/GUARD) + pay-demo.html।
 * গেটওয়ে নম্বর/অগ্রিমের পরিমাণ: WooCommerce → সেটিংস → পেমেন্ট। অনলাইন গেটওয়ে প্লাগ করতে: add_filter('hitscam_online_pay_url', fn($url,$order)=>…)
 */
defined( 'ABSPATH' ) || exit;

function hitscam_a2_guard_cfg() {
	return array( 'max' => (int) get_option( 'hitscam_guard_max', 3 ), 'above' => (float) get_option( 'hitscam_guard_cod_above', 10000 ) );
}

add_filter( 'hitscam_extra_pages', function ( $p ) {
	$p['pay-demo'] = array( 'অনলাইন পেমেন্ট', '[hitscam_paydemo]' );
	return $p;
} );

/* ---------- স্লিপ আপলোড (ক্লাসিক চেকআউট ফাইল পাঠায় না, তাই আলাদা AJAX) ---------- */
function hitscam_a2_ajax_slip() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! hitscam_a2_limit( 'slip', 10, HOUR_IN_SECONDS ) ) { wp_send_json_error( array( 'msg' => 'কিছুক্ষণ পরে চেষ্টা করুন।' ) ); }
	if ( empty( $_FILES['slip']['name'] ) || $_FILES['slip']['size'] > 5 * MB_IN_BYTES ) { wp_send_json_error( array( 'msg' => 'স্লিপের ছবি বা PDF আপলোড করুন (সর্বোচ্চ ৫MB)' ) ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$up = wp_handle_upload( $_FILES['slip'], array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf' ) ) ); // phpcs:ignore
	if ( empty( $up['url'] ) ) { wp_send_json_error( array( 'msg' => 'ফাইলটি গ্রহণযোগ্য নয়' ) ); }
	$tok = wp_generate_password( 20, false );
	set_transient( 'hcslip_' . $tok, $up['url'], DAY_IN_SECONDS );
	wp_send_json_success( array( 'token' => $tok ) );
}
add_action( 'wp_ajax_hc_slip', 'hitscam_a2_ajax_slip' );
add_action( 'wp_ajax_nopriv_hc_slip', 'hitscam_a2_ajax_slip' );

/* ---------- গেটওয়ে ---------- */
add_filter( 'woocommerce_payment_gateways', function ( $g ) {
	hitscam_a2_define_gateways();
	return array_merge( $g, array( 'HitsCam_GW_CodAdv', 'HitsCam_GW_Bkash', 'HitsCam_GW_Nagad', 'HitsCam_GW_Online' ) );
} );

function hitscam_a2_define_gateways() {
	if ( class_exists( 'HitsCam_GW_Base' ) || ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	abstract class HitsCam_GW_Base extends WC_Payment_Gateway {
		protected $mobile = true; // TrxID + স্লিপ লাগবে

		public function init_gw( $id, $title, $desc, $icon_title ) {
			$this->id = $id; $this->method_title = $title; $this->method_description = $desc; $this->has_fields = true;
			$this->init_form_fields(); $this->init_settings();
			$this->title = $this->get_option( 'title', $icon_title ); $this->description = '';
			$this->enabled = $this->get_option( 'enabled', 'yes' );
			add_action( 'woocommerce_update_options_payment_gateways_' . $id, array( $this, 'process_admin_options' ) );
		}
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array( 'title' => 'চালু', 'type' => 'checkbox', 'label' => 'চালু করুন', 'default' => 'yes' ),
				'title'   => array( 'title' => 'শিরোনাম', 'type' => 'text', 'default' => $this->title ),
				'number'  => array( 'title' => 'পার্সোনাল নাম্বার', 'type' => 'text', 'default' => '01XXXXXXXXX', 'description' => 'গ্রাহক এখানে Send Money করবে (বিকাশ/নগদ)।' ),
			);
		}
		protected function mobile_extra( $m, $amt_label ) {
			$num = $m ? $this->num( $m ) : '';
			echo '<div class="pay-extra" data-pay-extra>';
			echo '<div class="small mb-2" data-pay-hint>' . ( $num ? '<b>' . esc_html( $m ) . '</b> পার্সোনাল নাম্বার <b class="text-brand user-select-all">' . esc_html( $num ) . '</b> এ <b>Send Money</b> করে নিচে TrxID ও স্লিপ দিন। ' . wp_kses_post( $amt_label ) : '' ) . '</div>';
			echo '<div class="mb-2"><label class="form-label mb-1">ট্রানজেকশন আইডি (TrxID) *</label><input class="form-control" name="hc_txn_' . esc_attr( $this->id ) . '" autocomplete="off" autocapitalize="characters" placeholder="যেমন: 8N7A6B5C4D"></div>';
			echo '<div><label class="form-label mb-1">ট্রানজেকশন স্লিপ / স্ক্রিনশট আপলোড *</label><input class="form-control" type="file" data-hc-slip accept="image/*,application/pdf"><input type="hidden" name="hc_slip_token_' . esc_attr( $this->id ) . '" value=""><div class="small text-danger" data-slip-msg></div><div class="mt-2" data-slip-prev></div></div></div>';
		}
		protected function num( $m ) {
			$g = array( 'বিকাশ' => 'hc_bkash', 'নগদ' => 'hc_nagad' );
			$o = get_option( 'woocommerce_' . ( $g[ $m ] ?? '' ) . '_settings', array() );
			return isset( $o['number'] ) ? $o['number'] : '01XXXXXXXXX';
		}
		public function validate_fields() {
			if ( ! $this->mobile ) { return true; }
			$tx = strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'hc_txn_' . $this->id ] ?? '' ) ) ); // phpcs:ignore
			$tk = sanitize_text_field( wp_unslash( $_POST[ 'hc_slip_token_' . $this->id ] ?? '' ) ); // phpcs:ignore
			$ok = true;
			if ( ! preg_match( '/^[A-Z0-9]{6,20}$/', $tx ) ) { wc_add_notice( 'সঠিক TrxID দিন (৬–২০ অক্ষর/সংখ্যা)।', 'error' ); $ok = false; }
			if ( ! $tk || ! get_transient( 'hcslip_' . $tk ) ) { wc_add_notice( 'পেমেন্ট স্লিপের ছবি বা PDF আপলোড করুন।', 'error' ); $ok = false; }
			return $ok;
		}
		protected function save_mobile( WC_Order $o, $method ) {
			$tx = strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'hc_txn_' . $this->id ] ?? '' ) ) ); // phpcs:ignore
			$tk = sanitize_text_field( wp_unslash( $_POST[ 'hc_slip_token_' . $this->id ] ?? '' ) ); // phpcs:ignore
			$o->set_transaction_id( $tx );
			$o->update_meta_data( '_hc_trxid', $tx );
			$o->update_meta_data( '_hc_pay_method', $method );
			if ( $tk && ( $u = get_transient( 'hcslip_' . $tk ) ) ) { $o->update_meta_data( '_hc_slip_url', esc_url_raw( $u ) ); delete_transient( 'hcslip_' . $tk ); }
		}
		protected function finish( WC_Order $o, $note, $status = 'on-hold' ) {
			$o->update_status( $status, $note );
			wc_reduce_stock_levels( $o->get_id() );
			WC()->cart->empty_cart();
			return array( 'result' => 'success', 'redirect' => $this->get_return_url( $o ) );
		}
	}

	class HitsCam_GW_Bkash extends HitsCam_GW_Base {
		public function __construct() { $this->title = 'বিকাশ (পার্সোনাল)'; $this->init_gw( 'hc_bkash', 'HitsCam — বিকাশ', 'বিকাশ Send Money + TrxID + স্লিপ', $this->title ); }
		public function payment_fields() { $this->mobile_extra( 'বিকাশ', 'মোট: <b>' . wc_price( WC()->cart ? WC()->cart->get_total( 'edit' ) : 0 ) . '</b>' ); }
		public function process_payment( $oid ) { $o = wc_get_order( $oid ); $this->save_mobile( $o, 'বিকাশ' ); $o->update_meta_data( '_hc_advance', 0 ); return $this->finish( $o, 'বিকাশ পেমেন্ট — TrxID যাচাইয়ের অপেক্ষায়।' ); }
	}
	class HitsCam_GW_Nagad extends HitsCam_GW_Base {
		public function __construct() { $this->title = 'নগদ (পার্সোনাল)'; $this->init_gw( 'hc_nagad', 'HitsCam — নগদ', 'নগদ Send Money + TrxID + স্লিপ', $this->title ); }
		public function payment_fields() { $this->mobile_extra( 'নগদ', 'মোট: <b>' . wc_price( WC()->cart ? WC()->cart->get_total( 'edit' ) : 0 ) . '</b>' ); }
		public function process_payment( $oid ) { $o = wc_get_order( $oid ); $this->save_mobile( $o, 'নগদ' ); $o->update_meta_data( '_hc_advance', 0 ); return $this->finish( $o, 'নগদ পেমেন্ট — TrxID যাচাইয়ের অপেক্ষায়।' ); }
	}
	class HitsCam_GW_CodAdv extends HitsCam_GW_Base {
		public function __construct() { $this->title = 'ক্যাশ অন ডেলিভারি — অগ্রিমসহ'; $this->init_gw( 'hc_codadv', 'HitsCam — COD + অগ্রিম', 'অগ্রিম বিকাশ/নগদে, বাকিটা ডেলিভারিতে', $this->title ); }
		public function init_form_fields() {
			parent::init_form_fields();
			unset( $this->form_fields['number'] );
			$this->form_fields['advance'] = array( 'title' => 'অগ্রিমের পরিমাণ (৳)', 'type' => 'number', 'default' => '500' );
		}
		public function adv( $total ) { return min( (float) $this->get_option( 'advance', 500 ), (float) $total ); }
		public function get_title() { $a = (float) $this->get_option( 'advance', 500 ); return 'ক্যাশ অন ডেলিভারি — অগ্রিম ৳' . hitscam_bn_digits( $a ) . ' বিকাশ/নগদে, বাকিটা ডেলিভারিতে'; }
		public function payment_fields() {
			$t = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0; $a = $this->adv( $t );
			echo '<div class="mb-2" data-adv-method><span class="small me-2">অগ্রিম পাঠাবেন:</span><label class="me-3"><input type="radio" class="form-check-input" name="hc_advm" value="বিকাশ" checked> বিকাশ</label><label><input type="radio" class="form-check-input" name="hc_advm" value="নগদ"> নগদ</label></div>';
			$nums = array( 'বিকাশ' => $this->num( 'বিকাশ' ), 'নগদ' => $this->num( 'নগদ' ) );
			$this->mobile_extra( 'বিকাশ', 'অগ্রিম: <b>' . wc_price( $a ) . '</b> · বাকি <b>' . wc_price( $t - $a ) . '</b> ডেলিভারিতে' );
			echo '<script type="application/json" data-adv-nums>' . wp_json_encode( array( 'nums' => $nums, 'adv' => wc_price( $a ), 'due' => wc_price( $t - $a ) ) ) . '</script>';
		}
		public function process_payment( $oid ) {
			$o = wc_get_order( $oid ); $m = sanitize_text_field( wp_unslash( $_POST['hc_advm'] ?? 'বিকাশ' ) ); // phpcs:ignore
			$m = in_array( $m, array( 'বিকাশ', 'নগদ' ), true ) ? $m : 'বিকাশ';
			$this->save_mobile( $o, $m ); $adv = $this->adv( $o->get_total() );
			$o->update_meta_data( '_hc_advance', $adv ); $o->update_meta_data( '_hc_due', (float) $o->get_total() - $adv );
			return $this->finish( $o, "COD + অগ্রিম (৳$adv, $m) — TrxID যাচাইয়ের অপেক্ষায়, বাকি ডেলিভারিতে।" );
		}
	}
	class HitsCam_GW_Online extends HitsCam_GW_Base {
		protected $mobile = false;
		public function __construct() { $this->title = 'অনলাইন পেমেন্ট'; $this->init_gw( 'hc_online', 'HitsCam — অনলাইন পেমেন্ট (ডেমো)', 'কার্ড/মোবাইল ব্যাংকিং — ডেমো গেটওয়ে পেজ। আসল গেটওয়ের জন্য hitscam_online_pay_url ফিল্টার', $this->title ); }
		public function init_form_fields() { parent::init_form_fields(); unset( $this->form_fields['number'] ); }
		public function get_title() { return 'অনলাইন পেমেন্ট (কার্ড / মোবাইল ব্যাংকিং)'; }
		public function payment_fields() { echo '<div class="small text-muted">অর্ডার কনফার্মের পর পেমেন্ট পেজে নিয়ে যাওয়া হবে।</div>'; }
		public function process_payment( $oid ) {
			$o = wc_get_order( $oid ); $o->update_status( 'pending', 'অনলাইন পেমেন্টের অপেক্ষায়।' );
			$url = add_query_arg( array( 'o' => $o->get_id(), 'key' => $o->get_order_key() ), hitscam_page_url( 'pay-demo' ) );
			return array( 'result' => 'success', 'redirect' => apply_filters( 'hitscam_online_pay_url', $url, $o ) );
		}
	}
}

/* ---------- অনলাইন ডেমো গেটওয়ে পেজ ---------- */
add_shortcode( 'hitscam_paydemo', function () {
	$oid = absint( $_GET['o'] ?? 0 ); $key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore
	$o   = $oid ? wc_get_order( $oid ) : null;
	if ( ! $o || ! hash_equals( $o->get_order_key(), $key ) || ! $o->has_status( 'pending' ) ) { return '<div class="panel text-center">কোনো অপেক্ষমাণ পেমেন্ট নেই। <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">কেনাকাটা করুন</a></div>'; }
	ob_start(); ?>
<div class="panel pay-demo" data-paydemo data-o="<?php echo (int) $oid; ?>" data-key="<?php echo esc_attr( $key ); ?>">
	<div class="alert alert-info small">এটি <b>ডেমো গেটওয়ে</b>। আসল সাইটে SSLCommerz / বিকাশ পেমেন্ট গেটওয়ের হোস্টেড পেজ এখানে বসবে; সফল/ব্যর্থ হলে গেটওয়ে আপনার সাইটে ফেরত পাঠাবে।</div>
	<div class="d-flex justify-content-between"><span>অর্ডার নং</span><b>#<?php echo esc_html( $o->get_order_number() ); ?></b></div>
	<div class="d-flex justify-content-between mb-3"><span>পরিশোধযোগ্য</span><b class="text-brand fs-4"><?php echo wp_kses_post( wc_price( $o->get_total() ) ); ?></b></div>
	<div class="mb-3"><label class="form-label fw-semibold">পেমেন্ট মাধ্যম</label>
		<label class="pay-opt"><input type="radio" class="form-check-input" name="pgm" value="বিকাশ" checked><span><i class="bi bi-wallet2 text-danger"></i> বিকাশ</span></label>
		<label class="pay-opt"><input type="radio" class="form-check-input" name="pgm" value="নগদ"><span><i class="bi bi-wallet2 text-warning"></i> নগদ</span></label>
		<label class="pay-opt"><input type="radio" class="form-check-input" name="pgm" value="কার্ড"><span><i class="bi bi-credit-card text-info"></i> ক্রেডিট / ডেবিট কার্ড</span></label></div>
	<button type="button" class="btn btn-brand w-100 rounded-pill mb-2" id="pgOk"><i class="bi bi-lock-fill"></i> পেমেন্ট সম্পন্ন করুন (ডেমো)</button>
	<button type="button" class="btn btn-soft w-100 rounded-pill" id="pgFail">পেমেন্ট বাতিল / ব্যর্থ</button>
</div>
	<?php
	return ob_get_clean();
} );

function hitscam_a2_ajax_paydemo() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$o = wc_get_order( absint( $_POST['o'] ?? 0 ) );
	if ( ! $o || ! hash_equals( $o->get_order_key(), sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) ) || ! $o->has_status( 'pending' ) || 'hc_online' !== $o->get_payment_method() ) { wp_send_json_error( array( 'msg' => 'অবৈধ অনুরোধ' ) ); }
	if ( 'ok' === ( $_POST['res'] ?? '' ) ) {
		$m = sanitize_text_field( wp_unslash( $_POST['pgm'] ?? '' ) );
		$o->payment_complete( 'DEMO-' . strtoupper( wp_generate_password( 8, false ) ) );
		$o->add_order_note( 'ডেমো গেটওয়েতে পরিশোধিত (' . $m . ')। আসল গেটওয়ে যুক্ত করুন।' );
		if ( WC()->cart ) { WC()->cart->empty_cart(); }
		wp_send_json_success( array( 'url' => $o->get_checkout_order_received_url() ) );
	}
	wp_send_json_success( array( 'url' => add_query_arg( 'payfail', 1, wc_get_checkout_url() ) ) );
}
add_action( 'wp_ajax_hc_paydemo', 'hitscam_a2_ajax_paydemo' );
add_action( 'wp_ajax_nopriv_hc_paydemo', 'hitscam_a2_ajax_paydemo' );

/* ---------- গার্ড ---------- */
add_filter( 'woocommerce_available_payment_gateways', function ( $g ) {
	if ( is_admin() || ! WC()->cart ) { return $g; }
	$c = hitscam_a2_guard_cfg();
	if ( $c['above'] > 0 && (float) WC()->cart->get_total( 'edit' ) >= $c['above'] ) { unset( $g['cod'] ); } // উচ্চমূল্যে সাধারণ COD বন্ধ → অগ্রিমসহ
	return $g;
} );
function hitscam_a2_guard_message( $phone, $method, $total ) {
	$c = hitscam_a2_guard_cfg();
	if ( $phone && $c['max'] > 0 ) {
		$n = count( wc_get_orders( array( 'billing_phone' => $phone, 'date_created' => '>' . ( time() - DAY_IN_SECONDS ), 'limit' => $c['max'], 'return' => 'ids', 'status' => array( 'pending', 'processing', 'on-hold', 'completed' ) ) ) );
		if ( $n >= $c['max'] ) { return 'এই নাম্বার থেকে ২৪ ঘণ্টায় সর্বোচ্চ ' . hitscam_bn_digits( $c['max'] ) . 'টি অর্ডার নেওয়া হয়। সমস্যা হলে WhatsApp এ জানান।'; }
	}
	if ( 'cod' === $method && $c['above'] > 0 && $total >= $c['above'] ) { return '৳' . number_format_i18n( $c['above'] ) . '+ অর্ডারে "অগ্রিমসহ ক্যাশ অন ডেলিভারি" বা অনলাইন পেমেন্ট বেছে নিন।'; }
	return '';
}
add_action( 'woocommerce_checkout_process', function () {
	if ( ! empty( $_POST['hc_website'] ) ) { wc_add_notice( 'অর্ডার গ্রহণ করা যায়নি।', 'error' ); return; } // phpcs:ignore
	$ph = function_exists( 'hitscam_normalize_bd_phone' ) ? hitscam_normalize_bd_phone( wp_unslash( $_POST['billing_phone'] ?? '' ) ) : ''; // phpcs:ignore
	$m  = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore
	$msg = hitscam_a2_guard_message( $ph, $m, WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0 );
	if ( $msg ) { wc_add_notice( $msg, 'error' ); }
} );
add_action( 'woocommerce_after_order_notes', function () {
	echo '<div class="hp-field" aria-hidden="true"><label>Website<input name="hc_website" tabindex="-1" autocomplete="off"></label></div>';
} );

/* ---------- অ্যাডমিন অর্ডারে পেমেন্ট তথ্য ---------- */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $o ) {
	$tx = $o->get_meta( '_hc_trxid' ); $sl = $o->get_meta( '_hc_slip_url' ); $adv = $o->get_meta( '_hc_advance' );
	if ( ! $tx && ! $sl ) { return; }
	echo '<h3>HitsCam পেমেন্ট</h3><p><b>TrxID:</b> ' . esc_html( $tx ) . ' (' . esc_html( $o->get_meta( '_hc_pay_method' ) ) . ')';
	if ( $adv ) { echo '<br><b>অগ্রিম:</b> ' . wp_kses_post( wc_price( $adv ) ) . ' · <b>বাকি:</b> ' . wp_kses_post( wc_price( $o->get_meta( '_hc_due' ) ) ); }
	if ( $sl ) { echo '<br><a href="' . esc_url( $sl ) . '" target="_blank" rel="noopener">স্লিপ দেখুন</a>'; }
	echo '</p>';
} );
