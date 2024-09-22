<?php
namespace WPUmbrella\Services;

class SessionStore
{
    /**
     * @param WP_REST_Request $request
     * @return boolean
     */
    public function removeUmbrellaSessions()
    {
        $userId = get_current_user_id();
        $sessions = get_user_meta($userId, 'session_tokens', true);

        if (!is_array($sessions)) {
            return;
        }

        foreach ($sessions as $key => $session) {
            if (isset($session['ua']) && strpos($session['ua'], 'WPUmbrella') !== false) {
                unset($sessions[$key]);
            }
        }

        update_user_meta($userId, 'session_tokens', $sessions);
    }
}
