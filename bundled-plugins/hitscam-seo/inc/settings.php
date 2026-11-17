<?php
defined( 'ABSPATH' ) || exit;

/** স্কিমা-চালিত সেটিংস: ট্যাব => [ key => [type,label,default,desc] ] */
function hcseo_schema() {
	$tpl = '%%post_title%% %%sep%% %%sitetitle%%';
	return array(
		'titles' => array(
			'title_sep'        => array( 'text', 'সেপারেটর', '-', 'যেমন - | » •' ),
			'home_title'       => array( 'text', 'হোম টাইটেল', '%%sitetitle%% %%sep%% %%sitedesc%%', '' ),
			'home_desc'        => array( 'textarea', 'হোম মেটা ডেসক্রিপশন', '%%sitedesc%%', '' ),
			'post_title'       => array( 'text', 'পোস্ট টাইটেল', $tpl, '' ),
			'post_desc'        => array( 'textarea', 'পোস্ট ডেসক্রিপশন', '%%post_excerpt%%', '' ),
			'page_title'       => array( 'text', 'পেজ টাইটেল', $tpl, '' ),
			'page_desc'        => array( 'textarea', 'পেজ ডেসক্রিপশন', '%%post_excerpt%%', '' ),
			'product_title'    => array( 'text', 'প্রোডাক্ট টাইটেল', '%%post_title%% — %%product_price%% %%sep%% %%sitetitle%%', '' ),
			'product_desc'     => array( 'textarea', 'প্রোডাক্ট ডেসক্রিপশন', '%%post_excerpt%% (মূল্য %%product_price%%)', '' ),
			'term_title'       => array( 'text', 'ক্যাটেগরি/ট্যাগ টাইটেল', '%%term_title%% %%sep%% %%sitetitle%%', '' ),
			'term_desc'        => array( 'textarea', 'ক্যাটেগরি/ট্যাগ ডেসক্রিপশন', '%%term_description%%', '' ),
			'archive_title'    => array( 'text', 'আর্কাইভ টাইটেল', '%%archive_title%% %%sep%% %%sitetitle%%', '' ),
			'search_title'     => array( 'text', 'সার্চ টাইটেল', '"%%search_keywords%%" %%sep%% %%sitetitle%%', '' ),
			'notfound_title'   => array( 'text', '404 টাইটেল', 'পেজ পাওয়া যায়নি %%sep%% %%sitetitle%%', '' ),
			'noindex_search'   => array( 'checkbox', 'সার্চ রেজাল্ট noindex', 1, '' ),
			'noindex_author'   => array( 'checkbox', 'অথর আর্কাইভ noindex', 1, '' ),
			'noindex_date'     => array( 'checkbox', 'ডেট আর্কাইভ noindex', 1, '' ),
			'noindex_tags'     => array( 'checkbox', 'পণ্য ট্যাগ noindex', 0, '' ),
			'noindex_filtered' => array( 'checkbox', 'ফিল্টার/সর্ট প্যারামিটারযুক্ত শপ URL noindex', 1, '' ),
		),
		'social' => array(
			'og_enable'     => array( 'checkbox', 'Open Graph চালু', 1, '' ),
			'tw_enable'     => array( 'checkbox', 'X (Twitter) Card চালু', 1, '' ),
			'tw_card'       => array( 'select', 'X কার্ড টাইপ', 'summary_large_image', array( 'summary' => 'summary', 'summary_large_image' => 'summary_large_image' ) ),
			'tw_site'       => array( 'text', 'X ইউজারনেম', '', '@handle' ),
			'fb_app_id'     => array( 'text', 'Facebook App ID', '', '' ),
			'default_image' => array( 'text', 'ডিফল্ট শেয়ার ইমেজ URL', '', '১২০০×৬৩০ প্রস্তাবিত' ),
			'dublin_core'   => array( 'checkbox', 'Dublin Core মেটা', 0, 'টাইটেল/বিবরণ — Bing, Yandex, ডিরেক্টরি' ),
		),
		'schema' => array(
			'schema_enable' => array( 'checkbox', 'স্ট্রাকচার্ড ডেটা (JSON-LD)', 1, '' ),
			'kg_type'       => array( 'select', 'Knowledge Graph — সাইট কার', 'Organization', array( 'Organization' => 'Organization', 'Person' => 'Person' ) ),
			'kg_name'       => array( 'text', 'নাম', '', 'খালি হলে সাইট টাইটেল' ),
			'kg_logo'       => array( 'text', 'লোগো URL', '', '' ),
			'kg_same_as'    => array( 'textarea', 'সোশ্যাল প্রোফাইল (প্রতি লাইনে ১টি URL)', '', '' ),
			'kg_phone'      => array( 'text', 'ফোন', '', '+8801…' ),
			'lb_enable'     => array( 'checkbox', 'Local Business স্কিমা', 0, '' ),
			'lb_type'       => array( 'text', 'বিজনেস টাইপ', 'Store', 'Store, Restaurant, LocalBusiness…' ),
			'lb_street'     => array( 'text', 'রাস্তা', '', '' ),
			'lb_city'       => array( 'text', 'শহর', '', '' ),
			'lb_region'     => array( 'text', 'বিভাগ', '', '' ),
			'lb_postal'     => array( 'text', 'পোস্টকোড', '', '' ),
			'lb_country'    => array( 'text', 'দেশ কোড', 'BD', '' ),
			'lb_hours'      => array( 'textarea', 'খোলার সময় (প্রতি লাইনে: Mo-Fr 09:00-18:00)', '', '' ),
			'lb_price'      => array( 'text', 'মূল্যসীমা', '৳৳', '' ),
			'sitelinks'     => array( 'checkbox', 'WebSite + SearchAction (সাইটলিংক সার্চবক্স)', 1, '' ),
			'breadcrumbs'   => array( 'checkbox', 'ব্রেডক্রাম্ব স্কিমা', 1, 'ব্রেডক্রাম্ব UI: [hitscam_breadcrumbs] বা hitscam_seo_breadcrumbs()' ),
			'bc_sep'        => array( 'text', 'ব্রেডক্রাম্ব সেপারেটর', '›', '' ),
			'wc_meta'       => array( 'checkbox', 'WooCommerce প্রাইস/কারেন্সি মেটা ট্যাগ (product:price:amount…)', 1, '' ),
		),
		'sitemaps' => array(
			'sm_enable'   => array( 'checkbox', 'XML সাইটম্যাপ (/sitemap_index.xml)', 1, '' ),
			'sm_types'    => array( 'text', 'পোস্ট টাইপ (কমা)', 'post,page,product', '' ),
			'sm_tax'      => array( 'text', 'ট্যাক্সোনমি (কমা)', 'category,product_cat', '' ),
			'sm_images'   => array( 'checkbox', 'ইমেজ ট্যাগ', 1, '' ),
			'sm_news'     => array( 'checkbox', 'Google News সাইটম্যাপ (/sitemap-news.xml)', 0, 'শেষ ৪৮ ঘণ্টার পোস্ট' ),
			'sm_news_pub' => array( 'text', 'News প্রকাশক নাম', '', '' ),
			'sm_video'    => array( 'checkbox', 'ভিডিও সাইটম্যাপ (/sitemap-video.xml)', 0, 'কনটেন্টে YouTube/Vimeo এম্বেড থাকলে' ),
			'sm_rss'      => array( 'checkbox', 'RSS সাইটম্যাপ (/sitemap-rss.xml)', 0, 'সাম্প্রতিক ৫০ আইটেম' ),
		),
		'indexing' => array(
			'in_now'       => array( 'checkbox', 'IndexNow (Bing/Yandex) অটো-পিং', 0, 'পাবলিশ/আপডেটে URL পাঠায়' ),
			'in_key'       => array( 'text', 'IndexNow Key', '', 'খালি রাখলে অটো তৈরি; /{key}.txt সার্ভ হয়' ),
			'in_google'    => array( 'checkbox', 'Google Indexing API অটো-নোটিফাই', 0, 'সার্ভিস অ্যাকাউন্ট দরকার (নিচে)' ),
			'in_google_sa' => array( 'textarea', 'Google সার্ভিস অ্যাকাউন্ট JSON', '', 'Search Console-এ ঐ ইমেইলকে Owner করতে হবে। টোকেন Google OAuth-এ যাচাই হয়।' ),
		),
		'advanced' => array(
			'redirect_404'  => array( 'checkbox', '404 মনিটর চালু', 1, '' ),
			'redirect_auto' => array( 'checkbox', 'স্লাগ বদলালে অটো রিডাইরেক্ট', 1, '' ),
			'psi_key'       => array( 'text', 'PageSpeed Insights API Key', '', 'ঐচ্ছিক (কোটা বাড়ে)' ),
			'ai_endpoint'   => array( 'text', 'AI এন্ডপয়েন্ট (OpenAI-সামঞ্জস্য)', 'https://api.openai.com/v1/chat/completions', '' ),
			'ai_key'        => array( 'text', 'AI API Key', '', 'টাইটেল/ডেসক্রিপশন জেনারেট করতে' ),
			'ai_model'      => array( 'text', 'AI মডেল', 'gpt-4o-mini', '' ),
			'gsc_site'      => array( 'text', 'Search Console সাইট URL', '', 'যেমন https://example.com/ বা sc-domain:example.com (Indexing-এর সার্ভিস অ্যাকাউন্ট ব্যবহার হয়)' ),
		),
	);
}

function hcseo_defaults() {
	$d = array();
	foreach ( hcseo_schema() as $fields ) { foreach ( $fields as $k => $f ) { $d[ $k ] = $f[2]; } }
	return $d;
}
function hcseo_opts() {
	static $c = null;
	if ( null === $c ) { $c = array_merge( hcseo_defaults(), (array) get_option( 'hcseo', array() ) ); }
	return $c;
}
function hcseo_opt( $k ) { $o = hcseo_opts(); return $o[ $k ] ?? ''; }

function hcseo_save_group( $group, $in ) {
	$sc  = hcseo_schema();
	if ( ! isset( $sc[ $group ] ) ) { return; }
	$cur = array_merge( hcseo_defaults(), (array) get_option( 'hcseo', array() ) );
	foreach ( $sc[ $group ] as $k => $f ) {
		$v = $in[ $k ] ?? '';
		switch ( $f[0] ) {
			case 'checkbox': $cur[ $k ] = empty( $v ) ? 0 : 1; break;
			case 'textarea': $cur[ $k ] = sanitize_textarea_field( $v ); break;
			case 'select':   $cur[ $k ] = isset( $f[3][ $v ] ) ? $v : $f[2]; break;
			default:         $cur[ $k ] = sanitize_text_field( $v );
		}
	}
	if ( 'indexing' === $group && ! $cur['in_key'] ) { $cur['in_key'] = bin2hex( random_bytes( 16 ) ); }
	update_option( 'hcseo', $cur );
	flush_rewrite_rules( false );
}

/** ডাইনামিক ভেরিয়েবল */
function hcseo_vars_list() {
	return array( 'sitetitle', 'sitedesc', 'sep', 'post_title', 'post_excerpt', 'post_content', 'post_date', 'post_author', 'post_category', 'post_tag', 'product_price', 'currency', 'term_title', 'term_description', 'archive_title', 'search_keywords', 'page', 'current_year' );
}
function hcseo_replace( $str, $ctx = array() ) {
	$p    = $ctx['post'] ?? null;
	$term = $ctx['term'] ?? null;
	$cat  = $tag = $price = '';
	if ( $p ) {
		$c = get_the_category( $p->ID ); $cat = $c ? $c[0]->name : '';
		$t = get_the_tags( $p->ID ); $tag = $t ? $t[0]->name : '';
		if ( 'product' === $p->post_type && function_exists( 'wc_get_product' ) && ( $pr = wc_get_product( $p->ID ) ) ) {
			$price = html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $pr ) ) ), ENT_QUOTES, 'UTF-8' );
		}
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$ex    = $p ? ( has_excerpt( $p ) ? $p->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 30, '…' ) ) : '';
	$map   = array(
		'sitetitle' => get_bloginfo( 'name' ), 'sitedesc' => get_bloginfo( 'description' ), 'sep' => hcseo_opt( 'title_sep' ),
		'post_title' => $p ? get_the_title( $p ) : '', 'post_excerpt' => $ex, 'post_content' => $p ? wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 40, '…' ) : '',
		'post_date' => $p ? get_the_date( '', $p ) : '', 'post_author' => $p ? get_the_author_meta( 'display_name', $p->post_author ) : '',
		'post_category' => $cat, 'post_tag' => $tag, 'product_price' => $price, 'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		'term_title' => $term ? $term->name : '', 'term_description' => $term ? wp_strip_all_tags( term_description( $term->term_id, $term->taxonomy ) ) : '',
		'archive_title' => wp_strip_all_tags( get_the_archive_title() ), 'search_keywords' => get_search_query( false ),
		'page' => $paged > 1 ? sprintf( 'পৃষ্ঠা %d', $paged ) : '', 'current_year' => gmdate( 'Y' ),
	);
	$map = apply_filters( 'hitscam_seo_variables', $map, $ctx );
	$out = preg_replace_callback( '/%%([a-z_]+)%%/', fn( $m ) => (string) ( $map[ $m[1] ] ?? $m[0] ), (string) $str );
	return trim( preg_replace( '/\s+/', ' ', preg_replace( '/(\s*[-–—|»•]\s*)+$/u', '', $out ) ) );
}

function hcseo_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cc = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$wpdb->prefix}hcseo_404 (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		url varchar(191) NOT NULL,
		hits int(10) unsigned NOT NULL DEFAULT 1,
		referrer varchar(255) NOT NULL DEFAULT '',
		last_seen datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY url (url)
	) $cc;" );
	dbDelta( "CREATE TABLE {$wpdb->prefix}hcseo_redirects (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source varchar(191) NOT NULL,
		target varchar(500) NOT NULL,
		code smallint(4) NOT NULL DEFAULT 301,
		hits int(10) unsigned NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY source (source)
	) $cc;" );
}
