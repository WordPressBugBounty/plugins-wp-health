<?php
namespace WPUmbrella\CLI;

if (!defined('ABSPATH')) {
    exit;
}

class AttachByApiKeyCommand
{
    /**
     * Attach this WordPress site to a WP Umbrella account using a user API key.
     *
     * Mirrors the admin "enter your API key" form (Actions/Admin/Ajax/ValidationApiKey)
     * — same SaaS calls, same option writes, same outcome. Does NOT use the
     * /v1/projects/pair endpoint (which is the partner/CLI pair_code flow).
     *
     * ## OPTIONS
     *
     * --api-key=<api_key>
     * : The user's WP Umbrella API key (visible in the dashboard under account settings).
     *
     * [--workspace-id=<id>]
     * : Target a specific workspace when the api_key has access to several. Skips
     *   the interactive prompt. Use --list-workspaces to discover IDs.
     *
     * [--list-workspaces]
     * : Print the workspaces accessible to this api_key (id, name) and exit
     *   without touching anything.
     *
     * [--force]
     * : Re-attach an already-paired site. Existing api_key / request_token / secret_token are cleared before attaching; restored on failure.
     *
     * ## EXAMPLES
     *
     *     wp wp-umbrella attach --api-key=abcdef0123456789...
     *     wp wp-umbrella attach --api-key=abcdef0123456789... --workspace-id=clxyz...
     *     wp wp-umbrella attach --api-key=abcdef0123456789... --list-workspaces
     *     wp wp-umbrella attach --api-key=abcdef0123456789... --force
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assocArgs)
    {
        $apiKey = isset($assocArgs['api-key']) ? $assocArgs['api-key'] : '';
        $force = !empty($assocArgs['force']);
        $workspaceIdArg = isset($assocArgs['workspace-id']) ? (string) $assocArgs['workspace-id'] : null;
        $listWorkspaces = !empty($assocArgs['list-workspaces']);

        if (!is_string($apiKey) || $apiKey === '') {
            \WP_CLI::error('--api-key is required.');
            return;
        }
        if (strlen($apiKey) < 10 || strlen($apiKey) > 512) {
            \WP_CLI::error('api_key looks malformed (expected 10-512 chars).');
            return;
        }

        $optionService = \wp_umbrella_get_service('Option');
        $ownerService = \wp_umbrella_get_service('Owner');
        $projectsService = \wp_umbrella_get_service('Projects');
        $contextService = \wp_umbrella_get_service('WordPressContext');

        if ($this->isAlreadyPaired($optionService) && !$force) {
            \WP_CLI::error('This site is already paired. Re-run with --force to clear the current state and re-attach.');
            return;
        }

        $snapshot = null;
        if ($force) {
            $snapshot = $this->snapshotState($optionService);
            $this->clearState($optionService);
            \WP_CLI::warning('--force: cleared existing api_key / request_token / secret_token. State will be restored if the attach fails.');
        }

        try {
            $ownerData = $ownerService->validateApiKeyOnApplication(['api_key' => $apiKey]);

            if (is_array($ownerData) && isset($ownerData['code'])) {
                $this->fail($optionService, $snapshot, 'api_key invalid (rejected by WP Umbrella SaaS).');
                return;
            }
            if (!is_array($ownerData) || !isset($ownerData['result'])) {
                $this->fail($optionService, $snapshot, 'Unexpected response from SaaS validateApiKey (no result field).');
                return;
            }

            $owner = $ownerData['result'];

            // Workspace resolution. The /v1/external/me response embeds every
            // workspace the api_key has access to — own + accepted invites
            // from other owners. Each workspace ships its own owner-scoped
            // api_key so picking a workspace really means "switch the
            // api_key under which the project will be created", which is
            // why we substitute `$apiKey` further down.
            $workspaces = isset($owner['workspaces']) && is_array($owner['workspaces'])
                ? $owner['workspaces']
                : [];

            if ($listWorkspaces) {
                $this->printWorkspaces($workspaces);
                if ($snapshot !== null) {
                    $this->restoreState($optionService, $snapshot);
                }
                return;
            }

            $chosenWorkspace = $this->resolveWorkspace($workspaces, $workspaceIdArg);
            if ($chosenWorkspace === false) {
                $this->fail($optionService, $snapshot, '--workspace-id did not match any workspace this api_key has access to. Try --list-workspaces.');
                return;
            }
            if (is_array($chosenWorkspace) && isset($chosenWorkspace['api_key']) && $chosenWorkspace['api_key']) {
                $apiKey = (string) $chosenWorkspace['api_key'];
                \WP_CLI::log(sprintf(
                    'Using workspace "%s" (id=%s).',
                    isset($chosenWorkspace['name']) ? $chosenWorkspace['name'] : '?',
                    isset($chosenWorkspace['id']) ? $chosenWorkspace['id'] : '?'
                ));
            }

            if (isset($owner['total_projects'], $owner['limit_projects']) && $owner['total_projects'] >= $owner['limit_projects']) {
                $this->fail($optionService, $snapshot, 'Project quota exceeded for this account.');
                return;
            }

            $existingProjectId = isset($owner['project']['id']) ? $owner['project']['id'] : null;

            // Generate the per-site secret_token. Plain stays in memory (sent
            // to the SaaS once); the hashed form is what gets persisted in
            // wp_options and what inbound Bearer checks compare against.
            $secretToken = \wp_umbrella_generate_random_string(128);
            if (!\wp_umbrella_is_new_hash()) {
                \wp_umbrella_init_new_hash();
            }
            $hashedSecretToken = $contextService->getHash($secretToken);

            $options = $optionService->getOptions(['secure' => false]);
            $options['allowed'] = true;
            $options['api_key'] = $apiKey;
            $options['secret_token'] = $hashedSecretToken;
            $options['project_id'] = $existingProjectId;
            $optionService->setOptions($options);
            \wp_cache_flush();
            \wp_load_alloptions(true);

            if ($existingProjectId !== null) {
                $resp = $projectsService->validateSecretToken([
                    'base_url' => \site_url(),
                    'rest_url' => \rest_url(),
                    'secret_token' => $secretToken,
                    'http_auth_user' => null,
                    'http_auth_password' => null,
                    'save' => true,
                ], $apiKey);

                if (!is_array($resp) || empty($resp['success'])) {
                    $code = isset($resp['data']['code'])
                        ? $resp['data']['code']
                        : (isset($resp['code']) ? $resp['code'] : 'failed_authorize_wordpress');
                    $this->fail($optionService, $snapshot, 'validateSecretToken failed: ' . $code);
                    return;
                }

                $requestToken = \wp_umbrella_request_token_from_response($resp);
                if ($requestToken) {
                    $options['request_token'] = $requestToken;
                    $options['api_key'] = '';
                    $optionService->setOptions($options);
                }

                \WP_CLI::success(sprintf('Site re-attached to existing project %s.', (string) $existingProjectId));
                return;
            }

            // No existing project for this site under this owner → create one.
            $contextService->requireWpRewrite();
            $name = \get_bloginfo('name');
            $hosting = \wp_umbrella_get_service('HostResolver')->getCurrentHost();
            $createResp = $projectsService->createProjectOnApplication([
                'base_url' => \site_url(),
                'home_url' => \home_url(),
                'rest_url' => \rest_url(),
                'backdoor_url' => \plugins_url(),
                'admin_url' => \get_admin_url(),
                'wp_umbrella_url' => WP_UMBRELLA_DIRURL,
                'secret_token' => $secretToken,
                'is_multisite' => \is_multisite(),
                'name' => empty($name) ? \site_url() : $name,
                'hosting' => $hosting,
            ], $apiKey);

            $newProjectId = isset($createResp['result']['id']) ? $createResp['result']['id'] : null;
            $success = isset($createResp['success']) && $createResp['success'] === 'success';
            if (!$success || $newProjectId === null) {
                $code = isset($createResp['code']) ? $createResp['code'] : 'create_project_failed';
                $this->fail($optionService, $snapshot, 'createProjectOnApplication failed: ' . $code);
                return;
            }

            $options['project_id'] = $newProjectId;
            $optionService->setOptions($options);
            \update_option('wp_umbrella_backup_version', 'v4', false);

            \WP_CLI::success(sprintf('Site attached to new project %s.', (string) $newProjectId));
        } catch (\Exception $e) {
            $this->fail($optionService, $snapshot, 'Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Three outcomes:
     *   - null   : no workspace switch needed (0 or 1 workspaces, OR the user
     *              did not pass --workspace-id and we are non-interactive)
     *   - array  : a workspace from the list, possibly the one the user picked
     *   - false  : --workspace-id was passed but did not match anything
     */
    protected function resolveWorkspace(array $workspaces, $workspaceIdArg)
    {
        if (count($workspaces) === 0) {
            return null;
        }

        if ($workspaceIdArg !== null) {
            foreach ($workspaces as $workspace) {
                if (isset($workspace['id']) && (string) $workspace['id'] === $workspaceIdArg) {
                    return $workspace;
                }
            }
            return false;
        }

        if (count($workspaces) === 1) {
            // One workspace, no choice to make. Returning it (rather than
            // null) makes sure we still switch to its api_key, which can
            // differ from the one the user typed when they typed an old
            // personal key but their only access is now an invited workspace.
            return $workspaces[0];
        }

        // Multiple workspaces, interactive prompt. WP-CLI runs with STDIN
        // attached when invoked from a terminal; if it is being piped or
        // run in CI, the user is expected to pass --workspace-id and we
        // tell them so instead of hanging on fgets().
        if (!defined('STDIN') || !is_resource(STDIN) || !$this->isInteractive()) {
            \WP_CLI::error('Multiple workspaces available for this api_key. Re-run with --workspace-id=<id> (use --list-workspaces to see them).');
            return null;
        }

        $this->printWorkspaces($workspaces);
        \WP_CLI::log('');
        \WP_CLI::log('Pick a workspace by typing its id (or its index in the list above), then press Enter:');
        $input = trim((string) fgets(STDIN));

        // Allow either the workspace id or the 1-based index for ergonomics.
        if ($input === '') {
            \WP_CLI::error('No workspace selected.');
            return null;
        }
        if (ctype_digit($input)) {
            $idx = (int) $input - 1;
            if ($idx >= 0 && $idx < count($workspaces)) {
                return $workspaces[$idx];
            }
        }
        foreach ($workspaces as $workspace) {
            if (isset($workspace['id']) && (string) $workspace['id'] === $input) {
                return $workspace;
            }
        }
        \WP_CLI::error('Workspace not found. Run again with --workspace-id=<id> or --list-workspaces.');
        return null;
    }

    protected function isInteractive()
    {
        // posix_isatty is available on every PHP CLI build on Linux/macOS;
        // when missing (Windows, stripped builds) we assume interactive so
        // the user is not silently locked out of the picker.
        if (function_exists('posix_isatty') && defined('STDIN') && is_resource(STDIN)) {
            return @posix_isatty(STDIN);
        }
        return true;
    }

    protected function printWorkspaces(array $workspaces)
    {
        if (count($workspaces) === 0) {
            \WP_CLI::log('No workspaces accessible to this api_key.');
            return;
        }
        \WP_CLI::log('Workspaces accessible to this api_key:');
        $i = 1;
        foreach ($workspaces as $workspace) {
            $id = isset($workspace['id']) ? $workspace['id'] : '?';
            $name = isset($workspace['name']) ? $workspace['name'] : '?';
            \WP_CLI::log(sprintf('  %d) id=%s  name=%s', $i, $id, $name));
            $i += 1;
        }
    }

    protected function isAlreadyPaired($optionService)
    {
        $requestToken = $optionService->getRequestTokenWithoutCache();
        if (is_string($requestToken) && $requestToken !== '') {
            return true;
        }
        $apiKey = $optionService->getApiKeyWithoutCache();
        return is_string($apiKey) && $apiKey !== '';
    }

    protected function snapshotState($optionService)
    {
        $options = $optionService->getOptions(['secure' => false]);
        return [
            'api_key' => isset($options['api_key']) ? $options['api_key'] : '',
            'request_token' => isset($options['request_token']) ? $options['request_token'] : '',
            'secret_token' => isset($options['secret_token']) ? $options['secret_token'] : '',
            'project_id' => isset($options['project_id']) ? $options['project_id'] : '',
            'allowed' => isset($options['allowed']) ? $options['allowed'] : false,
        ];
    }

    protected function clearState($optionService)
    {
        $options = $optionService->getOptions(['secure' => false]);
        $options['api_key'] = '';
        $options['request_token'] = '';
        $options['secret_token'] = '';
        $options['project_id'] = '';
        $options['allowed'] = false;
        $optionService->setOptions($options);
    }

    protected function restoreState($optionService, $snapshot)
    {
        $options = $optionService->getOptions(['secure' => false]);
        $options['api_key'] = $snapshot['api_key'] ?? '';
        $options['request_token'] = $snapshot['request_token'] ?? '';
        $options['secret_token'] = $snapshot['secret_token'] ?? '';
        $options['project_id'] = $snapshot['project_id'] ?? '';
        $options['allowed'] = $snapshot['allowed'] ?? false;
        $optionService->setOptions($options);
    }

    protected function fail($optionService, $snapshot, $message)
    {
        if ($snapshot !== null) {
            $this->restoreState($optionService, $snapshot);
            \WP_CLI::warning('Attach failed; restored previous state.');
        } else {
            // No prior state to restore — wipe whatever we partially wrote so
            // the site is not left half-armed (api_key set, no request_token).
            $this->clearState($optionService);
        }
        \WP_CLI::error($message);
    }
}
