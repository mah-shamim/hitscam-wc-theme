<?php
/** ইউনিভার্সাল SEO মেটাবক্স (সব পোস্ট টাইপ + টার্ম) + কনটেন্ট অ্যানালাইসিস + AI */
defined( 'ABSPATH' ) || exit;

function hcseo_fields() {
	return array( 'title' => 'text', 'desc' => 'textarea', 'canonical' => 'url', 'noindex' => 'bool', 'nofollow' => 'bool', 'og_title' => 'text', 'og_desc' => 'textarea', 'og_image' => 'url', 'keywords' => 'text' );
}

function hcseo_box_html( $id, $kind, $permalink ) {
	$m = hcseo_get_meta( $id, $kind );
	wp_nonce_field( 'hcseo_box', 'hcseo_nonce' );
	echo '<div class="hcseo-box" data-id="' . (int) $id . '" data-kind="' . esc_attr( $kind ) . '" data-url="' . esc_attr( $permalink ) . '" data-site="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
	echo '<div class="hcseo-tabs"><button type="button" data-t="meta" class="on">মেটা</button><button type="button" data-t="social">সোশ্যাল</button><button type="button" data-t="adv">অ্যাডভান্সড</button><button type="button" data-t="an">কনটেন্ট অ্যানালাইসিস</button></div>';
	echo '<div class="hcseo-pane" data-p="meta"><div class="hcseo-snip"><div class="u"></div><div class="t"></div><div class="d"></div></div>';
	printf( '<p><label>SEO টাইটেল <span class="c" data-for="title"></span></label><input type="text" name="hcseo[title]" value="%s" class="widefat" placeholder="%s"></p>', esc_attr( $m['title'] ), esc_attr( 'ডিফল্ট টেমপ্লেট (সেটিংস)' ) );
	printf( '<p><label>মেটা ডেসক্রিপশন <span class="c" data-for="desc"></span></label><textarea name="hcseo[desc]" rows="3" class="widefat">%s</textarea></p>', esc_textarea( $m['desc'] ) );
	printf( '<p><label>ফোকাস কিওয়ার্ড (কমা দিয়ে একাধিক)</label><input type="text" name="hcseo[keywords]" value="%s" class="widefat"></p>', esc_attr( $m['keywords'] ) );
	echo '<p><small>ভেরিয়েবল: ' . esc_html( implode( ' ', array_map( fn( $v ) => "%%$v%%", hcseo_vars_list() ) ) ) . '</small></p>';
	if ( hcseo_opt( 'ai_key' ) ) { echo '<p><button type="button" class="button hcseo-ai">✨ AI দিয়ে টাইটেল ও ডেসক্রিপশন বানান</button> <span class="hcseo-ai-msg"></span></p>'; }
	echo '</div><div class="hcseo-pane" data-p="social" hidden>';
	printf( '<p><label>সোশ্যাল টাইটেল</label><input type="text" name="hcseo[og_title]" value="%s" class="widefat"></p>', esc_attr( $m['og_title'] ) );
	printf( '<p><label>সোশ্যাল ডেসক্রিপশন</label><textarea name="hcseo[og_desc]" rows="2" class="widefat">%s</textarea></p>', esc_textarea( $m['og_desc'] ) );
	printf( '<p><label>সোশ্যাল ইমেজ URL</label><input type="url" name="hcseo[og_image]" value="%s" class="widefat"></p>', esc_attr( $m['og_image'] ) );
	echo '<div class="hcseo-card"><img alt="" hidden><div><b></b><span></span><i></i></div></div></div>';
	echo '<div class="hcseo-pane" data-p="adv" hidden>';
	printf( '<p><label>Canonical URL</label><input type="url" name="hcseo[canonical]" value="%s" class="widefat"></p>', esc_attr( $m['canonical'] ) );
	printf( '<p><label><input type="checkbox" name="hcseo[noindex]" value="1" %s> noindex (সার্চে দেখাবেন না)</label></p>', checked( $m['noindex'], '1', false ) );
	printf( '<p><label><input type="checkbox" name="hcseo[nofollow]" value="1" %s> nofollow</label></p>', checked( $m['nofollow'], '1', false ) );
	echo '</div><div class="hcseo-pane" data-p="an" hidden><p><button type="button" class="button hcseo-run">বিশ্লেষণ চালান</button> <small>এডিটরের (Gutenberg/ক্লাসিক) কনটেন্ট থেকে; পেজ-বিল্ডারে সেভ করা কনটেন্ট থেকে।</small></p><ul class="hcseo-res"></ul></div></div>';
}

add_action( 'add_meta_boxes', function () {
	foreach ( get_post_types( array( 'public' => true ) ) as $pt ) {
		if ( 'attachment' === $pt ) { continue; }
		add_meta_box( 'hcseo', 'HitsCam SEO', function ( $post ) { hcseo_box_html( $post->ID, 'post', get_permalink( $post ) ?: home_url( '/' ) ); }, $pt, 'normal', 'default' );
	}
} );
add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['hcseo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hcseo_nonce'] ) ), 'hcseo_box' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) { return; }
	hcseo_save_meta_fields( $id, 'post' );
} );
function hcseo_save_meta_fields( $id, $kind ) {
	$in = wp_unslash( $_POST['hcseo'] ?? array() ); // phpcs:ignore
	$f  = 'term' === $kind ? 'update_term_meta' : 'update_post_meta';
	$d  = 'term' === $kind ? 'delete_term_meta' : 'delete_post_meta';
	foreach ( hcseo_fields() as $k => $type ) {
		$v = $in[ $k ] ?? '';
		$v = 'bool' === $type ? ( $v ? '1' : '' ) : ( 'url' === $type ? esc_url_raw( $v ) : ( 'textarea' === $type ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) ) );
		'' === $v ? $d( $id, '_hcseo_' . $k ) : $f( $id, '_hcseo_' . $k, $v );
	}
}
// টার্ম এডিট ফর্ম (সব পাবলিক ট্যাক্সোনমি)
add_action( 'admin_init', function () {
	foreach ( get_taxonomies( array( 'public' => true ) ) as $tx ) {
		add_action( $tx . '_edit_form', function ( $term ) { echo '<h2>HitsCam SEO</h2>'; hcseo_box_html( $term->term_id, 'term', get_term_link( $term ) ); } );
		add_action( 'edited_' . $tx, function ( $tid ) { if ( isset( $_POST['hcseo_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hcseo_nonce'] ) ), 'hcseo_box' ) && current_user_can( 'manage_categories' ) ) { hcseo_save_meta_fields( $tid, 'term' ); } } );
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php' ), true ) ) { return; }
	wp_enqueue_style( 'hcseo-box', HCSEO_URL . 'assets/metabox.css', array(), HCSEO_VERSION );
	wp_enqueue_script( 'hcseo-box', HCSEO_URL . 'assets/metabox.js', array(), HCSEO_VERSION, true );
	wp_localize_script( 'hcseo-box', 'HCSEO', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'hcseo_ajax' ), 'sep' => hcseo_opt( 'title_sep' ), 'tplTitle' => hcseo_opt( 'post_title' ), 'tplDesc' => hcseo_opt( 'post_desc' ) ) );
} );

/** সেভ করা কনটেন্ট (পেজ-বিল্ডার ফলব্যাক) */
add_action( 'wp_ajax_hcseo_content', function () {
	check_ajax_referer( 'hcseo_ajax', 'nonce' );
	$id = absint( $_POST['id'] ?? 0 );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error(); }
	$p = get_post( $id );
	wp_send_json_success( array( 'html' => apply_filters( 'the_content', $p->post_content ), 'slug' => $p->post_name ) );
} );

/** AI: OpenAI-সামঞ্জস্য এন্ডপয়েন্ট */
add_action( 'wp_ajax_hcseo_ai', function () {
	check_ajax_referer( 'hcseo_ajax', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'অনুমতি নেই' ); }
	$o = hcseo_opts();
	if ( ! $o['ai_key'] ) { wp_send_json_error( 'AI Key সেট নেই' ); }
	$txt = mb_substr( wp_strip_all_tags( wp_unslash( $_POST['content'] ?? '' ) ), 0, 3500 );
	$kw  = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) );
	$r   = wp_remote_post( esc_url_raw( $o['ai_endpoint'] ), array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $o['ai_key'], 'Content-Type' => 'application/json' ),
		'body' => wp_json_encode( array( 'model' => $o['ai_model'], 'response_format' => array( 'type' => 'json_object' ), 'messages' => array(
			array( 'role' => 'system', 'content' => 'You write SEO metadata. Reply ONLY JSON {"title":"<=60 chars","description":"<=155 chars"} in the same language as the content.' ),
			array( 'role' => 'user', 'content' => "Focus keyword: $kw\nContent:\n$txt" ) ) ) ) ) );
	if ( is_wp_error( $r ) ) { wp_send_json_error( $r->get_error_message() ); }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	$c = json_decode( $j['choices'][0]['message']['content'] ?? '', true );
	if ( empty( $c['title'] ) ) { wp_send_json_error( 'AI উত্তর বোঝা যায়নি (HTTP ' . wp_remote_retrieve_response_code( $r ) . ')' ); }
	wp_send_json_success( array( 'title' => sanitize_text_field( $c['title'] ), 'description' => sanitize_text_field( $c['description'] ?? '' ) ) );
} );
