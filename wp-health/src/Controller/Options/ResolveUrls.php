<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class ResolveUrls extends AbstractController
{
    const MAX_URLS = 500;

    public function executePost($params)
    {
        $urls = isset($params['urls']) ? (array) $params['urls'] : [];
        $urls = array_slice($urls, 0, self::MAX_URLS);

        if (empty($urls)) {
            return $this->returnResponse([
                'success' => true,
                'results' => [],
            ]);
        }

        $results = [];

        foreach ($urls as $url) {
            $url = esc_url_raw($url);
            $postId = url_to_postid($url);

            if ($postId === 0) {
                $results[] = [
                    'url' => $url,
                    'postId' => null,
                    'editUrl' => null,
                ];
                continue;
            }

            $results[] = [
                'url' => $url,
                'postId' => $postId,
                'editUrl' => admin_url('post.php?post=' . $postId . '&action=edit'),
            ];
        }

        return $this->returnResponse([
            'success' => true,
            'results' => $results,
        ]);
    }
}
