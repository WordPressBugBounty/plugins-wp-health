<?php
use WPUmbrella\Actions\Admin\Option;

include_once __DIR__ . '/ajax-response.php';

$data = wp_umbrella_get_service('GetSettingsData')->getData();

$wpUmbrellaApiKeyLinkTags = array(
	'a' => array(
		'href' => array(),
		'target' => array(),
		'rel' => array(),
		'style' => array(),
		'class' => array(),
	),
);

$wpUmbrellaApiKeyI18n = array(
	'copied' => __('Copied!', 'wp-health'),
	'securing_step_connection' => __('Establishing a secure connection', 'wp-health'),
	'securing_step_pairing' => __('Pairing your site with WP Umbrella', 'wp-health'),
	'securing_step_keys' => __('Generating your encryption keys', 'wp-health'),
	'securing_step_signing' => __('Signing and sealing the connection', 'wp-health'),
	'securing_step_finalizing' => __('Finalizing your setup', 'wp-health'),
	'securing_done' => __('Your site is connected', 'wp-health'),
	'select_a_workspace' => __('Select a workspace', 'wp-health'),
	'close' => esc_html__('Close', 'wp-health'),
	'not_authorized' => __('You are not authorized to perform this action.', 'wp-health'),
	'network_admin_required' => __('On a WordPress network, only a network administrator can connect a site to WP Umbrella. Ask yours to enter the API key for this site.', 'wp-health'),
	'api_key_invalid' => __('The API key is invalid.', 'wp-health'),
	'api_key_invalid_title' => __('Bad API Key', 'wp-health'),
	'api_key_invalid_support' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening link tag to the support mailbox, %2$s is the closing link tag. */
			__('The API key seems to be invalid. Please check it and try again. If the problem persists, %1$splease contact our support.%2$s', 'wp-health'),
			'<a href="mailto:support@wp-umbrella.com">',
			'</a>'
		),
		$wpUmbrellaApiKeyLinkTags
	),
	'limit_exceeded_title' => __('Limit excedeed', 'wp-health'),
	'limit_exceeded_free_plan' => __('You have reached the maximum number of sites allowed by your plan. (5 sites for the free plan)', 'wp-health'),
	'limit_exceeded_trial' => __('You have reached the maximum number of sites allowed by your plan. (5 sites during the trial)', 'wp-health'),
	'api_unreachable_title' => __('No answer from WP Umbrella', 'wp-health'),
	'api_unreachable_text' => __('Your site could not get an answer from WP Umbrella in time. Your settings have been kept, so simply try again in a moment. If it keeps happening, contact our support.', 'wp-health'),
	'unverified_channel_title' => __('Secure connection not verified', 'wp-health'),
	'unverified_channel_text' => __('Your site could not verify the security certificate of WP Umbrella, so it cannot be moved to another project right now. Your settings have been kept. Please contact our support.', 'wp-health'),
	'http_auth_required_title' => __('HTTP Auth credentials needed', 'wp-health'),
	'http_auth_required_text' => __('Your site is protected by HTTP Basic Authentication. Please fill in the HTTP Auth User and Password fields above.', 'wp-health'),
	'http_auth_invalid_title' => __('HTTP Auth credentials invalid', 'wp-health'),
	'http_auth_invalid_text' => __('The HTTP Auth User and Password you entered were rejected by your site. Please check them and try again.', 'wp-health'),
	'unknown_php_error' => __('Unknown PHP error', 'wp-health'),
	'critical_error_title' => __('WordPress critical error detected', 'wp-health'),
	'critical_error_intro' => esc_html__('Your WordPress site is currently throwing a PHP fatal error, which prevents WP Umbrella from connecting. This is not an issue with WP Umbrella itself: any plugin or external tool calling your site\'s REST API would fail the same way.', 'wp-health'),
	'critical_error_reported' => esc_html__('Error reported by your site:', 'wp-health'),
	'critical_error_how_to' => esc_html__('How to resolve it:', 'wp-health'),
	'critical_error_step_panel' => esc_html__('Open your hosting control panel (cPanel, Plesk, etc.) or connect via FTP/SFTP.', 'wp-health'),
	'critical_error_step_rename' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening HTML tag, %2$s is the matching closing tag. */
			__('Navigate to the file shown above and either fix it or rename it (for example, append %1$s.disabled%2$s to the filename) to disable it.', 'wp-health'),
			'<code>',
			'</code>'
		),
		array('code' => array())
	),
	'critical_error_step_reload' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening HTML tag, %2$s is the matching closing tag. */
			__('Reload your WordPress admin to confirm the error is gone, then click %1$sValidate%2$s again here.', 'wp-health'),
			'<strong>',
			'</strong>'
		),
		array('strong' => array())
	),
	'critical_error_footer' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening link tag to the support mailbox, %2$s is the closing link tag. */
			__('If you do not have access to your site\'s files, please forward this message to your hosting provider or developer. If you need help, our team is available at %1$ssupport@wp-umbrella.com%2$s.', 'wp-health'),
			'<a href="mailto:support@wp-umbrella.com" style="color:#2563eb;">',
			'</a>'
		),
		$wpUmbrellaApiKeyLinkTags
	),
	'connection_issue_title' => __('Connection Issue: Action Required', 'wp-health'),
	'connection_issue_intro' => esc_html__('We\'re currently unable to connect to your site. This is often due to the site\'s hosting firewall or security plugin mistakenly blocking WP Umbrella.', 'wp-health'),
	'connection_issue_resolve' => esc_html__('To Resolve This:', 'wp-health'),
	'connection_issue_whitelist' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening HTML tag, %2$s is the matching closing tag. */
			__('%1$s1. Whitelist Our Server IPs:%2$s Please ensure the following IPs are allowed access by your hosting provider or security settings:', 'wp-health'),
			'<strong>',
			'</strong>'
		),
		array('strong' => array())
	),
	'ipv4_label' => esc_html__('IPv4:', 'wp-health'),
	'ipv6_label' => esc_html__('IPv6:', 'wp-health'),
	'copy_all_ips_v4' => esc_html__('Copy all IPs v4', 'wp-health'),
	'copy_this_ip' => esc_html__('Copy this IP', 'wp-health'),
	'connection_issue_rest_api' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening HTML tag, %2$s is the matching closing tag. */
			__('%1$s2. Check WordPress REST API Access%2$s: Confirm that access to the WordPress REST API is not being restricted, as this is crucial for communication with WP Umbrella.', 'wp-health'),
			'<strong>',
			'</strong>'
		),
		array('strong' => array())
	),
	'connection_issue_guide' => wp_kses(
		sprintf(
			/* translators: %1$s is an opening link tag to the support guide, %2$s is the closing link tag. */
			__('For more details, feel free to check out our guide, "%1$sWe Can\'t Communicate with Your WordPress Site%2$s".', 'wp-health'),
			'<a href="https://support.wp-umbrella.com/en/articles/6-how-to-resolve-we-can-t-communicate-with-your-wordpress-site-error-in-wp-umbrella" target="_blank" style="color:#2563eb;">',
			'</a>'
		),
		$wpUmbrellaApiKeyLinkTags
	),
	'connection_issue_support' => esc_html__('If you need extra help, feel free to reach out to our support team at:', 'wp-health'),
	'connected_title' => __('Great, Your website is connected!', 'wp-health'),
	'connected_text' => __('Head to your dashboard to manage your WordPress sites more efficiently and start exploring the features that WP Umbrella has to offer. 🙂', 'wp-health'),
	'go_to_dashboard' => esc_html__('Go to the Dashboard', 'wp-health'),
);

?>

<style>
	.wpu-sec-overlay {
		position: absolute;
		inset: -12px;
		z-index: 20;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		text-align: center;
		border-radius: 18px;
		background: rgba(255, 255, 255, 0.82);
		-webkit-backdrop-filter: blur(6px);
		backdrop-filter: blur(6px);
		animation: wpu-sec-pop 260ms ease-out both;
	}
	.wpu-sec-emblem {
		position: relative;
		width: 96px;
		height: 96px;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 22px;
	}
	.wpu-sec-glow {
		position: absolute;
		inset: 8px;
		border-radius: 9999px;
		background: radial-gradient(circle, rgba(99, 102, 241, 0.55) 0%, rgba(99, 102, 241, 0) 70%);
		animation: wpu-sec-glow 1.8s ease-in-out infinite;
	}
	.wpu-sec-ring {
		position: absolute;
		border-radius: 9999px;
		border-style: solid;
	}
	.wpu-sec-ring-outer {
		inset: 0;
		border-width: 3px;
		border-color: rgba(99, 102, 241, 0.18);
		border-top-color: #4f46e5;
		border-right-color: #6366f1;
		animation: wpu-sec-spin 1.1s linear infinite;
	}
	.wpu-sec-ring-inner {
		inset: 16px;
		border-width: 2px;
		border-color: rgba(129, 140, 248, 0.22);
		border-bottom-color: #818cf8;
		animation: wpu-sec-spin-rev 1.5s linear infinite;
	}
	.wpu-sec-shield {
		width: 40px;
		height: 40px;
		color: #4f46e5;
	}
	.wpu-sec-title {
		font-size: 17px;
		font-weight: 600;
		color: #111827;
		margin-bottom: 6px;
	}
	.wpu-sec-step {
		font-size: 13.5px;
		color: #4b5563;
		min-height: 18px;
		transition: opacity 200ms ease;
	}
	.wpu-sec-track {
		position: relative;
		width: 240px;
		max-width: 70%;
		height: 6px;
		border-radius: 9999px;
		background: #e5e7eb;
		margin-top: 18px;
		overflow: hidden;
	}
	.wpu-sec-fill {
		position: absolute;
		inset: 0 auto 0 0;
		width: 8%;
		border-radius: 9999px;
		background: linear-gradient(90deg, #6366f1, #4f46e5);
		transition: width 320ms ease;
		overflow: hidden;
	}
	.wpu-sec-shimmer {
		position: absolute;
		top: 0;
		left: 0;
		height: 100%;
		width: 40%;
		background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0));
		animation: wpu-sec-shimmer 1.1s linear infinite;
	}
	@keyframes wpu-sec-spin { to { transform: rotate(360deg); } }
	@keyframes wpu-sec-spin-rev { to { transform: rotate(-360deg); } }
	@keyframes wpu-sec-glow {
		0%, 100% { opacity: 0.4; transform: scale(1); }
		50% { opacity: 0.75; transform: scale(1.14); }
	}
	@keyframes wpu-sec-shimmer {
		0% { transform: translateX(-120%); }
		100% { transform: translateX(320%); }
	}
	@keyframes wpu-sec-pop {
		0% { opacity: 0; transform: scale(0.97); }
		100% { opacity: 1; transform: scale(1); }
	}
</style>

<form id="wp_umbrella_valid_api_key" class="relative" action="<?php echo admin_url('admin-ajax.php'); ?>">
	<div class="space-y-4">
		<?php if($data['has_htpasswd']): ?>
		<p class="p-2 rounded-lg bg-indigo-50 border-indigo-100 border my-4 text-sm mt-8"><?php /* translators: %1$s is the .htpasswd file name, in bold. */ echo wp_kses(sprintf(__('We have detected the %1$s file on your site. If this is the case, you will need to specify the credentials so that we can communicate with your site.', 'wp-health'), '<strong>.htpasswd</strong>'), array('strong' => array())); ?>
		</p>

		<div>
			<label for="http_auth_user" class="text-sm font-medium text-gray-700 pl-3 mb-1"><?php echo esc_html__('HTTP Auth User', 'wp-health'); ?></label><input id="http_auth_user" name="http_auth_user" type="text"
				placeholder="<?php echo esc_attr__('HTTP Auth User', 'wp-health'); ?>"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				value="">
		</div>
		<div>
			<label for="http_auth_password" class="text-sm font-medium text-gray-700 pl-3 mb-1"><?php echo esc_html__('HTTP Auth Password', 'wp-health'); ?></label><input id="http_auth_password" name="http_auth_password" type="password"
				placeholder="<?php echo esc_attr__('HTTP Auth Password', 'wp-health'); ?>"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				value="">
		</div>
		<?php endif; ?>
		<div class="space-y-4">
			<div>
				<label for="apiKey" class="inline-block text-sm font-medium text-gray-700 pl-3 mb-1"><?php echo esc_html__('Your API Key', 'wp-health'); ?></label>
				<div class="flex items-center gap-4">
					<svg
						class="animate-spin font-semibold text-xs items-center justify-center rounded-full flex-none w-4 h-4 js-loader-check hidden"
						width="32"
						height="32"
						viewBox="0 0 32 32"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<rect
							class="animate-ring-fast"
							x="1"
							y="1"
							width="30"
							height="30"
							rx="15"
							stroke="currentColor"
							stroke-width="3"
							stroke-linejoin="round"
						/>
					</svg>
					<?php $isConnected = !empty($data['api_key']) || !empty($data['request_token']) || !empty($data['secret_token']); ?>
					<input id="apiKey" name="apiKey" type="<?php echo $isConnected ? 'password' : 'text' ?>" placeholder="<?php echo esc_attr__('My API KEY', 'wp-health'); ?>"
						class="appearance-none relative block px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm w-full"
						value="<?php if($isConnected) {
						    echo Option::SECURED_VALUE;
						} ?>">

				</div>
			</div>
			<div class="items-center gap-2 mt-2 text-red-600 text-sm js-error-message-container hidden">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="text-red-500 w-6 h-6"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
				<span class="js-error-message"></span>
			</div>
			<div class="items-center gap-2 mt-2 text-green-600 text-sm js-success-message-container hidden">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="text-green-500 w-6 h-6"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
				<span><?php /* translators: %1$s is an opening HTML tag, %2$s is the matching closing tag. */ echo wp_kses(sprintf(__('%1$sYour API key is valid!%2$s Click on Save. At the time of saving, we\'ll need to check communication with your site for security reasons.', 'wp-health'), '<strong>', '</strong>'), array('strong' => array())); ?></span>
			</div>

			<div class="js-container-workspaces hidden">
				<label for="workspaces" class="inline-block text-sm font-medium text-gray-700 pl-3 mb-1"><?php echo esc_html__('Choose a workspace', 'wp-health'); ?></label>
				<select id="workspaces" name="workspaces" class="block w-full rounded-full border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:max-w-xs sm:text-sm sm:leading-6">
					<option value="-1"><?php echo esc_html__('Select a workspace', 'wp-health'); ?></option>
				</select>
				<div class="items-center gap-2 mt-2 text-red-600 text-sm js-error-message-workspace hidden">
					<?php echo esc_html__('Please, select a workspace', 'wp-health'); ?>
				</div>
				<p class="text-gray-600 text-sm pl-3 mt-1">
					<?php echo esc_html__('Choose the workspace to which you wish to link your site.', 'wp-health'); ?>
				</p>
			</div>

			<button
				class="group relative flex gap-4 justify-center py-2 px-16 border border-transparent text-sm font-medium rounded-full text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mt-4 disabled:bg-indigo-300 disabled:cursor-not-allowed disabled:text-gray-50"
				disabled="disabled"
				type="submit"
			>
				<svg
					class="animate-spin font-semibold text-xs items-center justify-center rounded-full flex-none w-4 h-4 js-loader hidden"
					width="32"
					height="32"
					viewBox="0 0 32 32"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
				>
					<rect
						class="animate-ring-fast"
						x="1"
						y="1"
						width="30"
						height="30"
						rx="15"
						stroke="currentColor"
						stroke-width="3"
						stroke-linejoin="round"
					/>
				</svg>
				<?php echo esc_html__('Save', 'wp-health'); ?>
			</button>
		</div>
	</div>
	<div class="wpu-sec-overlay js-securing-overlay hidden">
		<div class="wpu-sec-emblem">
			<span class="wpu-sec-glow"></span>
			<span class="wpu-sec-ring wpu-sec-ring-outer"></span>
			<span class="wpu-sec-ring wpu-sec-ring-inner"></span>
			<svg class="wpu-sec-shield" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
				<path d="M9.2 12.2l1.9 1.9 3.7-3.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
		<div class="wpu-sec-title"><?php echo esc_html__('Securing your site', 'wp-health'); ?></div>
		<div class="wpu-sec-step js-securing-step"><?php echo esc_html__('Establishing a secure connection', 'wp-health'); ?></div>
		<div class="wpu-sec-track">
			<div class="wpu-sec-fill js-securing-fill"><span class="wpu-sec-shimmer"></span></div>
		</div>
	</div>

	<?php wp_nonce_field('wp_umbrella_valid_api_key', 'nonce_wp_umbrella_valid_api_key'); ?>
	<?php wp_nonce_field('wp_umbrella_check_api_key', 'nonce_wp_umbrella_check_api_key'); ?>

</form>

<script type="text/javascript">
	var wpUmbrellaApiKeyI18n = <?php echo wp_json_encode($wpUmbrellaApiKeyI18n); ?>;

	function copyToClipboard(element, text) {
		const value = text === undefined ?  "212.83.142.5\n212.83.175.107\n212.129.45.77" : text;

		const oldText = element.innerText

		const textareaTemp = document.createElement('textarea');
		textareaTemp.value = value;
		document.body.appendChild(textareaTemp);
		textareaTemp.select();
		document.execCommand('copy');
		document.body.removeChild(textareaTemp);
		 element.innerText = wpUmbrellaApiKeyI18n.copied;

		 setTimeout(() => {
			 element.innerText = oldText;
		 }, 2000);
	}

	document.addEventListener('DOMContentLoaded', function(){

		function debounce(func, wait) {
			let timeout;

			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};

				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}

		let workspaceSelected = null

		const securingSteps = [
			wpUmbrellaApiKeyI18n.securing_step_connection,
			wpUmbrellaApiKeyI18n.securing_step_pairing,
			wpUmbrellaApiKeyI18n.securing_step_keys,
			wpUmbrellaApiKeyI18n.securing_step_signing,
			wpUmbrellaApiKeyI18n.securing_step_finalizing
		];
		let securingStepTimer = null;
		let securingTrickleTimer = null;

		function wpuSecuringEls(){
			const form = document.getElementById('wp_umbrella_valid_api_key');
			return {
				overlay: form.querySelector('.js-securing-overlay'),
				step: form.querySelector('.js-securing-step'),
				fill: form.querySelector('.js-securing-fill')
			};
		}

		function wpuShowSecuring(){
			const { overlay, step, fill } = wpuSecuringEls();
			if(!overlay){ return; }

			overlay.classList.remove('hidden');
			overlay.classList.add('flex');

			let index = 0;
			step.textContent = securingSteps[0];
			clearInterval(securingStepTimer);
			securingStepTimer = setInterval(function(){
				index = (index + 1) % securingSteps.length;
				step.style.opacity = '0';
				setTimeout(function(){
					step.textContent = securingSteps[index];
					step.style.opacity = '1';
				}, 180);
			}, 1600);

			let pct = 8;
			fill.style.width = pct + '%';
			clearInterval(securingTrickleTimer);
			securingTrickleTimer = setInterval(function(){
				pct += Math.max(0.4, (92 - pct) * 0.07);
				if(pct > 92){ pct = 92; }
				fill.style.width = pct + '%';
			}, 320);
		}

		function wpuHideSecuring(success){
			const { overlay, step, fill } = wpuSecuringEls();
			if(!overlay){ return; }

			clearInterval(securingStepTimer);
			clearInterval(securingTrickleTimer);

			if(success){
				fill.style.width = '100%';
				step.style.opacity = '1';
				step.textContent = wpUmbrellaApiKeyI18n.securing_done;
				return;
			}

			overlay.classList.remove('flex');
			overlay.classList.add('hidden');
		}

		function handleCheckApiKey(){

			async function handleApiKeyChange(e) {
				const apiKey = e.target.value;

				const form = document.getElementById('wp_umbrella_valid_api_key');
				const containerError = form.querySelector('.js-error-message-container');
				const errorMessage = form.querySelector('.js-error-message');
				const containerSuccess = form.querySelector('.js-success-message-container');
				const containerWorkspaces = form.querySelector('.js-container-workspaces');

				if(containerError.classList.contains('flex')){
					containerError.classList.remove('flex');
					containerError.classList.add('hidden');
				}

				if(containerSuccess.classList.contains('flex')){
					containerSuccess.classList.remove('flex');
					containerSuccess.classList.add('hidden');
				}

				if(containerWorkspaces.classList.contains('block')){
					containerWorkspaces.classList.remove('block');
					containerWorkspaces.classList.add('hidden');
				}

				if(apiKey.length < 5){
					return;
				}

				const loader = form.querySelector('.js-loader-check');

				loader.classList.remove('hidden');
				loader.classList.add('flex');

				const body = new FormData(form);
				body.delete('_wpnonce')
				body.delete('action')

				body.append('_wpnonce', form.querySelector('#nonce_wp_umbrella_check_api_key').value);
				body.append('action', 'wp_umbrella_check_api_key')
				body.append('api_key', apiKey)

				const { json, failure } = await wpUmbrellaPostAjax(form.getAttribute('action'), body);

				loader.classList.remove('flex');
				loader.classList.add('hidden');

				if(failure){
					errorMessage.innerHTML = wpUmbrellaAjaxFailureHtml(failure);
					containerError.classList.remove('hidden');
					containerError.classList.add('flex');

					return;
				}

				const { code, ...rest } = json?.data || {};

				const { project_id, workspaces = [] } = rest

				if(code !== "success" && code !== "project_not_exist"){
					switch(code){
						case "not_authorized":
							errorMessage.textContent = wpUmbrellaApiKeyI18n.not_authorized;
							break;
						case "network_admin_required":
							errorMessage.textContent = wpUmbrellaApiKeyI18n.network_admin_required;
							break;
						case "api_key_invalid":
							errorMessage.textContent = wpUmbrellaApiKeyI18n.api_key_invalid;
							break;
						case "limit_excedeed":
							errorMessage.textContent = wpUmbrellaApiKeyI18n.limit_exceeded_free_plan
							break;
						default:
							errorMessage.innerHTML = wpUmbrellaApiKeyI18n.api_key_invalid_support
							break;
					}

					containerError.classList.remove('hidden');
					containerError.classList.add('flex');

					return;
				}

				containerSuccess.classList.remove('hidden');
				containerSuccess.classList.add('flex');

				if(workspaces.length > 1){
					containerWorkspaces.classList.remove('hidden');
					containerWorkspaces.classList.add('block');

					// add options to select
					const select = containerWorkspaces.querySelector('select');
					select.innerHTML = '';

					const option = document.createElement('option');
					option.value = -1;
					option.textContent = wpUmbrellaApiKeyI18n.select_a_workspace;
					select.appendChild(option);

					workspaces.forEach(workspace => {
						const option = document.createElement('option');
						option.value = workspace.api_key;
						option.textContent = workspace.name;
						select.appendChild(option);
					})
				}
 				// Auto select workspace if only one
				else if(workspaces.length === 1){
					workspaceSelected = workspaces[0].api_key
					form.querySelector('button[type="submit"]').removeAttribute('disabled');
				}
				else {
					// You don't have any workspace
					workspaceSelected = apiKey
					form.querySelector('button[type="submit"]').removeAttribute('disabled');
				}
			}

			const apiKeyInput = document.querySelector('input#apiKey');
			const debouncedHandleApiKeyChange = debounce(handleApiKeyChange, 500);

			apiKeyInput.addEventListener('keyup', debouncedHandleApiKeyChange);
		}

		handleCheckApiKey();

		document.querySelector('#wp_umbrella_valid_api_key #workspaces').addEventListener('change', function(e){
			workspaceSelected = e.target.value

			const form = document.getElementById('wp_umbrella_valid_api_key');

			form.querySelector('button[type="submit"]').removeAttribute('disabled');
		})


		function handleValidateApiKey(){
			const form = document.getElementById('wp_umbrella_valid_api_key');

			form.addEventListener('submit', async function(e){
				e.preventDefault();

				const messageSelectWorkspace = form.querySelector('.js-error-message-workspace')

				if(messageSelectWorkspace.classList.contains('block')){
					messageSelectWorkspace.classList.remove('block');
					messageSelectWorkspace.classList.add('hidden');
				}

				form.querySelector('button[type="submit"]').setAttribute('disabled', 'disabled');

				const body = new FormData(form);
				body.delete('_wpnonce')
				body.delete('action')
				body.append('_wpnonce', form.querySelector('#nonce_wp_umbrella_valid_api_key').value);
				body.append('action', 'wp_umbrella_valid_api_key')

				if(workspaceSelected){
					console.info("[INFO] Workspace is selected")
					body.delete('api_key')
					body.append('api_key', workspaceSelected)
				}
				else if(body.get("workspaces") === "-1"){
					messageSelectWorkspace.classList.remove('hidden');
					messageSelectWorkspace.classList.add('block');

					return;
				}

				const loader = form.querySelector('.js-loader')
				loader.classList.remove('hidden')
				loader.classList.add('flex');

				wpuShowSecuring();

				const { json, failure } = await wpUmbrellaPostAjax(form.getAttribute('action'), body);

				const data = failure?.type === 'php_fatal' ? { code: 'wordpress_critical_error', critical_error: failure.critical_error } : (json?.data || {});
				const { code, critical_error, ...rest } = data;

				loader.classList.remove('flex')
				loader.classList.add('hidden');
				form.querySelector('button[type="submit"]').removeAttribute('disabled');

				wpuHideSecuring(code === "success");

				if(failure && failure.type !== 'php_fatal'){
					Swal.fire({
						html: wpUmbrellaAjaxFailureHtml(failure),
						icon: 'error',
						confirmButtonText: wpUmbrellaApiKeyI18n.close,
					})

					return;
				}


				if(code !== "success"){

					switch(code){
						case "not_authorized":
							Swal.fire({
								text: wpUmbrellaApiKeyI18n.not_authorized,
								icon: 'error',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "network_admin_required":
							Swal.fire({
								text: wpUmbrellaApiKeyI18n.network_admin_required,
								icon: 'error',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "api_key_invalid":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.api_key_invalid_title,
								text: wpUmbrellaApiKeyI18n.api_key_invalid,
								icon: 'error',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "limit_excedeed":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.limit_exceeded_title,
								text: wpUmbrellaApiKeyI18n.limit_exceeded_trial,
								icon: 'error',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "api_unreachable":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.api_unreachable_title,
								text: wpUmbrellaApiKeyI18n.api_unreachable_text,
								icon: 'warning',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "unverified_channel":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.unverified_channel_title,
								text: wpUmbrellaApiKeyI18n.unverified_channel_text,
								icon: 'warning',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "http_auth_required":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.http_auth_required_title,
								text: wpUmbrellaApiKeyI18n.http_auth_required_text,
								icon: 'warning',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "http_auth_invalid":
							Swal.fire({
								titleText: wpUmbrellaApiKeyI18n.http_auth_invalid_title,
								text: wpUmbrellaApiKeyI18n.http_auth_invalid_text,
								icon: 'error',
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
						case "wordpress_critical_error":
							const escapeHtml = wpUmbrellaEscapeHtml;
							const phpMessage = escapeHtml(critical_error?.message || wpUmbrellaApiKeyI18n.unknown_php_error);
							const phpFile = escapeHtml(critical_error?.file || '');
							const phpLine = critical_error?.line ? `:${escapeHtml(critical_error.line)}` : '';
							const fileBlock = phpFile ? `<p style="font-size:14px; text-align:left; margin-bottom:10px; font-family:monospace; background:#f3f4f6; padding:8px; border-radius:4px; word-break:break-all;">${phpFile}${phpLine}</p>` : '';
							Swal.fire({
								icon: 'error',
								titleText: '⚠ ' + wpUmbrellaApiKeyI18n.critical_error_title,
								html: `
									<p style="font-size:16px; text-align:left; margin-bottom:10px;">${wpUmbrellaApiKeyI18n.critical_error_intro}</p>

									<p style="font-size:16px; text-align:left; margin-bottom:6px;"><strong>${wpUmbrellaApiKeyI18n.critical_error_reported}</strong></p>
									<p style="font-size:14px; text-align:left; margin-bottom:10px; font-family:monospace; background:#fee2e2; color:#991b1b; padding:8px; border-radius:4px; word-break:break-word;">${phpMessage}</p>

									${fileBlock}

									<p style="font-size:16px; text-align:left; margin-bottom:10px;"><strong>${wpUmbrellaApiKeyI18n.critical_error_how_to}</strong></p>
									<ol style="text-align:left; font-size:16px; margin-bottom:10px; padding-left:20px;">
										<li style="margin-bottom:6px;">${wpUmbrellaApiKeyI18n.critical_error_step_panel}</li>
										<li style="margin-bottom:6px;">${wpUmbrellaApiKeyI18n.critical_error_step_rename}</li>
										<li style="margin-bottom:6px;">${wpUmbrellaApiKeyI18n.critical_error_step_reload}</li>
									</ol>

									<p style="font-size:14px; text-align:left; margin-bottom:10px; color:#6b7280;">${wpUmbrellaApiKeyI18n.critical_error_footer}</p>
								`,
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
								width: 640,
							})
							break;
						case "failed_authorize_wordpress":
						case "rest_forbidden":
						default:
							Swal.fire({
								icon:"",
								titleText: "⚠ " + wpUmbrellaApiKeyI18n.connection_issue_title,
								html: `
								<p style="font-size:16px; text-align:left; margin-bottom:10px;">${wpUmbrellaApiKeyI18n.connection_issue_intro}</p>

								<p style="font-size:16px; text-align:left; margin-bottom:10px;">
									<strong>${wpUmbrellaApiKeyI18n.connection_issue_resolve}</strong>
								</p>

								<p style="font-size:16px; text-align:left; margin-bottom:6px;">
									${wpUmbrellaApiKeyI18n.connection_issue_whitelist}
								</p>
								<p style="font-size:16px; text-align:left; margin-bottom:10px; margin-top:10px;">
									${wpUmbrellaApiKeyI18n.ipv4_label} (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this)">${wpUmbrellaApiKeyI18n.copy_all_ips_v4}</a>)
								</p>
								<ul style="text-align:left; list-style-type:disc; font-size:16px; margin-bottom:2px;">
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.83.142.5 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.83.142.5')">${wpUmbrellaApiKeyI18n.copy_this_ip}</a>)</li>
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.83.175.107 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.83.175.107')">${wpUmbrellaApiKeyI18n.copy_this_ip}</a>)</li>
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.129.45.77 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.129.45.77')">${wpUmbrellaApiKeyI18n.copy_this_ip}</a>)</l>
								</ul>
								<p style="font-size:16px; text-align:left; margin-bottom:8px; margin-top:10px;">
									${wpUmbrellaApiKeyI18n.ipv6_label}
								</p>
								<ul style="text-align:left; list-style-type:disc; font-size:16px; margin-bottom:2px; margin-top:0px;">
									<li style="list-style-type:disc; padding-left:8px;">2001:BC8:2B7F:801::292/64 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '2001:BC8:2B7F:801::292/64')">${wpUmbrellaApiKeyI18n.copy_this_ip}</a>)</li>
								</ul>

								<p style="font-size:16px; text-align:left; margin-bottom:18px; margin-top:18px;">${wpUmbrellaApiKeyI18n.connection_issue_rest_api}</p>

								<p style="font-size:16px; text-align:left; margin-bottom:18px;">${wpUmbrellaApiKeyI18n.connection_issue_guide}</p>

								<p style="font-size:16px; text-align:left; margin-bottom:10px;">${wpUmbrellaApiKeyI18n.connection_issue_support} <a href="mailto:support@wp-umbrella.com" target="_blank" style="color:#2563eb;">support@wp-umbrella.com</a></p>
								`,
								confirmButtonText: wpUmbrellaApiKeyI18n.close,
							})
							break;
					}

					return;
				}

				Swal.fire({
					titleText: wpUmbrellaApiKeyI18n.connected_title,
					text: wpUmbrellaApiKeyI18n.connected_text,
					icon: "success",
					confirmButtonText: `<div style="display:flex; align-items:center;">${wpUmbrellaApiKeyI18n.go_to_dashboard} <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px; height:14px; margin-left:6px;">
		<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
		</svg></div>
		`,
					showCancelButton: false,
				}).then(function (result){
					if(result.isConfirmed){

						window.open("<?php echo WP_UMBRELLA_APP_URL; ?>", "_blank")
						return
					}

					window.location.reload();
				})

			});
		}

		handleValidateApiKey();


	})
</script>
