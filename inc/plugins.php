<?php
/**
 * প্রয়োজনীয় প্লাগইন: WooCommerce না থাকলে অটো-ইনস্টল + অ্যাক্টিভেট; বান্ডেলড HitsCam প্লাগইনও কপি/অ্যাক্টিভেট।
 *
 * - থিম অ্যাক্টিভেট করলে (after_switch_theme) একবার চলে।
 * - ব্যর্থ হলে অ্যাডমিন নোটিশে "আবার চেষ্টা করুন" বাটন (admin-post)।
 * - wordpress.org এ নেটওয়ার্ক/ফাইল-পারমিশন না থাকলে স্পষ্ট বার্তা দেয়, সাইট ভাঙে না।
 */
defined( 'ABSPATH' ) || exit;

function hitscam_required_plugins() {
	return array(
		'woocommerce'      => array( 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'source' => 'wporg', 'required' => true ),
		'hitscam-tracking' => array( 'name' => 'HitsCam Tracking (Meta/GA4/GTM)', 'file' => 'hitscam-tracking/hitscam-tracking.php', 'source' => 'bundled', 'required' => false ),
		'hitscam-seo'      => array( 'name' => 'HitsCam SEO', 'file' => 'hitscam-seo/hitscam-seo.php', 'source' => 'bundled', 'required' => false ),
		'hitscam-api'      => array( 'name' => 'HitsCam API', 'file' => 'hitscam-api/hitscam-api.php', 'source' => 'bundled', 'required' => false ),
	);
}

function hitscam_plugin_active( $file ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) );
}

/**
 * একটি প্লাগইন নিশ্চিত করে (ইনস্টল + অ্যাক্টিভেট)।
 *
 * @return true|WP_Error
 */
function hitscam_ensure_plugin( $slug ) {
	$all = hitscam_required_plugins();
	if ( ! isset( $all[ $slug ] ) ) {
		return new WP_Error( 'unknown', 'অজানা প্লাগইন' );
	}
	$p = $all[ $slug ];
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	if ( hitscam_plugin_active( $p['file'] ) ) {
		return true;
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return new WP_Error( 'cap', 'প্লাগইন অ্যাক্টিভেট করার অনুমতি নেই' );
	}

	// ইনস্টল করা না থাকলে
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $p['file'] ) ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'cap', 'প্লাগইন ইনস্টলের অনুমতি নেই (install_plugins)' );
		}
		if ( 'direct' !== get_filesystem_method() ) {
			return new WP_Error( 'fs', 'সার্ভারে সরাসরি ফাইল লেখা যায় না (FTP তথ্য লাগবে) — প্লাগইন ম্যানুয়ালি ইনস্টল করুন' );
		}
		$res = 'bundled' === $p['source'] ? hitscam_install_bundled( $slug ) : hitscam_install_from_wporg( $slug );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
	}
	wp_clean_plugins_cache( false );

	$act = activate_plugin( $p['file'], '', false, true ); // silent=true: রিডাইরেক্ট/হুক এড়ায়
	if ( is_wp_error( $act ) ) {
		return $act;
	}
	if ( 'woocommerce' === $slug ) {
		delete_transient( '_wc_activation_redirect' ); // অনবোর্ডিং রিডাইরেক্ট বন্ধ
		update_option( 'hitscam_store_setup_pending', 1 );
	}
	return true;
}

function hitscam_install_bundled( $slug ) {
	global $wp_filesystem;
	WP_Filesystem();
	$src = HITSCAM_DIR . '/bundled-plugins/' . $slug;
	if ( ! is_dir( $src ) ) {
		return new WP_Error( 'missing', 'বান্ডেলড প্লাগইন পাওয়া যায়নি: ' . $slug );
	}
	$dst = WP_PLUGIN_DIR . '/' . $slug;
	$ok  = copy_dir( $src, $dst );
	return is_wp_error( $ok ) ? $ok : true;
}

function hitscam_install_from_wporg( $slug ) {
	$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
	if ( is_wp_error( $api ) ) {
		return new WP_Error( 'api', 'wordpress.org থেকে তথ্য আনা যায়নি: ' . $api->get_error_message() );
	}
	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $api->download_link );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( is_wp_error( $skin->result ) ) {
		return $skin->result;
	}
	if ( ! $result ) {
		return new WP_Error( 'install', 'ইনস্টল ব্যর্থ: ' . wp_strip_all_tags( implode( ' ', (array) $skin->get_upgrade_messages() ) ) );
	}
	return true;
}

/** সব প্রয়োজনীয় প্লাগইন চালায়; ফলাফল অপশনে রাখে */
function hitscam_run_installer( $only_required = false ) {
	$report = array();
	foreach ( hitscam_required_plugins() as $slug => $p ) {
		if ( $only_required && ! $p['required'] ) {
			continue;
		}
		$r               = hitscam_ensure_plugin( $slug );
		$report[ $slug ] = is_wp_error( $r ) ? $r->get_error_message() : true;
	}
	update_option( 'hitscam_install_report', $report, false );
	return $report;
}

// থিম অ্যাক্টিভেশন → ফ্ল্যাগ; পরের অ্যাডমিন রিকোয়েস্টে ইনস্টল (অ্যাক্টিভেশন রিকোয়েস্ট হালকা রাখতে)
add_action( 'after_switch_theme', function () {
	update_option( 'hitscam_install_pending', 1 );
} );

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( get_option( 'hitscam_install_pending' ) ) {
		delete_option( 'hitscam_install_pending' ); // লুপ এড়াতে আগেই মুছি
		hitscam_run_installer();
	}
} );

// ব্যবহারকারী নিজে আবার চেষ্টা করতে পারে
add_action( 'admin_post_hitscam_install_plugins', function () {
	check_admin_referer( 'hitscam_install_plugins' );
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'অনুমতি নেই', 'hitscam' ) );
	}
	hitscam_run_installer();
	wp_safe_redirect( admin_url( 'themes.php?hitscam_installed=1' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$report = (array) get_option( 'hitscam_install_report', array() );
	$errors = array_filter( $report, fn( $v ) => true !== $v );
	$wc_ok  = hitscam_plugin_active( 'woocommerce/woocommerce.php' );

	if ( ! $wc_ok ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=hitscam_install_plugins' ), 'hitscam_install_plugins' );
		echo '<div class="notice notice-error"><p><strong>HitsCam:</strong> WooCommerce সক্রিয় নেই — থিমের শপ-ফিচার কাজ করবে না। ';
		if ( ! empty( $errors['woocommerce'] ) ) {
			echo 'কারণ: ' . esc_html( $errors['woocommerce'] ) . ' ';
		}
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '">WooCommerce ইনস্টল ও অ্যাক্টিভেট করুন</a></p></div>';
	}
	foreach ( $errors as $slug => $msg ) {
		if ( 'woocommerce' === $slug ) {
			continue;
		}
		echo '<div class="notice notice-warning is-dismissible"><p><strong>HitsCam:</strong> ' . esc_html( hitscam_required_plugins()[ $slug ]['name'] ?? $slug ) . ' — ' . esc_html( $msg ) . '</p></div>';
	}
	if ( isset( $_GET['hitscam_installed'] ) && $wc_ok ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>HitsCam: প্রয়োজনীয় প্লাগইন প্রস্তুত ✓</p></div>';
	}
} );
