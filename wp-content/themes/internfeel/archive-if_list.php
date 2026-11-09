<?php
/**
 * All listicles (/lists/): one bordered item per list with its intro and how many experiences it has.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="if-container if-list">
	<h1 class="heading-1"><?php esc_html_e( 'Listicles', 'internfeel' ); ?></h1>
	<p class="if-muted body-lg"><?php esc_html_e( 'Experiences picked by the InternFeel team, gathered by theme.', 'internfeel' ); ?></p>

	<?php if ( ! have_posts() ) : ?>
		<p><?php esc_html_e( 'No listicles yet.', 'internfeel' ); ?></p>
	<?php else : ?>
		<ul class="if-list__all">
			<?php
			while ( have_posts() ) :
				the_post();
				$if_count = if_core_active() ? count( \InternFeel\Lists\Lists::posts( get_the_ID() ) ) : 0;
				?>
				<li class="if-list__item">
					<h2 class="heading-3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php if ( has_excerpt() ) : ?>
						<p class="if-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
					<span class="small if-muted">
						<?php
						/* translators: %s: number of experiences. */
						echo esc_html( sprintf( _n( '%s experience', '%s experiences', $if_count, 'internfeel' ), number_format_i18n( $if_count ) ) );
						?>
					</span>
				</li>
			<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
