<?php
/** FAQ (CPT + সার্চ), কন্ট্যাক্ট ফর্ম, শর্টকোড। */
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type( 'hc_faq', array(
		'labels'       => array( 'name' => 'FAQ', 'singular_name' => 'FAQ', 'add_new_item' => 'নতুন প্রশ্ন', 'edit_item' => 'প্রশ্ন সম্পাদনা' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-editor-help',
		'supports'     => array( 'title', 'editor', 'page-attributes' ),
	) );
	register_taxonomy( 'hc_faq_cat', 'hc_faq', array( 'label' => 'FAQ বিভাগ', 'public' => false, 'show_ui' => true, 'hierarchical' => true, 'show_in_rest' => true ) );
} );

/** [hitscam_faq] — অ্যাকর্ডিয়ন + লাইভ সার্চ (JSON-LD FAQPage সহ) */
add_shortcode( 'hitscam_faq', function () {
	$q = new WP_Query( array( 'post_type' => 'hc_faq', 'posts_per_page' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ), 'no_found_rows' => true ) );
	if ( ! $q->have_posts() ) {
		return '<p class="text-muted">এখনও কোনো প্রশ্ন যোগ করা হয়নি। ড্যাশবোর্ড → FAQ থেকে যোগ করুন।</p>';
	}
	$o = '<div class="hc-faq"><input type="search" class="form-control mb-3" id="hcFaqSearch" placeholder="প্রশ্ন খুঁজুন…" aria-label="FAQ খুঁজুন"><div class="accordion" id="hcFaq">';
	$ld = array();
	$i  = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		++$i;
		$ans  = apply_filters( 'the_content', get_the_content() );
		$ld[] = array( '@type' => 'Question', 'name' => get_the_title(), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $ans ) ) );
		$o   .= '<div class="accordion-item" data-q="' . esc_attr( mb_strtolower( get_the_title() . ' ' . wp_strip_all_tags( $ans ) ) ) . '"><h2 class="accordion-header"><button class="accordion-button ' . ( 1 === $i ? '' : 'collapsed' ) . '" type="button" data-bs-toggle="collapse" data-bs-target="#hcf' . (int) $i . '" aria-expanded="' . ( 1 === $i ? 'true' : 'false' ) . '">' . esc_html( get_the_title() ) . '</button></h2><div id="hcf' . (int) $i . '" class="accordion-collapse collapse ' . ( 1 === $i ? 'show' : '' ) . '" data-bs-parent="#hcFaq"><div class="accordion-body">' . $ans . '</div></div></div>'; // phpcs:ignore
	}
	wp_reset_postdata();
	$o .= '</div><p class="text-muted mt-3 d-none" id="hcFaqNone">কিছু পাওয়া যায়নি।</p></div>';
	$o .= '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>';
	return $o;
} );

/** [hitscam_contact] — nonce + honeypot + wp_mail */
add_shortcode( 'hitscam_contact', function () {
	$msg = '';
	if ( isset( $_GET['hc_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '1' === $_GET['hc_sent'] ? '<div class="alert alert-success">ধন্যবাদ! আপনার বার্তা পাঠানো হয়েছে।</div>' : '<div class="alert alert-danger">বার্তা পাঠানো যায়নি — সব ঘর ঠিকভাবে পূরণ করুন।</div>'; // phpcs:ignore
	}
	$o  = $msg . '<form class="panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
	$o .= '<input type="hidden" name="action" value="hitscam_contact">' . wp_nonce_field( 'hitscam_contact', '_hcn', true, false );
	$o .= '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">';
	$o .= '<div class="row g-3"><div class="col-md-6"><label class="form-label" for="hcc_name">আপনার নাম *</label><input class="form-control" id="hcc_name" name="name" required maxlength="80"></div>';
	$o .= '<div class="col-md-6"><label class="form-label" for="hcc_phone">ফোন/ইমেইল *</label><input class="form-control" id="hcc_phone" name="contact" required maxlength="120"></div>';
	$o .= '<div class="col-12"><label class="form-label" for="hcc_msg">বার্তা *</label><textarea class="form-control" id="hcc_msg" name="message" rows="5" required maxlength="2000"></textarea></div>';
	$o .= '<div class="col-12"><button class="btn btn-brand" type="submit">পাঠান</button></div></div></form>';
	return $o;
} );

function hitscam_handle_contact() {
	$ok = false;
	if ( isset( $_POST['_hcn'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_hcn'] ) ), 'hitscam_contact' ) && empty( $_POST['website'] ) ) {
		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$contact = sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( $name && $contact && mb_strlen( $message ) >= 5 ) {
			$to = hitscam_opt( 'email', get_option( 'admin_email' ) ) ?: get_option( 'admin_email' );
			$ok = wp_mail( $to, '[' . get_bloginfo( 'name' ) . '] নতুন বার্তা — ' . $name, "নাম: $name\nযোগাযোগ: $contact\n\n$message" );
			do_action( 'hitscam_contact_submitted', array( 'name' => $name, 'contact' => $contact ), $ok );
		}
	}
	wp_safe_redirect( add_query_arg( 'hc_sent', $ok ? '1' : '0', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_hitscam_contact', 'hitscam_handle_contact' );
add_action( 'admin_post_hitscam_contact', 'hitscam_handle_contact' );
