<?php
use WPUmbrella\Actions\Admin\Option;

$data = wp_umbrella_get_service('GetSettingsData')->getData();

?>

<form id="wp_umbrella_valid_api_key" action="<?php echo admin_url('admin-ajax.php'); ?>">
	<div class="space-y-4">
		<?php if($data['has_htpasswd']): ?>
		<p class="p-2 rounded-lg bg-indigo-50 border-indigo-100 border my-4 text-sm mt-8">We have
			detected the
			<strong>.htpasswd</strong> file on your site. If this is the case, you will need to specify the
			credentials so
			that we can communicate with your site.
		</p>

		<div>
			<label for="http_auth_user" class="text-sm font-medium text-gray-700 pl-3 mb-1">HTTP Auth
				User</label><input id="http_auth_user" name="http_auth_user" type="text"
				placeholder="HTTP Auth User"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				value="">
		</div>
		<div>
			<label for="http_auth_password" class="text-sm font-medium text-gray-700 pl-3 mb-1">HTTP Auth
				Password</label><input id="http_auth_password" name="http_auth_password" type="text"
				placeholder="HTTP Auth Password"
				class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
				value="">
		</div>
		<?php endif; ?>
		<div class="space-y-4">
			<div>
				<label for="apiKey" class="inline-block text-sm font-medium text-gray-700 pl-3 mb-1">Your API Key</label>
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
					<input id="apiKey" name="apiKey" type="<?php echo !empty($data['api_key']) ? 'password' : 'text' ?>" placeholder="My API KEY"
						class="appearance-none relative block px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm w-full"
						value="<?php if(!empty($data['api_key'])) {
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
				<span><strong>Your API key is valid!</strong> Click on Save. At the time of saving, we'll need to check communication with your site for security reasons.</span>
			</div>

			<div class="js-container-workspaces hidden">
				<label for="workspaces" class="inline-block text-sm font-medium text-gray-700 pl-3 mb-1">Choose a workspace</label>
				<select id="workspaces" name="workspaces" class="block w-full rounded-full border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:max-w-xs sm:text-sm sm:leading-6">
					<option value="-1">Select a workspace</option>
				</select>
				<div class="items-center gap-2 mt-2 text-red-600 text-sm js-error-message-workspace hidden">
					Please, select a workspace
				</div>
				<p class="text-gray-600 text-sm pl-3 mt-1">
					Choose the workspace to which you wish to link your site.
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
				Save
			</button>
		</div>
	</div>
	<?php wp_nonce_field('wp_umbrella_valid_api_key', 'nonce_wp_umbrella_valid_api_key'); ?>
	<?php wp_nonce_field('wp_umbrella_check_api_key', 'nonce_wp_umbrella_check_api_key'); ?>

</form>

<script type="text/javascript">
	function copyToClipboard(element, text) {
		const value = text === undefined ?  "212.83.142.5\n212.83.175.107\n212.129.45.77" : text;

		const oldText = element.innerText

		const textareaTemp = document.createElement('textarea');
		textareaTemp.value = value;
		document.body.appendChild(textareaTemp);
		textareaTemp.select();
		document.execCommand('copy');
		document.body.removeChild(textareaTemp);
		 element.innerText = "Copied!";

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

				const response = await fetch(form.getAttribute('action'), {
					method: 'POST',
					body: body
				})

				const { data: { code,  ...rest } } = await response.json();

				loader.classList.remove('flex');
				loader.classList.add('hidden');

				const { project_id, workspaces = [] } = rest

				if(code !== "success" && code !== "project_not_exist"){
					switch(code){
						case "not_authorized":
							errorMessage.innerHTML = 'You are not authorized to perform this action.';
							break;
						case "api_key_invalid":
							errorMessage.innerHTML = 'The API key is invalid.';
							break;
						case "limit_excedeed":
							errorMessage.innerHTML = "You have reached the maximum number of sites allowed by your plan. (5 sites for the free plan)"
							break;
						default:
							errorMessage.innerHTML = "The API key seems to be invalid. Please check it and try again. If the problem persists, <a href='mailto:support@wp-umbrella.com'>please contact our support.</a>"
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
					option.innerHTML = 'Select a workspace';
					select.appendChild(option);

					workspaces.forEach(workspace => {
						const option = document.createElement('option');
						option.value = workspace.api_key;
						option.innerHTML = workspace.name;
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

				const body = new FormData();
				body.append('_wpnonce', form.querySelector('#nonce_wp_umbrella_valid_api_key').value);
				body.append('action', 'wp_umbrella_valid_api_key')

				if(workspaceSelected){
					console.info("[INFO] Workspace is selected")
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

				const response = await fetch(form.getAttribute('action'), {
					method: 'POST',
					body: body
				})

				const { data : {code, ...rest} } = await response.json();

				loader.classList.remove('flex')
				loader.classList.add('hidden');
				form.querySelector('button[type="submit"]').removeAttribute('disabled');


				if(code !== "success"){

					switch(code){
						case "not_authorized":
							Swal.fire({
								text: 'You are not authorized to perform this action.',
								icon: 'error',
								confirmButtonText: "Close",
							})
							break;
						case "api_key_invalid":
							Swal.fire({
								title: 'Bad API Key',
								text: 'The API key is invalid.',
								icon: 'error',
								confirmButtonText: "Close",
							})
							break;
						case "limit_excedeed":
							Swal.fire({
								title: 'Limit excedeed',
								text: 'You have reached the maximum number of sites allowed by your plan. (5 sites during the trial)',
								icon: 'error',
								confirmButtonText: "Close",
							})
							break;
						case "failed_authorize_wordpress":
						case "rest_forbidden":
						default:
							Swal.fire({
								icon:"",
								title: "⚠ Connection Issue: Action Required",
								html: `
								<p style="font-size:16px; text-align:left; margin-bottom:10px;">We're currently unable to connect to your site. This is often due to the site's hosting firewall or security plugin mistakenly blocking WP Umbrella.</p>

								<p style="font-size:16px; text-align:left; margin-bottom:10px;">
									<strong>To Resolve This:</strong>
								</p>

								<p style="font-size:16px; text-align:left; margin-bottom:6px;">
									<strong>1. Whitelist Our Server IPs:</strong> Please ensure the following IPs are allowed access by your hosting provider or security settings:
								</p>
								<p style="font-size:16px; text-align:left; margin-bottom:10px; margin-top:10px;">
									IPv4: (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this)">Copy all IPs v4</a>)
								</p>
								<ul style="text-align:left; list-style-type:disc; font-size:16px; margin-bottom:2px;">
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.83.142.5 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.83.142.5')">Copy this IP</a>)</li>
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.83.175.107 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.83.175.107')">Copy this IP</a>)</li>
									<li style="margin-bottom:6px; list-style-type:disc; padding-left:8px;">212.129.45.77 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '212.129.45.77')">Copy this IP</a>)</l>
								</ul>
								<p style="font-size:16px; text-align:left; margin-bottom:8px; margin-top:10px;">
									IPv6:
								</p>
								<ul style="text-align:left; list-style-type:disc; font-size:16px; margin-bottom:2px; margin-top:0px;">
									<li style="list-style-type:disc; padding-left:8px;">2001:BC8:2B7F:801::292/64 (<a style="color:#2563eb; text-decoration:underline; font-size:16px; cursor:pointer;" onclick="copyToClipboard(this, '2001:BC8:2B7F:801::292/64')">Copy this IP</a>)</li>
								</ul>

								<p style="font-size:16px; text-align:left; margin-bottom:18px; margin-top:18px;"><strong>2. Check WordPress REST API Access</strong>: Confirm that access to the WordPress REST API is not being restricted, as this is crucial for communication with WP Umbrella.</p>

								<p style="font-size:16px; text-align:left; margin-bottom:18px;">For more details, feel free to check out our guide, "<a href="https://support.wp-umbrella.com/article/16-it-seems-we-cant-communicate-with-your-wordpress-api" target="_blank" style="color:#2563eb;">We Can't Communicate with Your WordPress Site</a>".</p>

								<p style="font-size:16px; text-align:left; margin-bottom:10px;">If you need extra help, feel free to reach out to our support team at: <a href="mailto:support@wp-umbrella.com" target="_blank" style="color:#2563eb;">support@wp-umbrella.com</a></p>
								`,
								confirmButtonText: "Close",
							})
							break;
					}

					return;
				}

				Swal.fire({
					title: "Great, Your website is connected!",
					text: "Head to your dashboard to manage your WordPress sites more efficiently and start exploring the features that WP Umbrella has to offer. 🙂",
					icon: "success",
					confirmButtonText: `<div style="display:flex; align-items:center;">Go to the Dashboard <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px; height:14px; margin-left:6px;">
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
