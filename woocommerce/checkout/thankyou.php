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
$tel = '+' . preg_replace( '/\D/', '', hitscam_opt( 'whatsapp', '8801614747054' ) );
$sub = (float) $order->get_subtotal();
$disc = (float) $order->get_discount_total();
$ship = (float) $order->get_shipping_total();
$pay_status = '';
$bn = fn( $n ) => hitscam_bn_digits( $n );
?>
<div class="order-received" data-order="<?php echo (int) $oid; ?>" data-key="<?php echo esc_attr( $order->get_order_key() ); ?>" data-phone="<?php echo esc_attr( $phone ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-total="<?php echo esc_attr( wp_strip_all_tags( wc_price( $order->get_total() ) ) ); ?>" data-reconf="<?php echo $reconf ? 1 : 0; ?>">
	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4"><h1 class="page-title mb-0">Order received</h1>
		<span class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-brand rounded-pill no-print reorder-btn" id="reorderBtn"><i class="bi bi-arrow-repeat"></i> আবার অর্ডার করুন</button>
		<button type="button" class="btn btn-soft rounded-pill no-print" id="printBtn"><i class="bi bi-printer"></i> ইনভয়েস প্রিন্ট</button></span></div>
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

	<div class="panel mb-3 no-print" id="reconfirmPanel">
		<h5 class="fw-bold">🔁 অর্ডার রিকনফার্ম</h5>
		<p class="text-muted mb-3">অর্ডারের তথ্য ঠিক থাকলে রিকনফার্ম করুন। আপনার মোবাইল <b id="smsPhone" class="text-dark"><?php echo esc_html( $phone ); ?></b> নাম্বারে নিচের মতো SMS যাবে।</p>
		<div class="sms-thread mb-3" id="smsThread" aria-live="polite"></div>
		<div class="d-flex flex-wrap gap-2">
			<button type="button" class="btn btn-brand btn-lg rounded-pill flex-fill" id="reconfirmBtn"><i class="bi bi-patch-check-fill"></i> অর্ডার রিকনফার্ম করুন</button>
			<button type="button" class="btn btn-soft btn-lg rounded-pill flex-fill" id="resendSms"><i class="bi bi-chat-dots"></i> আবার SMS পাঠান</button>
		</div>
		<p class="small text-muted mb-0 mt-2">SMS পাঠাতে একটি SMS গেটওয়ে যুক্ত করতে হয় (<code>hitscam_send_sms</code> অ্যাকশন); না থাকলে এখানে প্রিভিউ দেখায় ও রিকনফার্ম অর্ডার নোটে সেভ হয়।</p>
	</div>

	<div class="panel mb-3">
		<h5 class="fw-bold">📍 অর্ডার ট্র্যাকিং</h5>
		<p>বর্তমান স্ট্যাটাস: <span class="status-pill" id="statusPill"><?php echo esc_html( $reconf && 'processing' === $status ? 'Confirmed' : wc_get_order_status_name( $status ) ); ?></span></p>
		<ul class="timeline mt-3">
			<?php foreach ( $steps as $i => $s ) : $cls = $i < $cur ? 'done' : ( $i === $cur ? 'current' : '' ); ?>
			<li class="<?php echo esc_attr( $cls ); ?>"><div class="tl-dot"><?php echo 0 === strpos( $s[0], 'bi-' ) ? ( 'done' === $cls ? '<i class="bi bi-check-lg"></i>' : '<i class="bi ' . esc_attr( $s[0] ) . '"></i>' ) : esc_html( $s[0] ); ?></div><div><h6><?php echo esc_html( $s[1] ); ?></h6><p><?php echo esc_html( $s[2] ); ?></p></div></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="panel mb-3">
		<h5 class="fw-bold mb-3">🛍️ আপনার প্রোডাক্ট (<?php echo esc_html( $bn( count( $order->get_items() ) ) ); ?>)</h5>
		<?php foreach ( $order->get_items() as $it ) : $pr = $it->get_product(); $img = $pr ? $pr->get_image( array( 64, 64 ), array( 'class' => 'rounded-3', 'width' => 64, 'height' => 64 ) ) : ''; ?>
		<div class="d-flex gap-3 align-items-center p-3 mb-2 rounded-4 border bg-light"><?php echo wp_kses_post( $img ); ?><div class="flex-grow-1"><div class="fw-semibold"><?php echo esc_html( $it->get_name() ); ?></div><small>পরিমাণ: <?php echo esc_html( $bn( $it->get_quantity() ) ); ?></small></div><b><?php echo wp_kses_post( wc_price( $it->get_total() ) ); ?></b></div>
		<?php endforeach; ?>
		<hr style="border-style:dashed">
		<div class="totals-row"><span>Subtotal:</span><span><?php echo wp_kses_post( wc_price( $sub ) ); ?></span></div>
		<?php if ( $disc > 0 ) : ?><div class="totals-row"><span>Discount:</span><span>-<?php echo wp_kses_post( wc_price( $disc ) ); ?></span></div><?php endif; ?>
		<div class="totals-row"><span>Shipping:</span><span><?php echo $ship > 0 ? wp_kses_post( wc_price( $ship ) ) : 'Free shipping'; ?></span></div>
		<div class="totals-row total"><span>Total:</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
		<div class="totals-row"><span>Payment method:</span><b><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></b></div>
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
	<div class="row g-2 mb-5 no-print">
		<div class="col-lg"><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn btn-brand btn-lg w-100">আরও কেনাকাটা করুন</a></div>
		<div class="col-6 col-lg-auto"><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="btn btn-soft btn-lg w-100">অর্ডার বিস্তারিত</a></div>
		<div class="col-6 col-lg-auto"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-soft btn-lg w-100">হোমে ফিরে যান</a></div>
	</div>
</div>
