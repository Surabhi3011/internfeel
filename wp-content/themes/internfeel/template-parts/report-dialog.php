<?php
/**
 * The Report dialog, shared by every Report button on the page ([data-if-report]). report.js opens
 * it and posts to internfeel/v1/report. Signed-in readers only; the buttons link to log-in otherwise.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! if_core_active() ) {
	return;
}
?>
<dialog class="if-dialog" data-if-report-dialog data-rest="<?php echo esc_url( rest_url( 'internfeel/v1/' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" aria-labelledby="if-report-title">
	<form class="if-form" method="dialog">
		<h2 class="heading-3" id="if-report-title"><?php esc_html_e( 'Report this', 'internfeel' ); ?></h2>
		<fieldset class="if-field">
			<legend><?php esc_html_e( 'What is wrong?', 'internfeel' ); ?></legend>
			<div class="if-dialog__reasons">
				<?php foreach ( \InternFeel\Reports\Reports::reasons() as $if_value => $if_label ) : ?>
					<label class="if-check"><input type="radio" name="reason" value="<?php echo esc_attr( $if_value ); ?>" required> <span><?php echo esc_html( $if_label ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<div class="if-field">
			<label for="if-report-note"><?php esc_html_e( 'Anything an admin should know (optional)', 'internfeel' ); ?></label>
			<textarea id="if-report-note" name="note" rows="3" maxlength="1000"></textarea>
		</div>
		<p class="if-field__error if-form__status" role="alert" data-if-status></p>
		<div class="if-dialog__actions">
			<button class="if-btn if-btn--ghost" type="button" value="cancel" data-if-close><?php esc_html_e( 'Cancel', 'internfeel' ); ?></button>
			<button class="if-btn if-btn--primary" type="submit" value="send"><?php esc_html_e( 'Send report', 'internfeel' ); ?></button>
		</div>
	</form>
</dialog>
