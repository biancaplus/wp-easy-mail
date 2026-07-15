<?php
/**
 * 单个表单字段片段。
 *
 * 可用变量：$key、$definition、$field_settings、$field_id、$is_required、$extra_class。
 *
 * @package WPEasyMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes = trim( 'wpem-field ' . (string) $extra_class );
?>
<div class="<?php echo esc_attr( $classes ); ?>">
	<label for="<?php echo esc_attr( $field_id ); ?>">
		<?php echo esc_html( $field_settings['label'] ); ?>
		<?php if ( $is_required ) : ?>
			<span class="wpem-required" aria-hidden="true">*</span>
		<?php endif; ?>
	</label>

	<?php if ( 'textarea' === $definition['type'] ) : ?>
		<textarea
			id="<?php echo esc_attr( $field_id ); ?>"
			name="fields[<?php echo esc_attr( $key ); ?>]"
			placeholder="<?php echo esc_attr( $field_settings['placeholder'] ); ?>"
			<?php echo $is_required ? 'required aria-required="true"' : ''; ?>
		></textarea>
	<?php else : ?>
		<input
			id="<?php echo esc_attr( $field_id ); ?>"
			type="<?php echo esc_attr( $definition['type'] ); ?>"
			name="fields[<?php echo esc_attr( $key ); ?>]"
			placeholder="<?php echo esc_attr( $field_settings['placeholder'] ); ?>"
			<?php echo $is_required ? 'required aria-required="true"' : ''; ?>
		>
	<?php endif; ?>
	<span class="wpem-field-error" aria-live="polite"></span>
</div>
