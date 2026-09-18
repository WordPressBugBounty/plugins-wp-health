<?php

namespace WPUmbrella\Core\BackupScript;

/**
 * The file list of every compiled script generation.
 *
 * The order is the contract. The compiled file is a single PHP file, so a class
 * whose parent, interface or trait is declared further down fatals the moment
 * the site loads it. Everything the head of the list pins (the exceptions, the
 * capacity trait, the two database interfaces, the abstract process, the
 * context) stays ahead of what extends it, `script.php` stays last because it
 * runs, and the rest is alphabetical so that two machines produce the same
 * bytes.
 *
 * Until this list existed, the order came out of the filesystem iterator and the
 * partial sort in front of it: the site and the developer machine compiled the
 * same sources into two different files.
 *
 * The list is exhaustive. A source dropped into the directory without being
 * declared here is not compiled, and `reconcile` says so rather than letting it
 * land in an arbitrary position.
 */
class BackupScriptManifest
{
    const GENERATION_BACKUP_V4 = 'backup-v4';

    /**
     * @return array
     */
    public static function generations()
    {
        return [
            self::GENERATION_BACKUP_V4 => [
                'source' => 'backup-script',
                // Changing this name is not a one line change: the cleanup, the
                // mu-plugin, the router, the upload allowlist and the worker
                // endpoint all spell it out too.
                'output' => 'cloner.php',
                'files' => [
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
                ],
            ],
        ];
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
