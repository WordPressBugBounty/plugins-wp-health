<?php
$wpUmbrellaRegisterI18n = array(
	'error_not_authorized' => __('Your session expired or you are not allowed to do this. Reload the page and try again.', 'wp-health'),
	'error_network_admin_required' => __('On a WordPress network, only a network administrator can connect a site to WP Umbrella. Ask yours to create the account for this site.', 'wp-health'),
	'error_not_available_title' => __('Registration is not possible!', 'wp-health'),
	'error_not_available_text' => __('Please check that you do not already have an account.', 'wp-health'),
	'success_title' => __('Great, Your website is connected!', 'wp-health'),
	'success_text' => __('Head to your dashboard to manage your WordPress sites more efficiently and start exploring the features that WP Umbrella has to offer. 🙂', 'wp-health'),
	'go_to_dashboard' => esc_html__('Go to the Dashboard', 'wp-health'),
	'close' => esc_html__('Close', 'wp-health'),
);
?>
<form id="wp_umbrella_register" action="<?php echo admin_url('admin-ajax.php'); ?>">
	<h2 class="text-xl font-semibold text-gray-900">
		<?php echo esc_html__("Create an account to get your API Key - It's free !", 'wp-health'); ?>
	</h2>
	<p class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500">
		<?php echo esc_html__('Start your 14-day free trial', 'wp-health'); ?>
	</p>
	<div class="space-y-4 mt-8">
		<div class="flex items-center gap-4">
			<div class="w-1/2">
				<label class="block text-sm font-medium text-gray-700 pl-3 mb-1" for="firstname">
					<?php echo esc_html__('First name', 'wp-health'); ?>
				</label>
				<input id="firstname" type="text" name="firstname" placeholder="<?php echo esc_attr__('John', 'wp-health'); ?>"
					class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
					value="">
			</div>
			<div class="w-1/2"><label class="block text-sm font-medium text-gray-700 pl-3 mb-1"
					for="lastname"><?php echo esc_html__('Last name', 'wp-health'); ?></label><input id="lastname" type="text" name="lastname" placeholder="<?php echo esc_attr__('Doe', 'wp-health'); ?>"
					class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
					value=""></div>
		</div>
		<div><label class="block text-sm font-medium text-gray-700 pl-3 mb-1"
				for="email"><?php echo esc_html__('Email', 'wp-health'); ?></label><input id="email" name="email" type="email" autocomplete="email"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				placeholder="<?php echo esc_attr__('johndoe@domain.com', 'wp-health'); ?>" value=""></div>
		<div><label class="block text-sm font-medium text-gray-700 pl-3 mb-1"
				for="password"><?php echo esc_html__('Password', 'wp-health'); ?></label><input id="password" name="password" type="password"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				placeholder="<?php echo esc_attr__('Use a password', 'wp-health'); ?>" value=""></div>
		<div class="mt-6">
			<div class="flex items-center"><input id="terms" name="terms" type="checkbox"
					class="h-4 w-4 text-white border-gray-300 rounded"
					value="terms"><label for="terms" class="ml-2 block text-sm text-gray-900">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s is a link to the WP Umbrella Terms of Service. */
							__('I agree to %s.', 'wp-health'),
							'<a href="https://wp-umbrella.com/privacy-policy" target="_blank" class="text-blue-500 underline" rel="nofollow noreferer">' . esc_html__("WP Umbrella's Terms of Service", 'wp-health') . '</a>'
						),
						array('a' => array('href' => array(), 'target' => array(), 'class' => array(), 'rel' => array()))
					);
					?>
				</label>
			</div>
		</div>
		<div>
			<button type="submit"
				class="group relative flex items-center justify-center py-2 px-16 border border-transparent text-sm font-medium rounded-full text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 gap-4">
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
				<?php echo esc_html__('Register', 'wp-health'); ?>
			</button>
		</div>
	</div>

	<input type="hidden" name="action" value="wp_umbrella_register">
	<?php wp_nonce_field('wp_umbrella_register'); ?>
</form>

<script type="text/javascript">
	var wpUmbrellaRegisterI18n = <?php echo wp_json_encode($wpUmbrellaRegisterI18n); ?>;

	document.addEventListener('DOMContentLoaded', function(){

		function handleRegisterForm(){
			const form = document.getElementById('wp_umbrella_register');

			form.addEventListener('submit', async function(e){
				e.preventDefault();

				const loader = form.querySelector('.js-loader')
				loader.classList.remove('hidden')
				loader.classList.add('flex');

				form.querySelector('button[type="submit"]').setAttribute('disabled', 'disabled');


				const response = await fetch(form.getAttribute('action'), {
					method: 'POST',
					body: new FormData(form)
				})

				const { success: accepted = true, data : { code, success = true } } = await response.json();

				loader.classList.remove('flex')
				loader.classList.add('hidden');

				form.querySelector('button[type="submit"]').removeAttribute('disabled');

				if(!accepted || !success){
					switch(code){
						case "not_authorized":
							Swal.fire({
								text: wpUmbrellaRegisterI18n.error_not_authorized,
								icon: 'error',
								confirmButtonText: wpUmbrellaRegisterI18n.close,
							})
							break;
						case "network_admin_required":
							Swal.fire({
								text: wpUmbrellaRegisterI18n.error_network_admin_required,
								icon: 'error',
								confirmButtonText: wpUmbrellaRegisterI18n.close,
							})
							break;
						case "not_available":
							Swal.fire({
								titleText: wpUmbrellaRegisterI18n.error_not_available_title,
								text: wpUmbrellaRegisterI18n.error_not_available_text,
								icon: 'error',
							})
							break;
					}
					return;
				}


				Swal.fire({
					titleText: wpUmbrellaRegisterI18n.success_title,
					text: wpUmbrellaRegisterI18n.success_text,
					icon: "success",
					confirmButtonText: `<div style="display:flex; align-items:center;">${wpUmbrellaRegisterI18n.go_to_dashboard} <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px; height:14px; margin-left:6px;">
		<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
		</svg></div>
		`,
					showCancelButton: false,
				}).then(function (result){
					if(result.isConfirmed){
						// Redirect on dashboard
						window.open("<?php echo WP_UMBRELLA_APP_URL; ?>", "_blank")
						return
					}

					window.location.reload();
				})

			});
		}

		handleRegisterForm();

	})
</script>
