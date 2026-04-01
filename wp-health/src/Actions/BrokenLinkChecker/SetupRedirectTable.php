<?php
namespace WPUmbrella\Actions\BrokenLinkChecker;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\BrokenLinkChecker\RedirectTableManager;

class SetupRedirectTable implements ExecuteHooks, ActivationHook
{
    public function hooks()
    {
        RedirectTableManager::registerTable();
    }

    public function activate()
    {
        RedirectTableManager::createTable();
    }
}
