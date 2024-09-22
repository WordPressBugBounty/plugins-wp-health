<?php

$data = wp_umbrella_get_service('GetSettingsData')->getData();

?>

<div class="pl-8 pr-16 bg-white flex items-center relative w-full border-b">
	<img src="<?php echo sprintf('%s%s', WP_UMBRELLA_DIRURL, 'app/images/logo-umbrella.svg'); ?>" width="128">
	<div class="ml-auto flex items-center">
		<a class="hover:bg-gray-100 hover:text-blue-500 cursor-pointer flex items-center justify-center p-2 flex-col h-16 mr-4"
			href="https://changelog.wp-umbrella.com" target="_blank">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
				stroke="currentColor" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round"
					d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7">
				</path>
			</svg>
			Changelog
		</a>
		<a class="hover:bg-gray-100 hover:text-blue-500 cursor-pointer flex items-center justify-center p-2 flex-col h-16"
			href="https://support.wp-umbrella.com" target="_blank"><svg xmlns="http://www.w3.org/2000/svg"
				class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
					d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
				</path>
			</svg>
			Documentation
		</a>
	</div>
</div>

<div class="px-8 pt-4">
	<h1 class="text-2xl text-center">
		Helping Agencies and Freelancers with their WordPress Maintenance Business 🚀
	</h1>
	<p class="text-lg text-center px-12 text-gray-600 mt-2">
		Thousands of agencies use WP Umbrella to manage multiple WordPress sites from a single place, save considerable time and effectively demonstrate their work value to clients.
	</p>
	<div class="flex gap-16 h-full w-full justify-center mt-8">
		<div class="bg-white rounded-lg max-w-xl p-8">
			<?php include_once __DIR__ . '/forms/register.php'; ?>
		</div>
		<div class="bg-white rounded-lg max-w-xl p-8 w-full">
			<h2 class="text-xl font-semibold text-gray-900">
				You already have an account ? Add your API KEY
			</h2>
			<p class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500">
			<a href="<?php echo WP_UMBRELLA_APP_URL; ?>/login" target="_blank"
					class="underline">Log into your account in our app</a> to locate your API key.
			</p>
			<div class="mt-8">
				<?php include_once __DIR__ . '/forms/validate-api-key.php'; ?>
			</div>
		</div>
	</div>
</div>
