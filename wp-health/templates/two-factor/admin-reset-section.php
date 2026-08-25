<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<h2 id="wp-umbrella-two-factor"><?php echo esc_html__('Two-factor authentication', 'wp-health'); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><?php echo esc_html__('Status', 'wp-health'); ?></th>
			<td>
				<?php if ($enrolled) : ?>
					<p><?php echo esc_html__('Enabled with an authenticator app.', 'wp-health'); ?></p>
					<p class="description">
						<?php
                            printf(
                                /* translators: %d: number of recovery codes left. */
                                esc_html(_n('%d recovery code left.', '%d recovery codes left.', $remaining, 'wp-health')),
                                (int) $remaining
                            );
						?>
					</p>
				<?php elseif ($policyOn) : ?>
					<p><?php echo esc_html__('Required on this site. This user will be asked to set it up the next time they log in.', 'wp-health'); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__('Not set up. This site does not require it.', 'wp-health'); ?></p>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ($enrolled) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__('Reset', 'wp-health'); ?></th>
				<td>
					<?php
                        // The form itself lives in the footer, outside the
                        // profile form. Only the button is here, tied to it by
                        // the HTML5 form attribute.
                        submit_button(
                            esc_html__('Reset two-factor authentication', 'wp-health'),
                            'delete',
                            'wp-umbrella-2fa-reset-submit',
                            false,
                            ['form' => $formId]
                        );
					?>
					<p class="description">
						<?php echo esc_html__('Use this when the user has lost their phone and their recovery codes. Their authenticator app and every remaining recovery code stop working right away.', 'wp-health'); ?>
					</p>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
