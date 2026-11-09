<?php
/**
 * Fallback template. Specific templates (front-page.php, single.php, ...) are added as each page is built.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="if-container">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php if ( is_singular() ) : ?>
					<h1><?php the_title(); ?></h1>
					<?php the_content(); ?>
				<?php else : ?>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Nothing here yet', 'internfeel' ); ?></h1>
	<?php endif; ?>
</div>
<?php
get_footer();
