<?php
/**
 * Template Name: Legal page
 *
 * Privacy policy, terms of use, content and ad guidelines (approved-designs README, "Legal pages"):
 * heading-1 and body text at reading width with a "Last updated" date. No ads (inc/ads.php).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="if-container if-legal">
		<h1 class="heading-1"><?php the_title(); ?></h1>
		<p class="small if-muted">
			<?php
			/* translators: %s: date. */
			echo esc_html( sprintf( __( 'Last updated %s', 'internfeel' ), get_the_modified_date( 'j F Y' ) ) );
			?>
		</p>
		<div class="if-legal__body"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
