<?php
/**
 * অর্ডার রিসিভড — HitsCam টেমপ্লেটের order-received.html লেআউট (হিরো, কল-অ্যালার্ট, রিকনফার্ম+SMS, টাইমলাইন, প্রোডাক্ট, ঠিকানা)।
 *
 * @var WC_Order|false $order
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;
if ( ! $order ) {
	echo '<div class="panel text-center"><p class="mb-3">অর্ডার পাওয়া যায়নি।</p><a class="btn btn-brand" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">কেনাকাটা করুন</a></div>';
	return;
}
$oid     = $order->get_id();
$phone   = $order->get_billing_phone();
$name    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
$status  = $order->get_status();
$reconf  = (bool) $order->get_meta( '_hc_reconfirmed' );
$steps   = array(
	array( '📝', 'অর্ডার গ্রহণ', 'আপনার অর্ডার আমরা পেয়েছি' ),
	array( 'bi-telephone-fill', 'কনফার্মেশন কল', 'আমরা ফোনে কনফার্ম করব' ),
	array( '📦', 'প্যাকেজিং', 'প্রোডাক্ট প্যাক করা হবে' ),
	array( '🚚', 'কুরিয়ারে পাঠানো', 'আপনার ঠিকানায় পৌঁছাবে' ),
	array( '✅', 'ডেলিভারি সম্পন্ন', 'উপভোগ করুন!' ),
);
// বর্তমান ধাপ: স্ট্যাটাস থেকে
$cur = 1;
if ( $reconf || 'processing' === $status && $order->get_meta( '_hc_reconfirmed' ) ) { $cur = 2; }
if ( in_array( $status, array( 'shipped', 'out-for-delivery' ), true ) ) { $cur = 3; }
if ( 'completed' === $status ) { $cur = 4; }
$tel = '+' . preg_replace( '/\D/', '', hitscam_opt( 'whatsapp', '8801953073445' ) );
$sub = (float) $order->get_subtotal();
$disc = (float) $order->get_discount_total();
$ship = (float) $order->get_shipping_total();
$pay_status = '';
$bn = fn( $n ) => hitscam_bn_digits( $n );
$rm = hitscam_ty_mode( 'reconfirm' ); $nm = hitscam_ty_mode( 'notify' );
$sms_mode   = $rm['sms'];
$on = fn( $k ) => hitscam_ty_on( $k );
$msg_order  = 'HitsCam: প্রিয় ' . $name . ', আপনার অর্ডার #' . $order->get_order_number() . ' (মোট ' . wp_strip_all_tags( wc_price( $order->get_total() ) ) . ') গ্রহণ করা হয়েছে। আমাদের প্রতিনিধি কল করবেন। তথ্য ঠিক থাকলে রিকনফার্ম করুন। সাপোর্ট: ' . $tel;
$msg_reconf = 'HitsCam: আমি অর্ডার #' . $order->get_order_number() . ' রিকনফার্ম করছি। নাম: ' . $name . ', ফোন: ' . $phone;
$courier_html = function_exists( 'hitscam_e4_courier_panel' ) ? hitscam_e4_courier_panel( $order ) : '';
if ( $courier_html && $cur < 3 ) { $cur = 3; }
$addon = function_exists( 'hitscam_e4_addon_product' ) ? hitscam_e4_addon_product( $order ) : null;
$left  = max( 0, ( $order->get_date_created()->getTimestamp() + hitscam_a2_window() * 60 ) - time() );
$uid   = $order->get_customer_id();
$earn  = (int) $order->get_meta( '_hc_points_earn' );
?>
<div class="order-received" data-order="<?php echo (int) $oid; ?>" data-key="<?php echo esc_attr( $order->get_order_key() ); ?>" data-phone="<?php echo esc_attr( $phone ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-total="<?php echo esc_attr( wp_strip_all_tags( wc_price( $order->get_total() ) ) ); ?>" data-reconf="<?php echo $reconf ? 1 : 0; ?>" data-sms="<?php echo $sms_mode ? 1 : 0; ?>" data-pdf="<?php echo esc_url( get_theme_file_uri( 'assets/vendor/pdf/' ) ); ?>" data-nb="<?php echo $on( 'notify' ) && ! $nm['off'] ? 1 : 0; ?>" data-mg="<?php echo $on( 'manage' ) ? 1 : 0; ?>" data-cr="<?php echo $on( 'cross' ) ? 1 : 0; ?>" data-msg="<?php echo esc_attr( $msg_order ); ?>" data-msgdone="<?php echo esc_attr( 'HitsCam: ধন্যবাদ ' . $name . '! আপনার অর্ডার #' . $order->get_order_number() . ' রিকনফার্ম হয়েছে। শীঘ্রই প্যাকেজিং শুরু হবে।' ); ?>">
	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4"><h1 class="page-title mb-0">Order received</h1>
		<span class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-brand rounded-pill no-print reorder-btn" id="reorderBtn"><i class="bi bi-arrow-repeat"></i> আবার অর্ডার করুন</button>
		<button type="button" class="btn btn-soft rounded-pill no-print" id="pdfBtn"><i class="bi bi-file-earmark-pdf"></i> ইনভয়েস ডাউনলোড</button></span></div>
	<div class="print-only mb-3"><h2 class="fw-bold mb-0">HitsCam – ইনভয়েস</h2><small><?php echo esc_html( hitscam_opt( 'email', 'support@hitscam.com' ) ); ?> · <?php echo esc_html( $tel ); ?></small></div>

	<div class="or-hero mb-3">
		<div class="or-check"><i class="bi bi-check-lg"></i></div>
		<h2 class="fw-bold">ধন্যবাদ, <span><?php echo esc_html( mb_strtoupper( $name ) ); ?></span>! 🎉</h2>
		<p class="text-muted">আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে। আমাদের প্রতিনিধি শীঘ্রই আপনাকে কনফার্মেশনের জন্য কল করবেন।</p>
		<div class="row g-3 mt-2">
			<div class="col-6 col-md-3"><div class="or-stat"><small>অর্ডার নং</small><b>#<?php echo esc_html( $order->get_order_number() ); ?></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>তারিখ</small><b><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'F j, Y' ) ); ?></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>মোট</small><b><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></b></div></div>
			<div class="col-6 col-md-3"><div class="or-stat"><small>পেমেন্ট</small><b><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></b></div></div>
		</div>
	</div>

	<div class="call-alert mb-3">
		<i class="ph bi bi-telephone-fill"></i>
		<div class="flex-grow-1"><b id="alertTitle"><?php echo $reconf ? 'অর্ডার রিকনফার্ম হয়েছে ✓' : 'কনফার্মেশন কলের অপেক্ষায়'; ?></b><br>আমাদের টিম<br><b><?php echo esc_html( $tel ); ?></b><br><span>নাম্বারে কল করে অর্ডার কনফার্ম করবে। অনুগ্রহ করে ফোন রিসিভ করুন।</span></div>
		<a href="tel:<?php echo esc_attr( $tel ); ?>" class="btn btn-amber">সাহায্য চাই</a>
	</div>

	<?php if ( $on( 'reconfirm' ) && ! $rm['off'] ) : ?>
	<div class="panel mb-3 no-print" id="reconfirmPanel">
		<h5 class="fw-bold">🔁 অর্ডার রিকনফার্ম</h5>
		<p class="text-muted mb-3">অর্ডারের তথ্য ঠিক থাকলে রিকনফার্ম করুন। আপনার মোবাইল <b id="smsPhone" class="text-dark"><?php echo esc_html( $phone ); ?></b> নাম্বারে <?php echo $rm['sms'] ? 'নিচের মতো SMS পাঠানো হয়েছে।' : 'নিচের মেসেজটি আপনার পছন্দের অ্যাপে পাঠিয়ে রিকনফার্ম করুন।'; ?></p>
		<div class="sms-thread mb-3" id="smsThread" aria-live="polite"></div>
		<div class="btn-row" id="reconfirmRow">
			<button type="button" class="btn btn-brand btn-lg rounded-pill" id="reconfirmBtn"><i class="bi bi-patch-check-fill"></i> অর্ডার রিকনফার্ম করুন</button>
			<?php if ( $rm['sms'] ) : ?><button type="button" class="btn btn-soft btn-lg rounded-pill" id="resendSms"><i class="bi bi-chat-dots"></i> আবার SMS পাঠান</button><?php endif; ?>
			<?php if ( $rm['ch'] && function_exists( 'hitscam_channel_buttons' ) ) { echo hitscam_channel_buttons( 'reconfirm', $msg_reconf, $phone, 'btn btn-soft btn-lg rounded-pill' ); } // phpcs:ignore ?>
		</div>
		<p class="small text-muted mb-0 mt-2"><?php echo $rm['sms'] ? 'SMS এ পাঠানো তথ্য ঠিক থাকলে রিকনফার্ম বাটনে চাপ দিন।' : 'মেসেজ পাঠানোর পর “অর্ডার রিকনফার্ম করুন” চাপুন — আমাদের টিম যাচাই করে নেবে।'; ?></p>
	</div>
	<?php endif; ?>

	<div class="panel mb-3">
		<h5 class="fw-bold">📍 অর্ডার ট্র্যাকিং</h5>
		<p>বর্তমান স্ট্যাটাস: <span class="status-pill" id="statusPill"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span></p>
		<?php $n = count( $steps ); $k = (int) ceil( $n / 2 ); ?>
		<ul class="timeline u-timeline mt-3" style="--k:<?php echo (int) $k; ?>;--n:<?php echo (int) $n; ?>">
			<?php foreach ( $steps as $i => $s ) : $cls = $i < $cur ? 'done' : ( $i === $cur ? 'current' : '' ); $col = $i < $k ? 1 : 2; $row = $i < $k ? $i + 1 : ( 2 * $k - $i ); ?>
			<li class="<?php echo esc_attr( $cls ); ?> u-<?php echo $i < $k ? 'l' : 'r'; ?>" style="grid-column:<?php echo (int) $col; ?>;grid-row:<?php echo (int) $row; ?>"><div class="tl-dot"><?php echo 0 === strpos( $s[0], 'bi-' ) ? ( 'done' === $cls ? '<i class="bi bi-check-lg"></i>' : '<i class="bi ' . esc_attr( $s[0] ) . '"></i>' ) : esc_html( $s[0] ); ?></div><div><h6><?php echo esc_html( $s[1] ); ?></h6><p><?php echo esc_html( $s[2] ); ?></p></div></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $on( 'courier' ) ) { echo $courier_html; } // phpcs:ignore ?>
	</div>

	<div class="panel mb-3">
		<h5 class="fw-bold mb-3">🛍️ আপনার প্রোডাক্ট (<?php echo esc_html( $bn( count( $order->get_items() ) ) ); ?>)</h5>
		<?php foreach ( $order->get_items() as $it ) : $pr = $it->get_product(); $img = $pr ? $pr->get_image( array( 64, 64 ), array( 'class' => 'rounded-3', 'width' => 64, 'height' => 64 ) ) : ''; ?>
		<div class="d-flex gap-3 align-items-center p-3 mb-2 rounded-4 border bg-light"><?php echo wp_kses_post( $img ); ?><div class="flex-grow-1"><div class="fw-semibold"><?php echo esc_html( $it->get_name() ); ?></div><small>পরিমাণ: <?php echo esc_html( $bn( $it->get_quantity() ) ); ?></small></div><b><?php echo wp_kses_post( wc_price( $it->get_total() ) ); ?></b></div>
		<?php endforeach; ?>
		<?php if ( $on( 'addon' ) && $addon && $left > 0 && $order->has_status( array( 'pending', 'processing', 'on-hold' ) ) ) : ?>
		<div class="p-3 mb-2 rounded-4 border border-danger-subtle bg-danger-subtle no-print d-flex gap-3 align-items-center flex-wrap" id="addonOffer"><div class="flex-grow-1"><small class="text-danger fw-semibold"><i class="bi bi-plus-circle"></i> অর্ডারের সাথে যোগ করুন · আর <?php echo esc_html( $bn( (int) ceil( $left / 60 ) ) ); ?> মিনিট</small><div class="fw-semibold"><?php echo esc_html( $addon->get_name() ); ?></div><small class="text-muted">একই ঠিকানায় একই প্যাকেজে যাবে, ডেলিভারিতে পরিশোধ</small></div><div class="text-end"><b class="text-brand d-block"><?php echo wp_kses_post( wc_price( $addon->get_price() ) ); ?></b><button type="button" class="btn btn-brand btn-sm rounded-pill" id="addonBtn" data-pid="<?php echo (int) $addon->get_id(); ?>">যোগ করুন</button></div></div>
		<?php endif; ?>
		<?php $ref = $on( 'referral' ) && function_exists( 'hitscam_e4_ref_for' ) ? hitscam_e4_ref_for( $order ) : ''; if ( $ref ) : $rtxt = 'HitsCam এ ' . $ref . ' কোড দিয়ে কেনাকাটায় ছাড় পান: ' . home_url( '/?ref=' . $ref ); ?>
		<div class="p-3 mb-2 rounded-4 border border-dashed no-print d-flex gap-3 align-items-center flex-wrap"><div class="flex-grow-1"><b><i class="bi bi-gift text-brand"></i> বন্ধুকে ৳২০০ ছাড় দিন</b><div class="small text-muted">আপনার কোড <b class="text-brand"><?php echo esc_html( $ref ); ?></b></div></div><a class="btn btn-success btn-sm rounded-pill" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $rtxt ); ?>"><i class="bi bi-whatsapp"></i> শেয়ার করুন</a></div>
		<?php endif; ?>
		<hr style="border-style:dashed">
		<div class="totals-row"><span>Subtotal:</span><span><?php echo wp_kses_post( wc_price( $sub ) ); ?></span></div>
		<?php if ( $disc > 0 ) : ?><div class="totals-row"><span>Discount:</span><span>-<?php echo wp_kses_post( wc_price( $disc ) ); ?></span></div><?php endif; ?>
		<div class="totals-row"><span>Shipping:</span><span><?php echo $ship > 0 ? wp_kses_post( wc_price( $ship ) ) : 'Free shipping'; ?></span></div>
		<?php if ( $on( 'points' ) && $earn > 0 ) : ?><div class="totals-row"><span>অর্জিত পয়েন্ট:</span><b><?php echo esc_html( $bn( $earn ) ); ?><?php echo $uid && function_exists( 'hitscam_points' ) ? ' (ব্যালেন্স ' . esc_html( $bn( hitscam_points( $uid ) ) ) . ')' : ''; ?></b></div><?php endif; ?>
		<div class="totals-row total"><span>Total:</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
		<div class="totals-row"><span>Payment method:</span><b><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></b></div>
		<div class="totals-row"><span>পেমেন্ট অবস্থা:</span><b><?php echo $order->is_paid() && ! in_array( $order->get_payment_method(), array( 'cod', 'hc_codadv' ), true ) ? 'পরিশোধিত' : ( (float) $order->get_meta( '_hc_advance' ) > 0 ? 'অগ্রিম পাঠানো, বাকি ডেলিভারিতে' : 'ডেলিভারিতে পরিশোধ' ); ?></b></div>
		<?php if ( $order->get_transaction_id() ) : ?><div class="totals-row"><span>Transaction ID:</span><b><?php echo esc_html( $order->get_transaction_id() ); ?></b></div><?php endif; ?>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-md-6"><div class="panel h-100"><h5 class="fw-bold">🚚 ডেলিভারি ঠিকানা</h5>
			<b><?php echo esc_html( mb_strtoupper( $name ) ); ?></b>
			<div><?php echo wp_kses_post( $order->get_formatted_shipping_address( $order->get_formatted_billing_address() ) ); ?></div>
			<?php if ( $phone ) : ?><div class="text-muted"><i class="bi bi-telephone"></i> <?php echo esc_html( $phone ); ?></div><?php endif; ?>
			<?php if ( $order->get_billing_email() ) : ?><div class="text-muted"><i class="bi bi-envelope"></i> <?php echo esc_html( $order->get_billing_email() ); ?></div><?php endif; ?></div></div>
		<div class="col-md-6"><div class="panel h-100"><h5 class="fw-bold">💡 এরপর কী হবে?</h5>
			<ol class="ps-3 mb-0"><li class="mb-1">আমাদের প্রতিনিধি ১-২ ঘণ্টার মধ্যে কল করবে</li><li class="mb-1">অর্ডার কনফার্মেশনের পর প্যাকেজিং শুরু হবে</li><li class="mb-1">কুরিয়ার আপনার ঠিকানায় পৌঁছে দেবে</li><li>পণ্য হাতে পেয়ে চেক করে পেমেন্ট করুন</li></ol></div></div>
	</div>
	<?php if ( $on( 'notify' ) && ! $nm['off'] ) : ?><div class="notif-box no-print" id="notifBox"><div><b><i class="bi bi-bell-fill text-brand"></i> অর্ডারের আপডেট নোটিফিকেশনে পান</b><div class="small text-muted" id="notifTxt"><?php echo $nm['sms'] && ! $nm['ch'] ? 'কনফার্ম, কুরিয়ারে পাঠানো ও ডেলিভারির খবর SMS এ জানানো হবে।' : 'কোন অ্যাপে আপডেট চান বেছে নিন — আমরা সেখানে জানাব।'; ?></div>
		<?php if ( $nm['ch'] && function_exists( 'hitscam_channels' ) ) : ?><div class="d-flex flex-wrap gap-2 mt-2" id="chanNotify"><?php foreach ( hitscam_channels( 'notify' ) as $c ) : ?><button type="button" class="btn btn-soft btn-sm rounded-pill hc-notify-ch" data-ch="<?php echo esc_attr( $c['key'] ); ?>" data-href="<?php echo esc_url( hitscam_channel_link( $c, 'HitsCam: অর্ডার #' . $order->get_order_number() . ' এর আপডেট এই অ্যাপে পেতে চাই। ফোন: ' . $phone, $phone ) ); ?>"><i class="bi <?php echo esc_attr( $c['icon'] ); ?>"></i> <?php echo esc_html( $c['label'] ); ?></button><?php endforeach; ?></div><?php endif; ?></div>
		<button type="button" class="btn btn-brand btn-sm rounded-pill" id="notifBtn">চালু করুন</button></div><?php endif; ?>
	<div class="row g-2 mb-5 no-print">
		<div class="col-lg"><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn btn-brand btn-lg w-100">আরও কেনাকাটা করুন</a></div>
		<div class="col-6 col-lg-auto"><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn btn-soft btn-lg w-100">অর্ডার বিস্তারিত</a></div>
		<div class="col-6 col-lg-auto"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-soft btn-lg w-100">হোমে ফিরে যান</a></div>
	</div>
</div>
