<?php
/**
 * Listicle (/lists/{slug}/), from the approved-designs README "Pages not drawn": heading-1 title, the
 * intro at reading width, then the cards in the admin's order.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

the_post();

$if_cards = if_core_active()
	? array_map( array( \InternFeel\Search\Cards::class, 'data' ), \InternFeel\Lists\Lists::posts( get_the_ID() ) )
	: array();

get_header();
?>
<div class="if-container if-list">
	<nav class="small if-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'internfeel' ); ?>">
		<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'if_list' ) ); ?>"><?php esc_html_e( 'Listicles', 'internfeel' ); ?></a>
	</nav>
	<h1 class="heading-1"><?php the_title(); ?></h1>
	<div class="if-list__intro body-lg"><?php the_content(); ?></div>
	<?php if ( array() === $if_cards ) : ?>
		<p class="if-muted"><?php esc_html_e( 'Experiences for this list are on their way.', 'internfeel' ); ?></p>
	<?php else : ?>
		<div class="if-grid"><?php if_the_cards( $if_cards, 'h2' ); ?></div>
		<?php if_the_ad( 'hub' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
