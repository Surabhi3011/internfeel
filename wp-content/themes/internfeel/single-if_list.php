<?php
/**
 * Blog post (/blog/{slug}/), formerly a listicle: heading-1 title, date and reading time, the
 * featured picture, the article at reading width, then any experience cards the admin added, in order.
 * One ad slot after the cards or the article, as on hub pages.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

the_post();

$if_cards = if_core_active()
	? array_map( array( \InternFeel\Search\Cards::class, 'data' ), \InternFeel\Lists\Lists::posts( get_the_ID() ) )
	: array();

$if_words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', get_the_ID() ) ) );
$if_minutes = max( 1, (int) ceil( $if_words / 200 ) );

get_header();
?>
<article class="if-container if-list if-article">
	<nav class="small if-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'internfeel' ); ?>">
		<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'if_list' ) ); ?>"><?php esc_html_e( 'Blog', 'internfeel' ); ?></a>
	</nav>
	<header class="if-article__head">
		<h1 class="heading-1"><?php the_title(); ?></h1>
		<p class="small if-muted">
			<time datetime="<?php echo esc_attr( (string) get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			&middot;
			<?php
			/* translators: %s: minutes. */
			echo esc_html( sprintf( _n( '%s min read', '%s min read', $if_minutes, 'internfeel' ), number_format_i18n( $if_minutes ) ) );
			?>
		</p>
	</header>
	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="if-article__image"><?php the_post_thumbnail( 'large' ); ?></figure>
	<?php endif; ?>
	<div class="if-legal__body if-article__body"><?php the_content(); ?></div>
	<?php if ( array() !== $if_cards ) : ?>
		<section class="if-article__cards" aria-labelledby="if-article-cards">
			<h2 class="heading-2" id="if-article-cards"><?php esc_html_e( 'Experiences from students', 'internfeel' ); ?></h2>
			<div class="if-grid"><?php if_the_cards( $if_cards, 'h3' ); ?></div>
		</section>
	<?php endif; ?>
	<?php if_the_ad( 'hub' ); ?>
</article>
<?php
get_footer();
