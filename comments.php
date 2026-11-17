<?php
defined( 'ABSPATH' ) || exit;
if ( post_password_required() ) { return; }
?>
<section id="comments" class="mt-4"><?php if ( have_comments() ) : ?><h2 class="h5 fw-bold"><?php echo esc_html( hitscam_bn_digits( get_comments_number() ) ); ?>টি মন্তব্য</h2><ol class="list-unstyled"><?php wp_list_comments( array( 'style' => 'ol', 'avatar_size' => 40 ) ); ?></ol><?php endif; comment_form(); ?></section>
