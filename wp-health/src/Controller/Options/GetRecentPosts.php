<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class GetRecentPosts extends AbstractController
{
    const PER_PAGE = 100;

    public function executeGet($params)
    {
        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
        $perPage = isset($params['per_page']) ? min(100, max(1, (int) $params['per_page'])) : self::PER_PAGE;
        $postTypes = isset($params['post_type']) ? explode(',', sanitize_text_field($params['post_type'])) : ['post', 'page'];

        $query = new \WP_Query([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => false,
        ]);

        $results = [];

        foreach ($query->posts as $post) {
            $results[] = [
                'postId' => $post->ID,
                'url' => get_permalink($post->ID),
                'editUrl' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                'postType' => $post->post_type,
                'updatedAt' => $post->post_modified_gmt,
            ];
        }

        return $this->returnResponse([
            'success' => true,
            'results' => $results,
            'total' => (int) $query->found_posts,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) $query->max_num_pages,
        ]);
    }

    public function executePost($params)
    {
        return $this->executeGet($params);
    }
}
