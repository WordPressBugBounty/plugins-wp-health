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
        // Bind the login to the exact user id the permission callback verified
        // the signature against (UmbrellaRequest::getParam), NOT WP core's merged
        // get_params(): a query/body split of user_id must not let a signature
        // minted for X create a session for Y.
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
}
