<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures comment, taxonomy term and navigation menu events.
 *
 * Event keys emitted:
 * - comment.created    (LOW)
 * - comment.approved   (LOW)
 * - comment.unapproved (LOW)
 * - comment.spammed    (MEDIUM)
 * - comment.trashed    (LOW)
 * - comment.deleted    (LOW)
 * - term.created       (LOW)
 * - term.updated       (LOW)
 * - term.deleted       (LOW)
 * - menu.created       (LOW)
 * - menu.updated       (LOW)
 * - menu.deleted       (MEDIUM)
 *
 * Implementation notes:
 * - Comment status changes are dispatched through transition_comment_status,
 *   which covers approve / unapprove / spam / trash / restore in one place.
 * - wp_insert_comment fires for *every* new comment, including spam created
 *   directly with status=spam. We emit comment.created for the initial row
 *   and rely on transition_comment_status for subsequent moderation events.
 * - Widget activity is intentionally out of scope here. Widgets in WP are
 *   stored in the sidebars_widgets option and are best surfaced via a
 *   future option whitelist entry rather than a separate sensor.
 */
class CommentTermMenuSensor extends AbstractSensor
{
    /**
     * Snapshot a term before deletion so onTermDeleted can emit a payload
     * with the original name and taxonomy.
     *
     * @var array<int, array{name: string, taxonomy: string, slug: string}>
     */
    protected $termDeletionCache = [];

    /**
     * @return void
     */
    public function register()
    {
        add_action('wp_insert_comment', [$this, 'onCommentInserted'], 10, 2);
        add_action('transition_comment_status', [$this, 'onCommentTransition'], 10, 3);
        add_action('deleted_comment', [$this, 'onCommentDeleted'], 10, 2);

        add_action('created_term', [$this, 'onTermCreated'], 10, 3);
        add_action('edited_term', [$this, 'onTermEdited'], 10, 3);
        add_action('pre_delete_term', [$this, 'onBeforeDeleteTerm'], 10, 2);
        add_action('delete_term', [$this, 'onTermDeleted'], 10, 5);

        add_action('wp_create_nav_menu', [$this, 'onMenuCreated'], 10, 2);
        add_action('wp_update_nav_menu', [$this, 'onMenuUpdated'], 10, 2);
        add_action('wp_delete_nav_menu', [$this, 'onMenuDeleted'], 10, 1);
    }

    public function onCommentInserted($commentId, $comment = null)
    {
        $info = $this->commentSnapshot($comment);

        $this->recordEvent('comment.created', 'LOW', array_merge(
            ['commentId' => (int) $commentId],
            $info
        ));
    }

    public function onCommentTransition($newStatus, $oldStatus, $comment)
    {
        if (!is_object($comment)) {
            return;
        }

        if ($newStatus === $oldStatus) {
            return;
        }

        $eventKey = $this->resolveCommentEvent($newStatus, $oldStatus);

        if ($eventKey === null) {
            return;
        }

        $severity = $eventKey === 'comment.spammed' ? 'MEDIUM' : 'LOW';

        $this->recordEvent($eventKey, $severity, array_merge(
            [
                'commentId' => isset($comment->comment_ID) ? (int) $comment->comment_ID : null,
                'newStatus' => (string) $newStatus,
                'previousStatus' => (string) $oldStatus,
            ],
            $this->commentSnapshot($comment)
        ));
    }

    public function onCommentDeleted($commentId, $comment = null)
    {
        $this->recordEvent('comment.deleted', 'LOW', array_merge(
            ['commentId' => (int) $commentId],
            $this->commentSnapshot($comment)
        ));
    }

    public function onTermCreated($termId, $taxonomyTermId, $taxonomy)
    {
        $info = $this->termInfo($termId, $taxonomy);

        $this->recordEvent('term.created', 'LOW', [
            'termId' => (int) $termId,
            'taxonomy' => is_string($taxonomy) ? $taxonomy : null,
            'termName' => $info !== null ? $info['name'] : null,
            'termSlug' => $info !== null ? $info['slug'] : null,
        ]);
    }

    public function onTermEdited($termId, $taxonomyTermId, $taxonomy)
    {
        $info = $this->termInfo($termId, $taxonomy);

        $this->recordEvent('term.updated', 'LOW', [
            'termId' => (int) $termId,
            'taxonomy' => is_string($taxonomy) ? $taxonomy : null,
            'termName' => $info !== null ? $info['name'] : null,
            'termSlug' => $info !== null ? $info['slug'] : null,
        ]);
    }

    public function onBeforeDeleteTerm($termId, $taxonomy)
    {
        $info = $this->termInfo($termId, $taxonomy);

        if ($info !== null) {
            $this->termDeletionCache[(int) $termId] = $info;
        }
    }

    public function onTermDeleted($termId, $taxonomyTermId, $taxonomy, $deletedTerm = null, $objectIds = null)
    {
        $termId = (int) $termId;
        $cached = isset($this->termDeletionCache[$termId]) ? $this->termDeletionCache[$termId] : null;
        unset($this->termDeletionCache[$termId]);

        $name = $cached !== null ? $cached['name'] : null;
        $slug = $cached !== null ? $cached['slug'] : null;

        if ($name === null && is_object($deletedTerm)) {
            $name = isset($deletedTerm->name) ? (string) $deletedTerm->name : null;
            $slug = isset($deletedTerm->slug) ? (string) $deletedTerm->slug : null;
        }

        $this->recordEvent('term.deleted', 'LOW', [
            'termId' => $termId,
            'taxonomy' => is_string($taxonomy) ? $taxonomy : null,
            'termName' => $name,
            'termSlug' => $slug,
        ]);
    }

    public function onMenuCreated($menuId, $menuData = [])
    {
        $this->recordEvent('menu.created', 'LOW', [
            'menuId' => (int) $menuId,
            'menuName' => $this->extractMenuName($menuData),
        ]);
    }

    public function onMenuUpdated($menuId, $menuData = null)
    {
        $this->recordEvent('menu.updated', 'LOW', [
            'menuId' => (int) $menuId,
            'menuName' => $this->extractMenuName($menuData),
        ]);
    }

    public function onMenuDeleted($menuId)
    {
        $this->recordEvent('menu.deleted', 'MEDIUM', [
            'menuId' => (int) $menuId,
        ]);
    }

    /**
     * @param string $newStatus
     * @param string $oldStatus
     *
     * @return string|null
     */
    protected function resolveCommentEvent($newStatus, $oldStatus)
    {
        switch ($newStatus) {
            case 'approved':
            case '1':
                return 'comment.approved';
            case 'unapproved':
            case '0':
            case 'hold':
                return 'comment.unapproved';
            case 'spam':
                return 'comment.spammed';
            case 'trash':
                return 'comment.trashed';
            case 'delete':
                return 'comment.deleted';
        }

        return null;
    }

    /**
     * @param mixed $comment
     *
     * @return array
     */
    protected function commentSnapshot($comment)
    {
        if (!is_object($comment)) {
            return [
                'postId' => null,
                'commentAuthor' => null,
                'commentAuthorEmail' => null,
                'commentStatus' => null,
                'commentType' => null,
            ];
        }

        return [
            'postId' => isset($comment->comment_post_ID) ? (int) $comment->comment_post_ID : null,
            'commentAuthor' => isset($comment->comment_author) ? (string) $comment->comment_author : null,
            'commentAuthorEmail' => isset($comment->comment_author_email) ? (string) $comment->comment_author_email : null,
            'commentStatus' => isset($comment->comment_approved) ? (string) $comment->comment_approved : null,
            'commentType' => isset($comment->comment_type) ? (string) $comment->comment_type : null,
        ];
    }

    /**
     * @param int    $termId
     * @param string $taxonomy
     *
     * @return array{name: string, taxonomy: string, slug: string}|null
     */
    protected function termInfo($termId, $taxonomy)
    {
        if (!function_exists('get_term')) {
            return null;
        }

        $term = get_term((int) $termId, is_string($taxonomy) ? $taxonomy : '');

        if (!is_object($term)) {
            return null;
        }

        return [
            'name' => isset($term->name) ? (string) $term->name : '',
            'taxonomy' => isset($term->taxonomy) ? (string) $term->taxonomy : (string) $taxonomy,
            'slug' => isset($term->slug) ? (string) $term->slug : '',
        ];
    }

    /**
     * @param mixed $menuData
     *
     * @return string|null
     */
    protected function extractMenuName($menuData)
    {
        if (is_array($menuData) && isset($menuData['menu-name'])) {
            return (string) $menuData['menu-name'];
        }

        return null;
    }
}
