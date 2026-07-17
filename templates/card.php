<?php
/**
 * 卡片表单模板（参考 006ip 联系我们页）。
 *
 * 可用变量：$form_id、$settings、$fields、$instance_id、$recaptcha_on、$theme_color。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$visible_keys = array();
foreach ( $fields as $key => $definition ) {
	$field_settings = isset( $settings['fields'][ $key ] ) ? $settings['fields'][ $key ] : $definition;
	if ( WPEM_Form_Types::is_field_visible( $field_settings, $definition ) ) {
		$visible_keys[] = $key;
	}
}

/**
 * 双列配对：姓名+公司、邮箱+电话；仅一方可见时仍单独输出该字段。
 *
 * @var array<int, array<int, string>>
 */
$pair_rows = array(
	array( 'name', 'company' ),
	array( 'email', 'phone' ),
);

$rendered_keys = array();
?>
<section
	class="wpem-form-wrap wpem-template-card"
	style="--wpem-accent: <?php echo esc_attr( $theme_color ); ?>;"
	data-frontend-script="<?php echo esc_url( $frontend_script_url ); ?>"
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
		data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
		data-site-key="<?php echo esc_attr( $site_key ); ?>"
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
			<?php foreach ( $pair_rows as $pair ) : ?>
				<?php
				$pair_visible = array_values(
					array_filter(
						$pair,
						static function ( $key ) use ( $visible_keys ) {
							return in_array( $key, $visible_keys, true );
						}
					)
				);
				if ( empty( $pair_visible ) ) {
					continue;
				}
				?>
				<?php if ( count( $pair_visible ) > 1 ) : ?>
					<div class="wpem-fields-row">
						<?php foreach ( $pair_visible as $key ) : ?>
							<?php
							WPEM_Form_Templates::render_field( $key, $fields[ $key ], $settings, $instance_id );
							$rendered_keys[] = $key;
							?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<?php
					$key = $pair_visible[0];
					WPEM_Form_Templates::render_field( $key, $fields[ $key ], $settings, $instance_id );
					$rendered_keys[] = $key;
					?>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php foreach ( $fields as $key => $definition ) : ?>
				<?php
				if ( in_array( $key, $rendered_keys, true ) ) {
					continue;
				}
				WPEM_Form_Templates::render_field( $key, $definition, $settings, $instance_id );
				?>
			<?php endforeach; ?>
		</div>

		<div class="wpem-submit-wrap">
			<button type="button" class="wpem-submit" data-loading-text="<?php esc_attr_e( '提交中…', 'wp-easy-mail' ); ?>">
				<?php echo esc_html( $settings['submit_text'] ); ?>
			</button>
		</div>

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
