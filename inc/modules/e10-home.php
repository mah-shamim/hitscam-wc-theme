<?php
/**
 * e10 — হোম পেজের ফিচার স্ট্রিপ (সারাদেশে ডেলিভারি / অরিজিনাল পণ্য …) অ্যাডমিন থেকে এডিট।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

const HITSCAM_STRIP_DEFAULT = "# আইকন (Bootstrap Icons)|শিরোনাম|সাব-টেক্সট — প্রতি লাইনে একটি, যত খুশি; সব লাইন মুছলে/# দিলে স্ট্রিপ লুকাবে\nbi-truck|সারাদেশে ডেলিভারি|দ্রুত ও নিরাপদ\nbi-shield-check|অরিজিনাল পণ্য|ওয়ারেন্টিসহ\nbi-cash-coin|ক্যাশ অন ডেলিভারি|হাতে পেয়ে পেমেন্ট\nbi-arrow-repeat|৭ দিনে রিটার্ন|ঝামেলাহীন";

add_action( 'admin_menu', function () {
	hitscam_e0_textarea_page( 'hitscam-strip', 'হোম ফিচার স্ট্রিপ', 'hitscam_strip', HITSCAM_STRIP_DEFAULT,
		'<p>হোম পেজে স্লাইডারের নিচের ফিচার বক্স। প্রতি লাইনে একটি: <code>আইকন|শিরোনাম|সাব-টেক্সট</code> (আইকন: <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>)। কোড থেকে: <code>add_filter(\'hitscam_home_strip\', …)</code>।</p>' );
}, 20 );

function hitscam_home_strip() {
	$out = array();
	foreach ( hitscam_e0_lines( 'hitscam_strip', HITSCAM_STRIP_DEFAULT ) as $r ) {
		if ( count( $r ) >= 2 && '' !== $r[1] ) { $out[] = array( $r[0] ?: 'bi-check-circle', $r[1], $r[2] ?? '' ); }
	}
	return apply_filters( 'hitscam_home_strip', $out );
}
