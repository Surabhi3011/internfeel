<?php
/**
 * Comments on an experience (approved-designs README, Experience page section 5): count, a sign-in
 * prompt or the form, then the list with one level of replies. Edit once and Report arrive on day 9.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

$if_count  = (int) get_comments_number();
$if_author = (int) get_post_field( 'post_author', get_the_ID() );
$if_anon   = (bool) get_post_meta( get_the_ID(), '_if_is_anonymous', true );

/**
 * One comment, in the design's markup. WordPress closes the <li>.
 *
 * @param WP_Comment           $comment Comment.
 * @param array<string, mixed> $args    wp_list_comments() arguments.
 * @param int                  $depth   Depth, 1 for top level.
 */
$if_comment = static function ( WP_Comment $comment, array $args, int $depth ) use ( $if_author, $if_anon ): void {
	$is_author = (int) $comment->user_id > 0 && (int) $comment->user_id === $if_author;
	// On an anonymous experience the author's own replies stay anonymous too.
	$name    = $is_author && $if_anon ? __( 'Anonymous', 'internfeel' ) : get_comment_author( $comment );
	$edited  = (string) get_comment_meta( (int) $comment->comment_ID, '_if_edited_at', true );
	$caps    = $is_author && $if_anon ? 0 : (int) get_user_meta( (int) $comment->user_id, '_if_verified_count', true );
	$initial = mb_strtoupper( mb_substr( $name, 0, 1 ) );
	?>
	<li id="comment-<?php comment_ID(); ?>" <?php comment_class( 'if-comment', $comment ); ?>>
		<span class="if-avatar<?php echo $is_author && $if_anon ? ' if-avatar--anon' : ''; ?>" aria-hidden="true"><?php echo $is_author && $if_anon ? '' : esc_html( $initial ); ?></span>
		<div class="if-comment__body">
			<div class="if-comment__head">
				<span class="if-byline"><?php echo esc_html( $name ); ?><?php if_the_badges( $caps ); ?></span>
				<?php if ( $is_author ) : ?>
					<span class="if-status"><?php esc_html_e( 'Author', 'internfeel' ); ?></span>
				<?php endif; ?>
				<time class="small if-muted" datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>">
					<?php
					/* translators: %s: time, such as "2 days". */
					echo esc_html( sprintf( __( '%s ago', 'internfeel' ), human_time_diff( (int) get_comment_date( 'U', $comment ) ) ) );
					echo '' !== $edited ? ' · ' . esc_html__( 'edited', 'internfeel' ) : '';
					?>
				</time>
			</div>
			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p class="small if-muted"><?php esc_html_e( 'Your comment is waiting for review.', 'internfeel' ); ?></p>
			<?php endif; ?>
			<div class="if-comment__text"><?php comment_text( $comment ); ?></div>
			<div class="if-comment__actions">
				<?php
				comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'      => $depth,
							'max_depth'  => (int) $args['max_depth'],
							'reply_text' => __( 'Reply', 'internfeel' ),
						)
					),
					$comment
				);
				?>
			</div>
		</div>
	<?php
};
?>
<section id="comments" class="if-container if-comments" aria-labelledby="if-comments-title">
	<h2 class="heading-2" id="if-comments-title">
		<?php
		/* translators: %s: number of comments. */
		echo esc_html( 0 === $if_count ? __( 'Comments', 'internfeel' ) : sprintf( _n( '%s comment', '%s comments', $if_count, 'internfeel' ), number_format_i18n( $if_count ) ) );
		?>
	</h2>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="if-comments__signin">
			<p><?php esc_html_e( 'Log in to ask a question or add your own tips.', 'internfeel' ); ?></p>
			<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink() . '#respond' ), home_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Log in to comment', 'internfeel' ); ?></a>
		</div>
	<?php elseif ( comments_open() ) : ?>
		<?php
		comment_form(
			array(
				'title_reply'          => __( 'Add a comment', 'internfeel' ),
				'title_reply_before'   => '<h3 id="reply-title" class="heading-3">',
				'title_reply_after'    => '</h3>',
				'class_form'           => 'if-form if-comments__form',
				'class_submit'         => 'if-btn if-btn--primary',
				'label_submit'         => __( 'Post comment', 'internfeel' ),
				'logged_in_as'         => '',
				'comment_notes_before' => '',
				'comment_field'        => '<div class="if-field"><label for="comment">' . esc_html__( 'Your comment', 'internfeel' ) . '</label><textarea id="comment" name="comment" rows="4" maxlength="5000" required></textarea></div>',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( have_comments() ) : ?>
		<ol class="if-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'callback'    => $if_comment,
					'max_depth'   => 2,
					'avatar_size' => 0,
				)
			);
			?>
		</ol>
	<?php endif; ?>
</section>
