<?php
/**
 * The Blog (/blog/), formerly the listicles: one card per post with its picture, date, title and
 * excerpt, newest first.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="if-container if-list if-blog">
	<h1 class="heading-1"><?php esc_html_e( 'Blog', 'internfeel' ); ?></h1>
	<p class="if-muted body-lg"><?php esc_html_e( 'Guides, tips and stories to help you find, choose and make the most of an internship.', 'internfeel' ); ?></p>

	<?php if ( ! have_posts() ) : ?>
		<p><?php esc_html_e( 'No posts yet.', 'internfeel' ); ?></p>
	<?php else : ?>
		<ul class="if-blog__all">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<li class="if-blog__item">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="if-blog__thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
							<?php the_post_thumbnail( 'medium_large', array( 'alt' => '' ) ); ?>
						</a>
					<?php endif; ?>
					<div class="if-blog__text">
						<span class="small if-muted"><?php echo esc_html( get_the_date() ); ?></span>
						<h2 class="heading-3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php $if_excerpt = get_the_excerpt(); ?>
						<?php if ( '' !== $if_excerpt ) : ?>
							<p class="if-muted"><?php echo esc_html( $if_excerpt ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
