<?php

namespace WPUmbrella\Core\BackupScript;

/**
 * Compiles a script generation into the single file the site runs.
 *
 * One implementation, used by the plugin when it deploys the module on a
 * customer site and by the developer entry points. It reads the manifest and
 * nothing else: no directory scan decides the order any more.
 *
 * The first line of every source is dropped, which is how the opening tag is
 * stripped, so every source must keep `<?php` alone on its first line. The class
 * guards stay written by hand in the sources.
 */
class BackupScriptCompiler
{
    /**
     * @var string
     */
    protected $pluginDirectory;

    /**
     * @param string $pluginDirectory Directory holding the source directories
     */
    public function __construct($pluginDirectory)
    {
        $this->pluginDirectory = rtrim($pluginDirectory, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $generation
     * @return string
     * @throws \RuntimeException When a declared source is missing
     */
    public function compile($generation)
    {
        $manifest = BackupScriptManifest::generation($generation);

        $missing = $this->missingSources($generation);

        if (!empty($missing)) {
            throw new \RuntimeException(sprintf(
                'Cannot compile generation "%s": %d declared source(s) missing on disk: %s.',
                $generation,
                count($missing),
                implode(', ', $missing)
            ));
        }

        $output = '';

        foreach ($manifest['files'] as $relativePath) {
            $output .= $this->readWithoutFirstLine($this->sourcePath($manifest, $relativePath));
        }

        return "<?php \n" . $output;
    }

    /**
     * @param string $generation
     * @return string
     */
    public function outputName($generation)
    {
        $manifest = BackupScriptManifest::generation($generation);

        return $manifest['output'];
    }

    /**
     * Declared sources that are not on disk. Compiling with one of these missing
     * produces a file that fatals on the site, so compile() refuses.
     *
     * @param string $generation
     * @return array
     */
    public function missingSources($generation)
    {
        $manifest = BackupScriptManifest::generation($generation);
        $missing = [];

        foreach ($manifest['files'] as $relativePath) {
            if (!is_readable($this->sourcePath($manifest, $relativePath))) {
                $missing[] = $relativePath;
            }
        }

        return $missing;
    }

    /**
     * Sources on disk that the manifest does not declare. They are simply not
     * compiled, which is the safe behaviour on a customer site; on a developer
     * machine it means a file was added and never declared, and `reconcile`
     * turns it into an error.
     *
     * @param string $generation
     * @return array
     */
    public function undeclaredSources($generation)
    {
        $manifest = BackupScriptManifest::generation($generation);
        $directory = $this->pluginDirectory . DIRECTORY_SEPARATOR . $manifest['source'];

        if (!is_dir($directory)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isDir() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            // Test files sit next to the sources and pull in PHPUnit, which is
            // not there once merged.
            if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            // The manifest spells its paths with forward slashes, whatever the
            // machine compiling.
            $found[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($file->getPathname(), strlen($directory) + 1)
            );
        }

        $undeclared = array_values(array_diff($found, $manifest['files']));
        sort($undeclared, SORT_STRING);

        return $undeclared;
    }

    /**
     * @param array $manifest
     * @param string $relativePath
     * @return string
     */
    protected function sourcePath($manifest, $relativePath)
    {
        return $this->pluginDirectory
            . DIRECTORY_SEPARATOR
            . $manifest['source']
            . DIRECTORY_SEPARATOR
            . $relativePath;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function readWithoutFirstLine($path)
    {
        $content = file_get_contents($path);
        $firstLineBreak = strpos($content, "\n");

        if ($firstLineBreak === false) {
            return '';
        }

        return substr($content, $firstLineBreak + 1);
    }
}
