<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Login extends AbstractController
{
    public function executePost($params)
    {
        $userId = \WPUmbrella\Core\UmbrellaRequest::createFromGlobals()->getParam('user_id');

		if ($userId === null || $userId === '') {
            return $this->returnResponse([
                'code' => 'missing_parameters'
            ], 401);
        }

		if(!wp_umbrella_get_service('Option')->canOneClickAccess()){
			return $this->returnResponse([
                'code' => 'not_authorized'
            ], 401);
		}

        $user = wp_umbrella_get_service('WordPressContext')->getUserData($userId);

        if (!$user) {
            return $this->returnResponse([
                'code' => 'user_not_exist'
            ], 401);
        }

        if (!wp_umbrella_get_service('WordPressContext')->canOneClickLoginAs((int) $user->ID)) {
            return $this->returnResponse([
                'code' => 'not_authorized'
            ], 401);
        }

        $this->recordTwoFactorBypass($user);

        wp_set_current_user((int) $user->ID, $user->user_login);
        wp_set_auth_cookie((int) $user->ID);

        wp_redirect(admin_url('index.php'), 302);
		// Required for POST requests
		?>

		<html>
			<head>
				<meta http-equiv="refresh" content="0;URL=<?php echo admin_url('index.php'); ?>">
			</head>
			<body>
				<?php _e('Redirection in progress...','wp-health');?>☂
				<script>
					document.addEventListener("DOMContentLoaded", function(event) {
						window.location = "<?php echo admin_url('index.php'); ?>";
					});
				</script>
			</body>
		</html>
		<?php
		exit;
    }

    protected function recordTwoFactorBypass($user)
    {
        $policy = new \WPUmbrella\Services\TwoFactor\TwoFactorPolicy();

        if (!$policy->appliesToUser($user)) {
            return;
        }

        do_action('wp_umbrella_two_factor_bypassed', $user);
    }
}
