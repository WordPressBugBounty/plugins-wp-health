<?php
namespace WPUmbrella\Actions\BrokenLinkChecker;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\BrokenLinkChecker\LinkTableManager;

class SetupTable implements ExecuteHooks, ActivationHook
{
    public function hooks()
    {
        LinkTableManager::registerTable();
    }

    public function activate()
    {
        LinkTableManager::createTable();
    }
}
