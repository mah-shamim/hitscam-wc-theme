<?php
/** Template Name: FAQ / হেল্প সেন্টার */
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<main id="main" class="container pb-5"><?php hitscam_breadcrumbs(); ?><h1 class="page-title mb-3"><?php the_title(); ?></h1>
<?php while ( have_posts() ) { the_post(); the_content(); } ?></main>
<?php get_footer(); ?>
