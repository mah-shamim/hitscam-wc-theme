<?php
/** robots.txt (ভার্চুয়াল) ও .htaccess এডিটর */
defined( 'ABSPATH' ) || exit;

function hcseo_robots_default() {
	return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /cart/\nDisallow: /checkout/\nDisallow: /my-account/\nDisallow: /*?orderby=\nDisallow: /*?min_price=\nDisallow: /*?add-to-cart=\n";
}
add_filter( 'robots_txt', function ( $out, $public ) {
	$custom = get_option( 'hcseo_robots', '' );
	return '' !== trim( (string) $custom ) ? $custom : $out;
}, 10, 2 );

function hcseo_htaccess_path() { return get_home_path() . '.htaccess'; }
function hcseo_htaccess_snippets() {
	return array(
		'gzip'    => "<IfModule mod_deflate.c>\nAddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml\n</IfModule>",
		'cache'   => "<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType image/webp \"access plus 1 year\"\nExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\nExpiresByType text/css \"access plus 1 month\"\nExpiresByType application/javascript \"access plus 1 month\"\n</IfModule>",
		'noindex' => "<FilesMatch \"\\.(log|sql|bak)\$\">\nRequire all denied\n</FilesMatch>",
		'https'   => "RewriteEngine On\nRewriteCond %{HTTPS} off\nRewriteRule ^(.*)\$ https://%{HTTP_HOST}/\$1 [L,R=301]",
	);
}
