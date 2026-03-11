<?php

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

add_action('action_wp_umbrella_send_links', 'wp_umbrella_send_links', 10);

function wp_umbrella_send_links()
{
    $repository = wp_umbrella_get_service('LinkRepository');

    $links = $repository->getUnsentLinks(100);

    if (empty($links)) {
        return;
    }

    $payload = [];
    $ids = [];

    foreach ($links as $link) {
        $ids[] = $link->id;

        $item = [
            'pageUrl' => $link->page_url,
            'href' => $link->href,
            'isInternal' => (bool) $link->is_internal,
        ];

        $anchorText = $link->anchor_text;
        if (!empty($anchorText)) {
            $item['anchorText'] = mb_substr($anchorText, 0, 100);
        }

        if (!empty($link->position)) {
            $item['position'] = $link->position;
        }

        if (!empty($link->rel)) {
            $item['rel'] = $link->rel;
        }

        $payload[] = $item;
    }

    $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/links', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('Bearer %s', wp_umbrella_get_api_key()),
            'X-Project' => site_url(),
            'X-Project-Id' => wp_umbrella_get_project_id(),
            'X-Secret-Token' => wp_umbrella_get_secret_token(),
        ],
        'body' => json_encode(['links' => $payload]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $statusCode = wp_remote_retrieve_response_code($response);

    if ($statusCode >= 200 && $statusCode < 300) {
        $repository->markAsSent($ids);
        $repository->deleteOldSent(7);
    }
}
