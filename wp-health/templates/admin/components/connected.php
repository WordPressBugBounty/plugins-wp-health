<?php

$data = wp_umbrella_get_service('GetSettingsData')->getData();

$allowTracking = $data['allow_tracking'];
$allowOneClick = $data['allow_one_click_access'];
?>
<div class="p-8">
	<div class="flex gap-8">
		<div class="bg-white rounded-lg max-w-xl p-8 w-1/2">
			<img src="<?php echo esc_url($data['white_label']['logo']); ?>" width="256">
			<?php if(!empty($data['white_label']['data']['catchphrase'])): ?>
				<p class="text-lg my-2">
					<?php echo esc_html($data['white_label']['data']['catchphrase']); ?>
				</p>
			<?php endif; ?>
			<?php if(!empty($data['white_label']['data']['catchphrase_2'])): ?>
				<p class="my-2 text-sm">
					<?php echo esc_html($data['white_label']['data']['catchphrase_2']); ?>
				</p>
			<?php endif; ?>
			<?php if(!empty($data['white_label']['email_support'])): ?>
				<p class="my-2 text-sm">
					<?php _e('Email Support', 'wp-health') ?>:
					<a
						href="mailto:<?php echo esc_attr($data['white_label']['email_support']); ?>"
						class="underline text-blue-500"
					>
						<?php echo esc_html($data['white_label']['email_support']); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php if($data['white_label']['view_company_details']): ?>
			<div class="bg-white rounded-lg p-8">
				<p class="text-lg font-medium text-indigo-600 mb-1">
					Company details
				</p>
				<?php if(isset($data['white_label']['company_details']['line1']) && !empty($data['white_label']['company_details']['line1'])): ?>
					<p class="text-sm">
						<?php echo esc_html($data['white_label']['company_details']['line1']); ?>
					</p>
				<?php endif; ?>
				<?php if(isset($data['white_label']['company_details']['line2']) && !empty($data['white_label']['company_details']['line2'])): ?>
					<p class="text-sm">
						<?php echo esc_html($data['white_label']['company_details']['line2']); ?>
					</p>
				<?php endif; ?>
				<?php if(isset($data['white_label']['company_details']['state']) && !empty($data['white_label']['company_details']['state'])): ?>
					<p class="text-sm">
						<?php echo esc_html($data['white_label']['company_details']['state']); ?>
					</p>
				<?php endif; ?>
				<p class="text-sm">
					<?php if(isset($data['white_label']['company_details']['postal_code']) && !empty($data['white_label']['company_details']['postal_code'])): ?>
						<?php echo esc_html($data['white_label']['company_details']['postal_code']); ?>
					<?php endif; ?>
					<?php if(isset($data['white_label']['company_details']['city']) && !empty($data['white_label']['company_details']['city'])): ?>
						<?php echo esc_html($data['white_label']['company_details']['city']); ?>
					<?php endif; ?>
				</p>
				<?php if(isset($data['white_label']['company_details']['country']) && !empty($data['white_label']['company_details']['country'])): ?>
					<p class="text-sm">
						<?php echo esc_html($data['white_label']['company_details']['country']); ?>
					</p>
				<?php endif; ?>
				<?php if(isset($data['white_label']['company_details']['phone']) && !empty($data['white_label']['company_details']['phone'])): ?>
					<p class="text-sm">
						<?php echo esc_html($data['white_label']['company_details']['phone']); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="bg-white rounded-lg max-w-xl p-8 mt-8">
		<?php include_once __DIR__ . '/forms/validate-api-key.php'; ?>
		<div class="flex items-center gap-4 mt-8">
			<div class="w-3/4">
				<p>Enable Error Monitoring</p>
				<a class="underline cursor-pointer inline-flex items-center gap-2" href="https://support.wp-umbrella.com/article/101-what-information-do-we-collect" target="_blank">
					What information will we collect?

					<svg class="w-3 h-3 opacity-50" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 7H6C5.46957 7 4.96086 7.21071 4.58579 7.58579C4.21071 7.96086 4 8.46957 4 9V18C4 18.5304 4.21071 19.0391 4.58579 19.4142C4.96086 19.7893 5.46957 20 6 20H15C15.5304 20 16.0391 19.7893 16.4142 19.4142C16.7893 19.0391 17 18.5304 17 18V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 14L20 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 4H20V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
				</a>
			</div>

			<button
				class="<?php echo $allowTracking ? 'bg-indigo-600' : 'bg-gray-200'; ?> relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 js-toggle-error-monitoring">
				<span
				class="<?php echo $allowTracking ? 'translate-x-5' : 'translate-x-0'; ?> pointer-events-none relative inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 js-first-span">
					<span
					class="<?php echo $allowTracking ? 'opacity-0' : 'opacity-100'; ?> ease-out duration-100 absolute inset-0 h-full w-full flex items-center justify-center transition-opacity js-opacity-0"
					aria-hidden="true">
						<svg class="h-3 w-3 text-gray-400" fill="none"
						viewBox="0 0 12 12">
						<path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
						</svg>
					</span>
					<span
					class="<?php echo $allowTracking ? 'opacity-100' : 'opacity-0'; ?> ease-in duration-200 absolute inset-0 h-full w-full flex items-center justify-center transition-opacity js-opacity-100"
					aria-hidden="true">
						<svg class="h-3 w-3 text-indigo-600" fill="currentColor"
							viewBox="0 0 12 12">
							<path
								d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z">
							</path>
						</svg>
					</span>
				</span>
			</button>
		</div>
		<div class="flex items-center gap-4 mt-4">
			<div class="w-3/4">
				<p>Enable 1-Click Access</p>
				<a class="underline cursor-pointer inline-flex items-center gap-2" href="https://support.wp-umbrella.com/article/102-understanding-wp-umbrella-1-click-access" target="_blank">
					<span>What is the 1-Click Admin Access?</span>
					<svg class="w-3 h-3 opacity-50" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 7H6C5.46957 7 4.96086 7.21071 4.58579 7.58579C4.21071 7.96086 4 8.46957 4 9V18C4 18.5304 4.21071 19.0391 4.58579 19.4142C4.96086 19.7893 5.46957 20 6 20H15C15.5304 20 16.0391 19.7893 16.4142 19.4142C16.7893 19.0391 17 18.5304 17 18V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 14L20 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 4H20V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
				</a>
			</div>
			<button
				class="<?php echo $allowOneClick ? 'bg-indigo-600' : 'bg-gray-200'; ?> relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 js-toggle-one-click">
				<span
				class="<?php echo $allowOneClick ? 'translate-x-5' : 'translate-x-0'; ?> pointer-events-none relative inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 js-first-span">
					<span
					class="<?php echo $allowOneClick ? 'opacity-0' : 'opacity-100'; ?> ease-out duration-100 absolute inset-0 h-full w-full flex items-center justify-center transition-opacity js-opacity-0"
					aria-hidden="true">
						<svg class="h-3 w-3 text-gray-400" fill="none"
						viewBox="0 0 12 12">
						<path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
						</svg>
					</span>
					<span
					class="<?php echo $allowOneClick ? 'opacity-100' : 'opacity-0'; ?> ease-in duration-200 absolute inset-0 h-full w-full flex items-center justify-center transition-opacity js-opacity-100"
					aria-hidden="true">
						<svg class="h-3 w-3 text-indigo-600" fill="currentColor"
							viewBox="0 0 12 12">
							<path
								d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z">
							</path>
						</svg>
					</span>
				</span>
			</button>
	</div>
</div>

<?php wp_nonce_field('wp_health_allow_tracking', 'wp_health_allow_tracking'); ?>
<?php wp_nonce_field('wp_health_disallow_tracking', 'wp_health_disallow_tracking'); ?>
<input type="hidden" name="hidden_wp_health_allow_tracking" value="<?php echo $allowTracking ? 'true' : 'false'; ?>"/>

<?php wp_nonce_field('wp_umbrella_allow_one_click_access', 'wp_umbrella_allow_one_click_access'); ?>
<?php wp_nonce_field('wp_umbrella_disallow_one_click_access', 'wp_umbrella_disallow_one_click_access'); ?>
<input type="hidden" name="hidden_wp_umbrella_allow_one_click_access" value="<?php echo $allowOneClick ? 'true' : 'false'; ?>"/>


<script>
document.addEventListener('DOMContentLoaded', function(){

	function toggleErrorMonitoring(){
		const toggle = document.querySelector('.js-toggle-error-monitoring')

		toggle.addEventListener('click', async function(e){
			e.preventDefault()

			toggle.classList.toggle('bg-indigo-600')
			toggle.classList.toggle('bg-gray-200')
			const firstSpan = toggle.querySelector('.js-first-span')
			firstSpan.classList.toggle('translate-x-5')
			firstSpan.classList.toggle('translate-x-0')

			const op0 = toggle.querySelector('.js-opacity-0')
			op0.classList.toggle('opacity-0')
			op0.classList.toggle('opacity-100')

			const op100 = toggle.querySelector('.js-opacity-100')
			op100.classList.toggle('opacity-0')
			op100.classList.toggle('opacity-100')

			const body = new FormData()

			const valueTracking = document.querySelector('input[name="hidden_wp_health_allow_tracking"]').value

			if(valueTracking === 'true'){
				body.append('action','wp_health_disallow_tracking')
				body.append('_wpnonce', document.querySelector('#wp_health_disallow_tracking').value)
			}
			else{
				body.append('action', 'wp_health_allow_tracking')
				body.append('_wpnonce', document.querySelector('#wp_health_allow_tracking').value)
			}


			const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
				method: 'POST',
				body: body
			})

			document.querySelector('input[name="hidden_wp_health_allow_tracking"]').value = valueTracking === 'true' ? 'false' : 'true'

			Swal.fire({
				title: "Great!",
				html: "Your settings have been updated.",
				icon: "success",
				confirmButtonText: "Close",
			})

		})
	}

	toggleErrorMonitoring()

	function toggleOneClick(){
		const toggle = document.querySelector('.js-toggle-one-click')

		toggle.addEventListener('click', async function(e){
			e.preventDefault()

			toggle.classList.toggle('bg-indigo-600')
			toggle.classList.toggle('bg-gray-200')
			const firstSpan = toggle.querySelector('.js-first-span')
			firstSpan.classList.toggle('translate-x-5')
			firstSpan.classList.toggle('translate-x-0')

			const op0 = toggle.querySelector('.js-opacity-0')
			op0.classList.toggle('opacity-0')
			op0.classList.toggle('opacity-100')

			const op100 = toggle.querySelector('.js-opacity-100')
			op100.classList.toggle('opacity-0')
			op100.classList.toggle('opacity-100')

			const body = new FormData()

			const valueTracking = document.querySelector('input[name="hidden_wp_umbrella_allow_one_click_access"]').value

			if(valueTracking === 'true'){
				body.append('action','wp_umbrella_disallow_one_click_access')
				body.append('_wpnonce', document.querySelector('#wp_umbrella_disallow_one_click_access').value)
			}
			else{
				body.append('action', 'wp_umbrella_allow_one_click_access')
				body.append('_wpnonce', document.querySelector('#wp_umbrella_allow_one_click_access').value)
			}


			const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
				method: 'POST',
				body: body
			})

			document.querySelector('input[name="hidden_wp_umbrella_allow_one_click_access"]').value = valueTracking === 'true' ? 'false' : 'true'

			Swal.fire({
				title: "Great!",
				html: "Your settings have been updated.",
				icon: "success",
				confirmButtonText: "Close",
			})
		})
	}

	toggleOneClick()
})

</script>
