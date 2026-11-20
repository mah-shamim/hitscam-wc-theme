<?php
/**
 * স্ট্রিম B — লগইন / রেজিস্টার / OTP / পাসওয়ার্ড রিসেট (আসল WordPress+WooCommerce অথ)।
 * SMS গেটওয়ে হুক: add_filter( 'hitscam_send_sms', function ( $sent, $phone, $message ) { …আপনার গেটওয়ে API কল…; return true; }, 10, 3 );
 * হুক সফল হলে true ফেরত দিতে হবে। হুক না থাকলে শুধু WP_DEBUG চালু থাকলে কোড debug.log এ লেখা হয় ও ফর্মে দেখানো হয়।
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'hitscam_extra_pages', function ( $p ) {
	$p['login'] = array( 'লগইন / রেজিস্টার', '[hitscam_login]' );
	return $p;
} );

/** বাংলাদেশি মোবাইল 01XXXXXXXXX ফরম্যাটে (অবৈধ হলে '') */
function hitscam_bd_phone( $v ) {
	$v = strtr( (string) $v, array( '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9' ) );
	$v = preg_replace( '/[^\d+]/', '', $v );
	$v = preg_replace( '/^\+?88/', '', $v );
	return preg_match( '/^01[3-9]\d{8}$/', $v ) ? $v : '';
}

function hitscam_user_by_phone( $phone ) {
	$q = get_users( array(
		'number'     => 1,
		'meta_query' => array( array( 'key' => 'billing_phone', 'value' => array( $phone, '88' . $phone, '+88' . $phone ), 'compare' => 'IN' ) ),
	) );
	if ( $q ) { return $q[0]; }
	$u = get_user_by( 'login', $phone );
	return $u ?: null;
}

function hitscam_auth_markup() {
	$redir = isset( $_GET['redirect_to'] ) ? esc_url( wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), '' ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$terms = ( $ids = (array) get_option( 'hitscam_page_ids', array() ) ) && ! empty( $ids['terms'] ) ? get_permalink( $ids['terms'] ) : '#';
	$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	ob_start(); ?>
<div class="auth-card mx-auto mb-5" data-auth data-redirect="<?php echo esc_attr( $redir ); ?>">
	<div class="text-center mb-3">
		<span class="brand-mark mx-auto mb-2" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-person-fill"></i></span>
		<h1 class="h3 fw-bold mb-1" id="authTitle">আপনার অ্যাকাউন্টে লগইন করুন</h1>
		<p class="text-muted mb-0">অর্ডার ট্র্যাক, উইশলিস্ট ও দ্রুত চেকআউটের জন্য</p>
	</div>
	<ul class="nav nav-pills nav-fill auth-tabs mb-3" role="tablist">
		<li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabLogin" type="button" role="tab">লগইন</button></li>
		<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabRegister" type="button" role="tab">নতুন অ্যাকাউন্ট</button></li>
		<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabOtp" type="button" role="tab" id="otpTabBtn"><i class="bi bi-phone"></i> OTP</button></li>
	</ul>
	<div class="tab-content">
		<div class="tab-pane fade show active" id="tabLogin" role="tabpanel">
			<form id="loginForm" novalidate>
				<div class="mb-3"><label class="form-label">ফোন নাম্বার বা ই-মেইল *</label>
					<input class="form-control form-control-lg" name="id" autocomplete="username" placeholder="01XXXXXXXXX বা you@example.com" required>
					<div class="invalid-feedback">সঠিক ফোন নাম্বার বা ই-মেইল দিন</div></div>
				<div class="mb-2"><label class="form-label" for="pwIn">পাসওয়ার্ড *</label>
					<div class="input-group"><input class="form-control form-control-lg" id="pwIn" type="password" name="pass" autocomplete="current-password" required minlength="6"><button class="btn btn-soft pw-toggle" type="button" aria-label="পাসওয়ার্ড দেখান"><i class="bi bi-eye"></i></button></div></div>
				<div class="d-flex justify-content-between align-items-center mb-3">
					<div class="form-check"><input class="form-check-input" type="checkbox" id="remember" checked><label class="form-check-label" for="remember">মনে রাখুন</label></div>
					<a href="#" class="text-brand small" id="forgot">পাসওয়ার্ড ভুলে গেছেন?</a></div>
				<button class="btn btn-brand btn-lg w-100 rounded-pill">লগইন করুন</button>
			</form>
		</div>
		<div class="tab-pane fade" id="tabRegister" role="tabpanel">
			<form id="registerForm" novalidate>
				<div class="mb-3"><label class="form-label">আপনার নাম *</label><input class="form-control form-control-lg" name="name" autocomplete="name" required><div class="invalid-feedback">নাম লিখুন</div></div>
				<div class="mb-3"><label class="form-label">ফোন নাম্বার *</label><input class="form-control form-control-lg" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required><div class="invalid-feedback">সঠিক বাংলাদেশি নাম্বার দিন</div></div>
				<div class="mb-3"><label class="form-label">ই-মেইল (ঐচ্ছিক)</label><input class="form-control form-control-lg" name="email" type="email" autocomplete="email"></div>
				<div class="mb-3"><label class="form-label">পাসওয়ার্ড * <small class="text-muted">(কমপক্ষে ৬ অক্ষর)</small></label>
					<div class="input-group"><input class="form-control form-control-lg" type="password" name="pass" autocomplete="new-password" minlength="6" required><button class="btn btn-soft pw-toggle" type="button" aria-label="পাসওয়ার্ড দেখান"><i class="bi bi-eye"></i></button></div></div>
				<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="agree" required><label class="form-check-label" for="agree"><a href="<?php echo esc_url( $terms ); ?>" class="text-brand">শর্তাবলী</a> পড়েছি ও মেনে নিচ্ছি</label></div>
				<button class="btn btn-brand btn-lg w-100 rounded-pill">অ্যাকাউন্ট তৈরি করুন</button>
			</form>
		</div>
		<div class="tab-pane fade" id="tabOtp" role="tabpanel">
			<form id="otpPhoneForm" novalidate>
				<div class="mb-3"><label class="form-label">মোবাইল নাম্বার *</label>
					<input class="form-control form-control-lg" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required>
					<div class="invalid-feedback">সঠিক বাংলাদেশি মোবাইল নাম্বার দিন</div></div>
				<button class="btn btn-brand btn-lg w-100 rounded-pill">OTP পাঠান</button>
			</form>
			<form id="otpCodeForm" novalidate style="display:none">
				<p class="text-center mb-2">৬ সংখ্যার কোড পাঠানো হয়েছে <b id="otpTo"></b> নাম্বারে</p>
				<div class="otp-box mb-2" id="otpBox" role="group" aria-label="OTP কোড"></div>
				<div class="invalid-feedback text-center" id="otpErr" style="display:none">কোড মেলেনি। আবার চেষ্টা করুন।</div>
				<div class="alert alert-info small py-2 mt-3 mb-3" id="otpDebug" style="display:none"></div>
				<button class="btn btn-brand btn-lg w-100 rounded-pill mt-3" id="otpVerify">যাচাই করে লগইন</button>
				<div class="d-flex justify-content-between small mt-3"><button type="button" class="btn btn-link p-0 text-brand" id="otpChange">নাম্বার বদলান</button><button type="button" class="btn btn-link p-0 text-brand" id="otpResend" disabled>আবার পাঠান (<span id="otpLeft">30</span>)</button></div>
			</form>
		</div>
	</div>
	<div class="text-center mt-4 small text-muted">অ্যাকাউন্ট ছাড়াও অর্ডার করা যায় — <a href="<?php echo esc_url( $shop ); ?>" class="text-brand">কেনাকাটা চালিয়ে যান</a></div>
</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'hitscam_login', function () {
	if ( is_user_logged_in() ) {
		$u   = wp_get_current_user();
		$acc = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
		return '<div class="auth-card mx-auto mb-5 text-center"><h1 class="h4 fw-bold">স্বাগতম, ' . esc_html( $u->display_name ) . '</h1><p class="text-muted">আপনি লগইন করা আছেন।</p><a class="btn btn-brand rounded-pill me-2" href="' . esc_url( $acc ) . '">আমার অ্যাকাউন্ট</a><a class="btn btn-soft rounded-pill" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">লগআউট</a></div>';
	}
	return hitscam_auth_markup();
} );

// ফ্রন্টে "লগইন" ফর্ম: WooCommerce মাই-অ্যাকাউন্টে লগআউট অবস্থায় টেমপ্লেটের ফর্ম দেখাই
add_filter( 'woocommerce_locate_template', function ( $template, $name ) {
	if ( 'myaccount/form-login.php' === $name ) {
		return HITSCAM_DIR . '/template-parts/auth-form.php';
	}
	return $template;
}, 20, 2 );

/* ---------- AJAX ---------- */
function hitscam_b_check() {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'hitscam' ) ) {
		wp_send_json_error( array( 'msg' => 'সেশন মেয়াদ শেষ — পেজ রিলোড করুন' ) );
	}
}
function hitscam_b_redirect() {
	$r = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return $r ? wp_validate_redirect( $r, home_url( '/' ) ) : home_url( '/' );
}
function hitscam_b_ip_limit( $key, $max, $ttl ) {
	$ip = md5( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore
	$k  = 'hcrl_' . $key . '_' . $ip;
	$n  = (int) get_transient( $k );
	if ( $n >= $max ) { return false; }
	set_transient( $k, $n + 1, $ttl );
	return true;
}

add_action( 'wp_ajax_nopriv_hc_login', 'hitscam_ajax_login' );
add_action( 'wp_ajax_hc_login', 'hitscam_ajax_login' );
function hitscam_ajax_login() {
	hitscam_b_check();
	if ( ! hitscam_b_ip_limit( 'login', 20, 15 * MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন' ) );
	}
	$id   = trim( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
	$pass = (string) wp_unslash( $_POST['pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $id || ! $pass ) { wp_send_json_error( array( 'msg' => 'ফোন/ই-মেইল ও পাসওয়ার্ড দিন' ) ); }
	$login = $id;
	if ( ! is_email( $id ) ) {
		$ph = hitscam_bd_phone( $id );
		$u  = $ph ? hitscam_user_by_phone( $ph ) : get_user_by( 'login', $id );
		if ( $u ) { $login = $u->user_login; }
	}
	$user = wp_signon( array( 'user_login' => $login, 'user_password' => $pass, 'remember' => ! empty( $_POST['remember'] ) ), is_ssl() );
	if ( is_wp_error( $user ) ) { wp_send_json_error( array( 'msg' => 'ফোন/ই-মেইল বা পাসওয়ার্ড ভুল' ) ); }
	wp_send_json_success( array( 'msg' => 'লগইন সফল ✓', 'redirect' => hitscam_b_redirect() ) );
}

add_action( 'wp_ajax_nopriv_hc_register', 'hitscam_ajax_register' );
add_action( 'wp_ajax_hc_register', 'hitscam_ajax_register' );
function hitscam_ajax_register() {
	hitscam_b_check();
	if ( ! hitscam_b_ip_limit( 'reg', 8, HOUR_IN_SECONDS ) ) { wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা হয়েছে — পরে আবার চেষ্টা করুন' ) ); }
	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone = hitscam_bd_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['pass'] ?? '' ); // phpcs:ignore
	if ( ! $name || ! $phone || strlen( $pass ) < 6 ) { wp_send_json_error( array( 'msg' => 'তথ্য ঠিক করুন (পাসওয়ার্ড কমপক্ষে ৬ অক্ষর)' ) ); }
	if ( hitscam_user_by_phone( $phone ) || ( $email && email_exists( $email ) ) ) { wp_send_json_error( array( 'msg' => 'এই ফোন/ই-মেইলে অ্যাকাউন্ট আছে — লগইন করুন' ) ); }
	$mail = $email ?: $phone . '@phone.invalid';
	$uid  = function_exists( 'wc_create_new_customer' ) ? wc_create_new_customer( $mail, $phone, $pass ) : wp_create_user( $phone, $pass, $mail );
	if ( is_wp_error( $uid ) ) { wp_send_json_error( array( 'msg' => wp_strip_all_tags( $uid->get_error_message() ) ) ); }
	wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'first_name' => $name, 'nickname' => $name ) );
	update_user_meta( $uid, 'billing_phone', $phone );
	update_user_meta( $uid, 'billing_first_name', $name );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	wp_send_json_success( array( 'msg' => 'অ্যাকাউন্ট তৈরি হয়েছে ✓', 'redirect' => hitscam_b_redirect() ) );
}

add_action( 'wp_ajax_nopriv_hc_forgot', 'hitscam_ajax_forgot' );
add_action( 'wp_ajax_hc_forgot', 'hitscam_ajax_forgot' );
function hitscam_ajax_forgot() {
	hitscam_b_check();
	if ( ! hitscam_b_ip_limit( 'forgot', 5, HOUR_IN_SECONDS ) ) { wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা হয়েছে — পরে আবার চেষ্টা করুন' ) ); }
	$id = trim( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
	if ( ! $id ) { wp_send_json_error( array( 'msg' => 'ফোন বা ই-মেইল লিখুন' ) ); }
	$login = $id;
	if ( ! is_email( $id ) && ( $ph = hitscam_bd_phone( $id ) ) ) {
		$u = hitscam_user_by_phone( $ph );
		if ( $u ) { $login = $u->user_login; }
	}
	$_POST['user_login'] = $login;
	retrieve_password( $login ); // ফলাফল জানানো হয় না (ইউজার আছে কি না লুকাতে)
	wp_send_json_success( array( 'msg' => 'অ্যাকাউন্ট থাকলে পাসওয়ার্ড রিসেট লিংক ই-মেইলে পাঠানো হয়েছে' ) );
}

/* ---------- OTP ---------- */
function hitscam_otp_key( $phone ) { return 'hcotp_' . md5( $phone ); }

add_action( 'wp_ajax_nopriv_hc_otp_send', 'hitscam_ajax_otp_send' );
add_action( 'wp_ajax_hc_otp_send', 'hitscam_ajax_otp_send' );
function hitscam_ajax_otp_send() {
	hitscam_b_check();
	$phone = hitscam_bd_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	if ( ! $phone ) { wp_send_json_error( array( 'msg' => 'সঠিক বাংলাদেশি মোবাইল নাম্বার দিন' ) ); }
	$k   = hitscam_otp_key( $phone );
	$cur = get_transient( $k );
	if ( $cur && time() - $cur['ts'] < 30 ) { wp_send_json_error( array( 'msg' => 'একটু অপেক্ষা করুন — ৩০ সেকেন্ড পর আবার পাঠান' ) ); }
	$cnt = (int) get_transient( $k . '_n' );
	if ( $cnt >= 3 || ! hitscam_b_ip_limit( 'otp', 10, HOUR_IN_SECONDS ) ) { wp_send_json_error( array( 'msg' => 'অনেকবার OTP চাওয়া হয়েছে — ১০ মিনিট পর চেষ্টা করুন' ) ); }
	set_transient( $k . '_n', $cnt + 1, 10 * MINUTE_IN_SECONDS );
	$code = (string) wp_rand( 100000, 999999 );
	set_transient( $k, array( 'h' => hash_hmac( 'sha256', $code, wp_salt( 'auth' ) ), 'tries' => 0, 'ts' => time() ), 5 * MINUTE_IN_SECONDS );
	$msg  = $code . ' — ' . get_bloginfo( 'name' ) . ' লগইন কোড। কাউকে জানাবেন না।';
	$sent = (bool) apply_filters( 'hitscam_send_sms', false, $phone, $msg );
	$out  = array( 'msg' => 'OTP পাঠানো হয়েছে' );
	if ( ! $sent ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[hitscam-otp] ' . $phone . ' => ' . $code ); // phpcs:ignore
			$out['debug_code'] = $code;
			$out['msg']        = 'OTP তৈরি হয়েছে (SMS গেটওয়ে নেই — ডিবাগ মোড)';
		} else {
			delete_transient( $k );
			wp_send_json_error( array( 'msg' => 'SMS সেবা এখনও চালু হয়নি — পাসওয়ার্ড দিয়ে লগইন করুন' ) );
		}
	}
	wp_send_json_success( $out );
}

add_action( 'wp_ajax_nopriv_hc_otp_verify', 'hitscam_ajax_otp_verify' );
add_action( 'wp_ajax_hc_otp_verify', 'hitscam_ajax_otp_verify' );
function hitscam_ajax_otp_verify() {
	hitscam_b_check();
	$phone = hitscam_bd_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	$code  = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore
	$k     = hitscam_otp_key( (string) $phone );
	$cur   = $phone ? get_transient( $k ) : false;
	if ( ! $cur ) { wp_send_json_error( array( 'msg' => 'কোডের মেয়াদ শেষ — নতুন OTP নিন', 'lock' => true ) ); }
	if ( $cur['tries'] >= 3 ) { wp_send_json_error( array( 'msg' => 'অনেকবার ভুল। নতুন OTP নিন।', 'lock' => true ) ); }
	if ( ! hash_equals( $cur['h'], hash_hmac( 'sha256', $code, wp_salt( 'auth' ) ) ) ) {
		++$cur['tries'];
		set_transient( $k, $cur, 5 * MINUTE_IN_SECONDS );
		wp_send_json_error( array( 'msg' => 'কোড মেলেনি। আবার চেষ্টা করুন।', 'lock' => $cur['tries'] >= 3 ) );
	}
	delete_transient( $k );
	$user = hitscam_user_by_phone( $phone );
	if ( ! $user ) {
		$mail = $phone . '@phone.invalid';
		$uid  = function_exists( 'wc_create_new_customer' ) ? wc_create_new_customer( $mail, $phone, wp_generate_password( 20 ) ) : wp_create_user( $phone, wp_generate_password( 20 ), $mail );
		if ( is_wp_error( $uid ) ) { wp_send_json_error( array( 'msg' => 'অ্যাকাউন্ট তৈরি করা যায়নি' ) ); }
		update_user_meta( $uid, 'billing_phone', $phone );
		wp_update_user( array( 'ID' => $uid, 'display_name' => 'গ্রাহক ' . substr( $phone, -4 ) ) );
		$user = get_user_by( 'id', $uid );
	}
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	wp_send_json_success( array( 'msg' => 'লগইন সফল ✓', 'redirect' => hitscam_b_redirect() ) );
}

/* ---------- মাই-অ্যাকাউন্ট: লয়ালটি সারাংশ (প্লেসহোল্ডার; পয়েন্ট থাকলে দেখায়) ---------- */
add_action( 'woocommerce_account_dashboard', function () {
	$pts = (int) get_user_meta( get_current_user_id(), 'hc_loyalty_points', true );
	$ref = get_user_meta( get_current_user_id(), 'hc_referral_code', true );
	echo '<div class="panel mt-3 account-loyalty"><h2 class="h6 fw-bold mb-2"><i class="bi bi-gift-fill text-brand"></i> লয়ালটি পয়েন্ট</h2><div class="h4 fw-bold mb-1">' . esc_html( hitscam_bn_digits( $pts ) ) . ' <small class="text-muted fs-6">পয়েন্ট</small></div>';
	echo '<div class="small text-muted">প্রতিটি অর্ডার ও রিভিউয়ে পয়েন্ট জমে; পরের অর্ডারে ছাড় হিসেবে ব্যবহার করা যায়।' . ( $ref ? ' রেফারেল কোড: <b>' . esc_html( $ref ) . '</b>' : '' ) . '</div></div>';
} );
