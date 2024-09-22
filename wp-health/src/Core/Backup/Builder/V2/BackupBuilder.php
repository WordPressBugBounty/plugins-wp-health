<?php
namespace WPUmbrella\Core\Backup\Builder\V2;

use WPUmbrella\Core\Backup\Profile;
use WPUmbrella\Core\Backup\Namer\BackupNamer;
use WPUmbrella\Core\Backup\Source\V2\FinderBySizeSource;
use WPUmbrella\Core\Backup\Source\FinderByFileSource;
use WPUmbrella\Core\Backup\Source\V2\MySqlManualByTableSource;
use WPUmbrella\Core\Backup\Processor\ZipArchiveProcessor;
use WPUmbrella\Core\Backup\Processor\GzipArchiveProcessor;
use WPUmbrella\Core\Backup\Processor\ZipPhpArchiveProcessor;
use WPUmbrella\Core\Backup\Destination\UmbrellaDestination;
use WPUmbrella\Models\Backup\BackupBuilder as BackupBuilderModel;

class BackupBuilder implements BackupBuilderModel
{
    protected $mainDestination = null;

    protected $processor = null;

    const DEFAULT_TYPE_FILE = 'finder-by-size';

    const DEFAULT_TYPE_SQL = 'mysqlmanual-by-table';

    const DEFAULT_PROCESSOR = 'zip-php';

    public function reset()
    {
        $this->processor = null;
        $this->mainDestination = null;
        $this->fileSource = null;
        $this->sqlSource = null;
        $this->namer = null;
        $this->profile = null;
    }

    /**
     * @param string $name
     */
    public function buildNamer($name)
    {
        $namer = new BackupNamer();
        $namer->setName($name);

        $this->namer = $namer;

        return $this;
    }

    /**
     * @param array $options
     * 		- type: string (default: finder-by-size)
     * 		- base_directory: string (default: ABSPATH)
     * 		- size: number
     */
    public function buildFileSource($options = [])
    {
        $incrementalDate = isset($options['incremental_date']) ? $options['incremental_date'] : null;

        $type = isset($options['type']) ? $options['type'] : self::DEFAULT_TYPE_FILE;
        if ($type === null || empty($type)) {
            $type = self::DEFAULT_TYPE_FILE;
        }

        $baseDirectory = isset($options['base_directory']) ? $options['base_directory'] : ABSPATH;
        if ($baseDirectory === null || empty($baseDirectory)) {
            $baseDirectory = ABSPATH;
        }

        $source = null;
        switch ($type) {
            case 'finder-by-file':
                $source = new FinderByFileSource($this->namer, $baseDirectory);
                if ($incrementalDate) {
                    $source->setSinceDate($incrementalDate);
                }
                if (isset($options['size'])) {
                    $source->setSize($options['size']);
                }
                break;
            case 'finder-by-size':
                $source = new FinderBySizeSource($this->namer, $baseDirectory);
                $source->setVersion($options['version'] ?? 'v1');
                if ($incrementalDate) {
                    $source->setSinceDate($incrementalDate);
                }
                if (isset($options['size'])) {
                    $source->setSize($options['size']);
                }
                break;
        }

        if ($source === null) {
            return $this;
        }

        $this->fileSource = $source;

        return $this;
    }

    /**
     * @param array $options
     * 		- type: string (default: mysqlmanual-by-table)
     * 		- database: string (default: DB_NAME)
     * 		- host: string (default: DB_HOST)
     * 		- user: string (default: DB_USER)
     * 		- password: string (default: DB_PASSWORD)
     * 		- sock: string (default: null)
     */
    public function buildSqlSource($options = [])
    {
        $type = isset($options['type']) ? $options['type'] : self::DEFAULT_TYPE_SQL;

        $database = isset($options['database']) ? $options['database'] : DB_NAME;
        $databaseUser = isset($options['user']) ? $options['user'] : DB_USER;
        $databasePassword = isset($options['password']) ? $options['password'] : DB_PASSWORD;
        $databaseHost = isset($options['host']) ? $options['host'] : DB_HOST;
        $databaseSocket = isset($options['sock']) ? $options['sock'] : null;

        $source = null;
        switch ($type) {
            case 'mysqlmanual-by-table':
                $source = new MySqlManualByTableSource($this->namer, $database, [
                    'user' => $databaseUser,
                    'password' => $databasePassword,
                    'host' => $databaseHost,
                    'sock' => $databaseSocket,
                ]);

                $source->setVersion($options['version'] ?? 'v1');
                break;
        }

        if ($source === null) {
            return $this;
        }

        $this->sqlSource = $source;
        return $this;
    }

    /**
     * @param array $options
     * 		- type: string (default: zip-php)
     */
    public function buildProcessor($options = [])
    {
        $type = isset($options['type']) ? $options['type'] : self::DEFAULT_PROCESSOR;

        $processor = null;
        switch ($type) {
            case 'zip':
                $processor = new ZipArchiveProcessor($this->namer);
                break;
            case 'gzip':
                $processor = new GzipArchiveProcessor($this->namer);
                break;
            case 'zip-php':
                $processor = new ZipPhpArchiveProcessor($this->namer);
                break;
                ;
        }

        if ($processor === null) {
            return $this;
        }

        $this->processor = $processor;
        return $this;
    }

    /**
     * @param array $options
     * 		- type: string (default: umbrella)
     * @return BackupBuilder
     */
    public function buildDestination($options = [])
    {
        $type = isset($options['type']) ? $options['type'] : 'umbrella';
        $api = isset($options['api']) && $options['api'] !== null ? $options['api'] : wp_umbrella_get_service('BackupApi');

        $destination = null;
        switch ($type) {
            case 'umbrella':
                $destination = new UmbrellaDestination($this->namer);
                $destination->setApi($api);
                break;
        }

        $this->mainDestination = $destination;
        return $this;
    }

    /**
     *
     * @return void
     */
    public function buildProfile($scratchDirectory = null)
    {
        if ($this->namer === null) {
            return;
        }

        $sources = [];
        if ($this->fileSource !== null) {
            $sources[] = $this->fileSource;
        }

        if ($this->sqlSource !== null) {
            $sources[] = $this->sqlSource;
        }

        $destinations = [];
        if ($this->mainDestination !== null) {
            $destinations[] = $this->mainDestination;
        }

        if ($scratchDirectory === null) {
            $directorySuffix = get_option('wp_umbrella_backup_suffix_security');
            $scratchDirectory = sprintf('%s/%s', WP_UMBRELLA_DIR_WPU_BACKUP_BOX, $directorySuffix);
        }

        $this->profile = new Profile($this->namer, $scratchDirectory, $this->processor, $sources, $destinations);
    }

    /**
     *
     * @return Profile
     */
    public function getProfile()
    {
        return $this->profile;
    }
}
