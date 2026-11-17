<?php
defined( 'ABSPATH' ) || exit;

/** রিং-বাফার লগ (সর্বোচ্চ 300)। টোকেন/PII কখনো লগে যায় না। */
function hctrk_log( $type, $platform, $event, $status, $msg = '' ) {
	if ( ! hctrk_opt( 'general', 'logging' ) ) { return; }
	if ( 'ok' === $status && ! hctrk_opt( 'general', 'log_ok' ) ) { return; }
	$log = get_option( 'hctrk_logs', array() );
	array_unshift( $log, array( 't' => time(), 'type' => $type, 'p' => $platform, 'e' => $event, 's' => $status, 'm' => mb_substr( (string) $msg, 0, 400 ) ) );
	update_option( 'hctrk_logs', array_slice( $log, 0, 300 ), false );
}
