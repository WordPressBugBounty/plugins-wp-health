<?php

namespace WPUmbrella\Models\Backup;

use DateTime;
use DateTimeInterface;
use Exception;

class Backup
{

	/**
	 * @var array
	 */
	protected $data = [];

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function getId()
	{
		return $this->data['id'];
	}

	public function countAttachments()
	{
		return $this->data['count_attachments'];
	}

	/**
	 * @return string
	 */
	public function countPublicPosts()
	{
		return $this->data['count_public_posts'];
	}

	/**
	 * @return string
	 */
	public function countPlugins()
	{
		return $this->data['count_plugins'];
	}

	/**
	 * @return string
	 */
	public function getWordPressCoreVersion(): string
	{
		return $this->data['wp_core_version'];
	}

	/**
	 * @return string
	 */
	public function getConfigDatabase()
	{
		return json_decode($this->data['config_database'], true);
	}

	/**
	 * @return string
	 */
	public function getConfigFile()
	{
		return json_decode($this->data['config_file'], true);
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->data['title'];
	}

	/**
	 * @return string
	 */
	public function getSuffix(): string
	{
		return $this->data['suffix'];
	}

	/**
	 * @return string
	 */
	public function getStatus()
	{
		if( is_null( $this->data['status'] ) ){
			return '';
		}

		return $this->data['status'];
	}

	/**
	 * @return string
	 */
	public function isScheduled()
	{
		return $this->data['is_scheduled'];
	}

	/**
	 * @return string
	 */
	public function getBackupId()
	{
		return $this->data['backupId'];
	}

	/**
	 * @return string
	 */
	public function getIncrementalDate()
	{
		return $this->data['incremental_date'];
	}

	/**
	 * @return string
	 */
	public function inError()
	{
		return $this->data['in_error'];
	}

	/**
	 * @return string
	 */
	public function finishFile(): string
	{
		return $this->data['finish_file'];
	}

	/**
	 * @return string
	 */
	public function finishDatabase(): string
	{
		return $this->data['finish_database'];
	}
}
