<?php

namespace WPUmbrella\Core\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupSource;
use WPUmbrella\Models\Backup\BackupDestination;

class Profile
{

	protected $namer;

	protected $scratchDirectory;

	protected $processor;

	protected $sources = [];

	protected $destinations = [];

	/**
	 *
	 * @param string $name
	 * @param string $scratchDirectory
	 * @param array $sources
	 * @param array $destinations
	 */
    public function __construct($namer, $scratchDirectory, $processor, array $sources = [], array $destinations = [])
    {
        $this->namer = $namer;
        $this->scratchDirectory = $scratchDirectory;
        $this->processor = $processor;

        foreach ($sources as $source) {
            $this->addSource($source);
        }

        foreach ($destinations as $destination) {
            $this->addDestination($destination);
        }
    }

	public function addSource(BackupSource $source)
	{
		$this->sources[] = $source;
		return $this;
	}

	public function addDestination(BackupDestination $destination)
	{
		$this->destinations[] = $destination;
		return $this;
	}

	public function getSources(){
		return $this->sources;
	}

	public function getDestinations(){
		return $this->destinations;
	}

	public function getName(){
		return $this->namer->getName();
	}

	public function getProcessor(){
		return $this->processor;
	}

	public function getScratchDirectory()
	{
		return $this->scratchDirectory;
	}
}
