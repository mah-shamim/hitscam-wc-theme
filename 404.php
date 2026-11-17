<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container py-5 text-center"><h1 class="display-3 fw-bold text-brand">404</h1><p class="lead">পেজটি পাওয়া যায়নি।</p><a class="btn btn-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">হোমে ফিরুন</a></main>
<?php get_footer(); ?>
