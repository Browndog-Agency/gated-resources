<?php
/**
 * @var int    $post_id
 * @var string $turnstile_key
 * @var string $privacy_url
 * @var string $consent_label
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<form class="gr-form" id="gr-form" novalidate
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_form' ) ); ?>"
	data-page="<?php echo esc_attr( get_permalink( $post_id ) ); ?>"
	data-pagename="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">

	<p class="gr-form__intro"><?php esc_html_e( 'Complete the form below to access this resource and our full library.', 'gated-resources' ); ?></p>

	<div class="gr-field">
		<label for="gr-firstname"><?php esc_html_e( 'First name', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-firstname" name="firstname" required>
	</div>
	<div class="gr-field">
		<label for="gr-lastname"><?php esc_html_e( 'Last name', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-lastname" name="lastname" required>
	</div>
	<div class="gr-field">
		<label for="gr-email"><?php esc_html_e( 'Work email', 'gated-resources' ); ?> *</label>
		<input type="email" id="gr-email" name="email" required>
	</div>
	<div class="gr-field">
		<label for="gr-company"><?php esc_html_e( 'Organisation / Council', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-company" name="company" required>
	</div>
	<div class="gr-field">
		<label for="gr-jobtitle"><?php esc_html_e( 'Job title', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-jobtitle" name="jobtitle" required>
	</div>

	<?php /* Honeypot: hidden from humans, tempting to bots. */ ?>
	<div class="gr-hp" aria-hidden="true">
		<label>Company URL<input type="text" name="gr_company_url" tabindex="-1" autocomplete="off"></label>
	</div>

	<div class="gr-field gr-field--consent">
		<label>
			<input type="checkbox" name="consent" value="1">
			<?php echo esc_html( $consent_label ); ?>
		</label>
	</div>

	<?php if ( $turnstile_key ) : ?>
		<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_key ); ?>"></div>
	<?php endif; ?>

	<?php if ( $privacy_url ) : ?>
		<p class="gr-form__privacy">
			<?php
			printf(
				/* translators: %s: privacy policy link */
				esc_html__( 'We process your details to deliver this resource. See our %s for how we handle your data.', 'gated-resources' ),
				'<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'gated-resources' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<button type="submit" class="gr-btn gr-btn--primary"><?php esc_html_e( 'Access resource', 'gated-resources' ); ?></button>
	<p class="gr-form__msg" role="alert" aria-live="polite"></p>
</form>
