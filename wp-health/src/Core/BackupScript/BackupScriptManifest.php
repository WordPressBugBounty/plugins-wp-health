<?php

namespace WPUmbrella\Core\BackupScript;

/**
 * The file list of every compiled script generation.
 */
class BackupScriptManifest
{
    const GENERATION_V4 = 'v4';
    const GENERATION_V5 = 'v5';

    const SHARED_SOURCE_PREFIX = '../backup-script/';

    /**
     * @return array
     */
    public static function generations()
    {
        return [
            self::GENERATION_V4 => [
                'source' => 'backup-script',
                'output' => 'cloner.php',
                'ships_artifact' => true,
                'files' => self::firstGenerationFiles(),
            ],
            self::GENERATION_V5 => [
                'source' => 'backup-script-v5',
                'output' => 'cloner.php',
                'ships_artifact' => false,
                'tokens' => [
                    '[[UMBRELLA_SCRIPT_GENERATION]]' => self::GENERATION_V5,
                ],
                'files' => array_merge(
                    self::sharedFromFirstGeneration(),
                    [
                        'Step/ScriptGeneration.php',
                        'Step/StepProtocol.php',
                        'Step/StepContext.php',
                        'Step/StepRequestContext.php',
                        'Step/StepOutcome.php',
                        'Step/StepSocket.php',
                        'Step/AbstractStepHandler.php',
                        'Step/Handler/ScanStep.php',
                        'Step/Handler/ScanDictionaryStep.php',
                        'Step/Handler/ScanSizeStep.php',
                        'Step/Handler/ScanIntegrityStep.php',
                        'Step/Handler/StreamDatabaseStep.php',
                        'Step/Handler/StreamFilesStep.php',
                        'Step/Handler/CheckDirectoriesStep.php',
                        'Step/Handler/CleanupStep.php',
                        'Step/StepRegistry.php',
                        'script.php',
                    ]
                ),
            ],
        ];
    }

    /**
     * @return array
     */
    protected static function firstGenerationFiles()
    {
        return [
            'Exception/DefaultException.php',
            'Exception/UmbrellaException.php',
            'Exception/UmbrellaInternalRequestException.php',
            'Exception/UmbrellaSocketException.php',
            'Backup/ProcessCapacityTrait.php',
            'Exception/UmbrellaPreventMaxExecutionTime.php',
            'Exception/UmbrellaDatabasePreventMaxExecutionTime.php',
            'Database/Model/ConnectionInterface.php',
            'Database/Model/DatabaseStatementInterface.php',
            'Backup/ChecksumDictionaryGenerator.php',
            'Backup/SiteChecksumDirectoryGenerator.php',
            'Backup/AbstractProcessBackup.php',
            'Context.php',
            'Backup/BackupLogCode.php',
            'Backup/CheckupDirectories.php',
            'Backup/DatabaseBackup.php',
            'Backup/FileBackup.php',
            'Backup/ScanBackup.php',
            'Cleanup.php',
            'Database/Connection/MySQLConnection.php',
            'Database/Connection/MySQLiConnection.php',
            'Database/Connection/PDOConnection.php',
            'Database/DatabaseConfiguration.php',
            'Database/DatabaseFunction.php',
            'Database/Dump/SqlInstruction.php',
            'Database/Import/CharsetFixer.php',
            'Database/Import/DatabaseImportTable.php',
            'Database/Import/DumpScanner.php',
            'Database/Import/ImportDump.php',
            'Database/Import/ImportState.php',
            'Database/Model/TableType.php',
            'Database/Statement/MySQLStatement.php',
            'Database/Statement/MySQLiStatement.php',
            'Database/Statement/PDOStatement.php',
            'Database/Table/Column.php',
            'Database/Table/Table.php',
            'Encryption/UTF8.php',
            'ErrorHandler/ErrorHandler.php',
            'FileHandle.php',
            'Filesystem/RotatingFileHandle.php',
            'HTMLSynchronize.php',
            'Helper/DirectoryExclusion.php',
            'Helper/FileExclusion.php',
            'Helper/StatInfo.php',
            'Helper/UmbrellaDirectoryIterator.php',
            'Stream/WebSocket.php',
            'script.php',
        ];
    }

    /**
     * Every source of the first generation but its entry point, in its order.
     *
     * @return array
     */
    protected static function sharedFromFirstGeneration()
    {
        $shared = [];

        foreach (self::firstGenerationFiles() as $file) {
            if ($file === 'script.php') {
                continue;
            }

            $shared[] = self::SHARED_SOURCE_PREFIX . $file;
        }

        return $shared;
    }

    /**
     * @param string $generation
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function generation($generation)
    {
        $generations = self::generations();

        if (!isset($generations[$generation])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown script generation "%s". Known generations: %s.',
                $generation,
                implode(', ', array_keys($generations))
            ));
        }

        return $generations[$generation];
    }
}
