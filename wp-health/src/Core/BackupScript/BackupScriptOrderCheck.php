<?php

namespace WPUmbrella\Core\BackupScript;

/**
 * Proves the manifest order is loadable.
 *
 * The compiled output is one PHP file, so a class whose parent, interface or
 * trait is declared further down fatals the moment the site loads it. This is
 * the check that makes the manifest order safe to edit: it is what the
 * directory scan and its partial sort used to approximate, and never proved.
 *
 * A developer tool, called by `bin/backup-script.php` and by the test suite.
 * Nothing on a customer site loads it.
 */
class BackupScriptOrderCheck
{
    /**
     * @var string
     */
    protected $pluginDirectory;

    /**
     * @param string $pluginDirectory
     */
    public function __construct($pluginDirectory)
    {
        $this->pluginDirectory = rtrim($pluginDirectory, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $generation
     * @return array Human readable problems, empty when the order holds
     */
    public function forwardReferences($generation)
    {
        $manifest = BackupScriptManifest::generation($generation);

        return $this->forwardReferencesInOrder($manifest['source'], $manifest['files']);
    }

    /**
     * The check itself, on any order. Kept separate from the manifest so a test
     * can hand it a deliberately broken order and watch it bite: a guard that
     * has only ever been seen returning an empty array is not a proven guard.
     *
     * @param string $sourceDirectory
     * @param array $files
     * @return array
     */
    public function forwardReferencesInOrder($sourceDirectory, $files)
    {
        $declaredAt = [];
        $used = [];

        foreach ($files as $position => $relativePath) {
            $source = file_get_contents(
                $this->pluginDirectory
                . DIRECTORY_SEPARATOR . $sourceDirectory
                . DIRECTORY_SEPARATOR . $relativePath
            );

            foreach ($this->declaredSymbols($source) as $symbol) {
                if (!isset($declaredAt[$symbol])) {
                    $declaredAt[$symbol] = $position;
                }
            }

            $used[$position] = $this->requiredSymbols($source);
        }

        $problems = [];

        foreach ($used as $position => $symbols) {
            foreach ($symbols as $symbol) {
                // A symbol no file of the generation declares is a global or a
                // WordPress one, so it is not an ordering problem of ours.
                if (!isset($declaredAt[$symbol]) || $declaredAt[$symbol] <= $position) {
                    continue;
                }

                $problems[] = sprintf(
                    '%s (position %d) uses %s, declared at position %d',
                    $files[$position],
                    $position,
                    $symbol,
                    $declaredAt[$symbol]
                );
            }
        }

        return $problems;
    }

    /**
     * @param string $source
     * @return array
     */
    protected function declaredSymbols($source)
    {
        if (!preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/mi', $source, $matches)) {
            return [];
        }

        return $matches[1];
    }

    /**
     * @param string $source
     * @return array
     */
    protected function requiredSymbols($source)
    {
        $required = [];

        if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?class\s+\w+\s+extends\s+\\\\?(\w+)/mi', $source, $matches)) {
            $required = array_merge($required, $matches[1]);
        }

        if (preg_match_all('/^\s*interface\s+\w+\s+extends\s+([\w\s,\\\\]+)/mi', $source, $matches)) {
            $required = array_merge($required, $this->splitSymbolList($matches[1]));
        }

        $implements = '/^\s*(?:abstract\s+|final\s+)?class\s+\w+(?:\s+extends\s+\\\\?\w+)?\s+implements\s+([\w\s,\\\\]+)/mi';

        if (preg_match_all($implements, $source, $matches)) {
            $required = array_merge($required, $this->splitSymbolList($matches[1]));
        }

        // A trait is pulled in with a `use` statement at the top of the class
        // body; the namespace form never appears in these sources.
        if (preg_match_all('/^\s*use\s+\\\\?(\w+)\s*;/mi', $source, $matches)) {
            $required = array_merge($required, $matches[1]);
        }

        return array_unique(array_filter($required));
    }

    /**
     * @param array $lists
     * @return array
     */
    protected function splitSymbolList($lists)
    {
        $symbols = [];

        foreach ($lists as $list) {
            foreach (explode(',', $list) as $symbol) {
                // The capture runs up to the opening brace, so the last symbol
                // of an implements list carries the line break with it. Without
                // \r\n here every interface came back one byte too long, never
                // matched a declaration, and the check was silently blind to
                // every implements clause.
                $symbols[] = trim($symbol, " \t\r\n\\{");
            }
        }

        return $symbols;
    }
}
