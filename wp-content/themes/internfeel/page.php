<?php
/**
 * Plain pages such as About and Contact: heading-1 and body text at reading width, the same
 * layout as the legal pages without the "Last updated" line.
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
		<div class="if-legal__body"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
