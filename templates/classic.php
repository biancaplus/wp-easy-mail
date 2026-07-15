<?php
/**
 * 经典表单模板。
 *
 * 可用变量：$form_id、$settings、$fields、$instance_id、$recaptcha_on、$theme_color。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section
	class="wpem-form-wrap wpem-template-classic"
	style="--wpem-accent: <?php echo esc_attr( $theme_color ); ?>;"
	aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-title"
>
	<header class="wpem-form-header">
		<h2 id="<?php echo esc_attr( $instance_id ); ?>-title"><?php echo esc_html( get_the_title( $form_id ) ); ?></h2>
		<?php if ( ! empty( $settings['description'] ) ) : ?>
			<div class="wpem-form-description">
				<?php
				$description_lines = preg_split( '/\r\n|\r|\n/', (string) $settings['description'] );
				foreach ( $description_lines as $line ) {
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					echo '<p>' . esc_html( $line ) . '</p>';
				}
				?>
			</div>
		<?php endif; ?>
	</header>

	<form
		class="wpem-form"
		data-required-message="<?php echo esc_attr( $settings['required_message'] ); ?>"
		data-invalid-message="<?php echo esc_attr( $settings['invalid_message'] ); ?>"
		data-recaptcha="<?php echo $recaptcha_on ? '1' : '0'; ?>"
		novalidate
	>
		<input type="hidden" name="action" value="wpem_submit_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpem_submit_' . $form_id ) ); ?>">
		<div class="wpem-honeypot" aria-hidden="true">
			<label>
				<?php esc_html_e( '网站', 'wp-easy-mail' ); ?>
				<input type="text" name="website" tabindex="-1" autocomplete="off">
			</label>
		</div>

		<div class="wpem-fields">
			<?php foreach ( $fields as $key => $definition ) : ?>
				<?php WPEM_Form_Templates::render_field( $key, $definition, $settings, $instance_id ); ?>
			<?php endforeach; ?>
		</div>

		<button type="submit" class="wpem-submit" data-loading-text="<?php esc_attr_e( '提交中…', 'wp-easy-mail' ); ?>">
			<?php echo esc_html( $settings['submit_text'] ); ?>
		</button>

		<?php if ( $recaptcha_on ) : ?>
			<p class="wpem-recaptcha-terms">
				<?php
				echo wp_kses(
					__(
						'本网站受 reCAPTCHA 和 Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">隐私政策</a> 及 <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">服务条款</a> 保护。',
						'wp-easy-mail'
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				);
				?>
			</p>
		<?php endif; ?>

		<div class="wpem-response" role="status" aria-live="polite" hidden></div>
	</form>
</section>
