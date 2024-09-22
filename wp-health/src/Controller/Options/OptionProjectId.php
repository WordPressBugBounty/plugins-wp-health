<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class OptionProjectId extends AbstractController
{
    public function executePost($params)
    {
        if (!isset($params['project_id'])) {
            return $this->returnResponse(['success' => false]);
        }

        $projectId = sanitize_text_field($params['project_id']);

        $options = wp_umbrella_get_service('Option')->getOptions([
            'secure' => false
        ]);

        $options['project_id'] = $projectId;

        wp_umbrella_get_service('Option')->setOptions($options);

        return $this->returnResponse(['success' => true]);
    }

    public function executePut($params)
    {
        if (!isset($params['project_id'])) {
            return $this->returnResponse(['success' => false]);
        }

        $projectId = sanitize_text_field($params['project_id']);

        $options = wp_umbrella_get_service('Option')->getOptions([
            'secure' => false
        ]);

        $options['project_id'] = $projectId;

        wp_umbrella_get_service('Option')->setOptions($options);

        return $this->returnResponse(['success' => true]);
    }
}
