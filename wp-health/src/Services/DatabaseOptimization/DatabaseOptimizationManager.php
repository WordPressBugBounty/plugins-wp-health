<?php
namespace WPUmbrella\Services\DatabaseOptimization;

use WPUmbrella\Services\DatabaseOptimization\ExpiredTransient;
use WPUmbrella\Services\DatabaseOptimization\Table;
use WPUmbrella\Services\DatabaseOptimization\SpamComments;
use WPUmbrella\Services\DatabaseOptimization\TrashedComments;
use WPUmbrella\Services\DatabaseOptimization\AutoDrafts;
use WPUmbrella\Services\DatabaseOptimization\Revisions;
use WPUmbrella\Services\DatabaseOptimization\TrashedPosts;

class DatabaseOptimizationManager
{
    protected $optimizations = [];

    public function __construct()
    {
        $this->setDefaultOptimizations();
    }

    public function setDefaultOptimizations()
    {
        $this->optimizations = [
            'expired_transient' => new ExpiredTransient(),
            'table' => new Table(),
            'spam_comments' => new SpamComments(),
            'trashed_comments' => new TrashedComments(),
            'auto_drafts' => new AutoDrafts(),
            'revisions' => new Revisions(),
            'trashed_posts' => new TrashedPosts(),
        ];
    }

    public function getOptimizations()
    {
        return apply_filters('wp_umbrella_database_optimizations', $this->optimizations);
    }

    public function optimizeByType($type)
    {
        $optimizations = $this->getOptimizations();
        if (!isset($optimizations[$type])) {
            return;
        }

        try {
            $optimization = $optimizations[$type];
            return $optimization->handle();
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getData()
    {
        $optimizations = $this->getOptimizations();
        $data = [];
        foreach ($optimizations as $type => $optimization) {
            $data[$type] = (int) $optimization->getData();
        }
        return $data;
    }

    public function getDataByType($type)
    {
        $optimizations = $this->getOptimizations();
        if (!isset($optimizations[$type])) {
            return;
        }

        return $optimizations[$type]->getData();
    }
}
