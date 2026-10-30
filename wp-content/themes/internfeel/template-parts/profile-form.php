<?php
/**
 * The profile fields (Profile::get / POST me): first sign-in on page-signup.php and the dashboard's
 * Profile tab. forms.js submits it.
 *
 * @package InternFeel
 *
 * @var array{submit?: string} $args
 */

defined( 'ABSPATH' ) || exit;

$if_profile = \InternFeel\Auth\Profile::get( get_current_user_id() );
$if_submit  = $args['submit'] ?? __( 'Save profile', 'internfeel' );
?>
	<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/me' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<div class="if-field">
			<label for="if-full-name"><?php esc_html_e( 'Full name', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
			<input type="text" id="if-full-name" name="full_name" autocomplete="name" maxlength="120" required aria-describedby="if-full-name-help" value="<?php echo esc_attr( (string) $if_profile['full_name'] ); ?>">
			<span class="if-field__help" id="if-full-name-help"><?php esc_html_e( 'Printed on your certificates.', 'internfeel' ); ?></span>
		</div>

		<div class="if-field">
			<label for="if-display-name"><?php esc_html_e( 'Display name', 'internfeel' ); ?></label>
			<input type="text" id="if-display-name" name="display_name" maxlength="60" aria-describedby="if-display-name-help" value="<?php echo esc_attr( (string) $if_profile['display_name'] ); ?>">
			<span class="if-field__help" id="if-display-name-help"><?php esc_html_e( 'Optional. The name shown on your experiences and comments. Leave it blank to show your full name.', 'internfeel' ); ?></span>
		</div>

		<div class="if-field">
			<label for="if-college"><?php esc_html_e( 'College', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
			<input type="text" id="if-college" name="college" autocomplete="organization" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['college'] ); ?>">
		</div>

		<div class="if-form__two">
			<div class="if-field">
				<label for="if-course"><?php esc_html_e( 'Course', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
				<input type="text" id="if-course" name="course" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['course'] ); ?>">
			</div>
			<div class="if-field">
				<label for="if-graduation-year"><?php esc_html_e( 'Graduation year', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
				<input type="number" id="if-graduation-year" name="graduation_year" inputmode="numeric" min="1980" max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 8 ) ); ?>" required value="<?php echo $if_profile['graduation_year'] ? esc_attr( (string) $if_profile['graduation_year'] ) : ''; ?>">
			</div>
		</div>

		<div class="if-field">
			<label for="if-linkedin"><?php esc_html_e( 'LinkedIn profile', 'internfeel' ); ?></label>
			<input type="url" id="if-linkedin" name="linkedin_url" inputmode="url" maxlength="200" placeholder="https://www.linkedin.com/in/your-name" aria-describedby="if-linkedin-help" value="<?php echo esc_attr( (string) $if_profile['linkedin_url'] ); ?>">
			<span class="if-field__help" id="if-linkedin-help"><?php esc_html_e( 'Optional. Hidden on experiences you post anonymously.', 'internfeel' ); ?></span>
		</div>

		<fieldset class="if-field" aria-describedby="if-under-18-help">
			<legend><?php esc_html_e( 'Are you under 18?', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
			<div class="if-segment">
				<label><input type="radio" name="is_under_18" value="0" required <?php checked( false === $if_profile['is_under_18'] ); ?>><span><?php esc_html_e( 'No', 'internfeel' ); ?></span></label>
				<label><input type="radio" name="is_under_18" value="1" required <?php checked( true === $if_profile['is_under_18'] ); ?>><span><?php esc_html_e( 'Yes', 'internfeel' ); ?></span></label>
			</div>
			<span class="if-field__help" id="if-under-18-help"><?php esc_html_e( 'If you are, we show you only non-personalised ads.', 'internfeel' ); ?></span>
		</fieldset>

		<p class="if-field__error if-form__status" role="alert" data-if-status></p>

		<button type="submit" class="if-btn if-btn--primary if-btn--block"><?php echo esc_html( $if_submit ); ?></button>
	</form>
