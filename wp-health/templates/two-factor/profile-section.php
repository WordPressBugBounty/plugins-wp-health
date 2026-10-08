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
					<p><?php echo esc_html__('Required on this site. You will be asked to set it up the next time you log in.', 'wp-health'); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__('Not set up. This site does not require it.', 'wp-health'); ?></p>
				<?php endif; ?>
			</td>
		</tr>

		<?php if (is_array($codes)) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__('New recovery codes', 'wp-health'); ?></th>
				<td>
					<p class="description"><?php echo esc_html__('Save these now. They are shown once and each one works a single time.', 'wp-health'); ?></p>
					<ul style="margin:12px 0;padding:12px;background:#f6f7f7;list-style:none;font-family:monospace;line-height:1.8;">
						<?php foreach ($codes as $code) : ?>
							<li><?php echo esc_html($code); ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endif; ?>

		<?php if ($enrolled) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__('Recovery codes', 'wp-health'); ?></th>
				<td>
					<?php
                        // The form itself lives in the footer, outside the
                        // profile form. Only the button is here, tied to it by
                        // the HTML5 form attribute.
                        submit_button(
                            esc_html__('Generate a new set', 'wp-health'),
                            'secondary',
                            'wp-umbrella-2fa-regenerate-submit',
                            false,
                            ['form' => $formIdRegenerate]
                        );
					?>
					<p class="description"><?php echo esc_html__('Generating a new set invalidates every code you have now.', 'wp-health'); ?></p>
				</td>
			</tr>

			<?php if ($canDisable) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__('Turn off', 'wp-health'); ?></th>
					<td>
						<?php
                            submit_button(
                                esc_html__('Turn off two-factor authentication', 'wp-health'),
                                'delete',
                                'wp-umbrella-2fa-disable-submit',
                                false,
                                ['form' => $formIdDisable]
                            );
						?>
					</td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>
	</tbody>
</table>
