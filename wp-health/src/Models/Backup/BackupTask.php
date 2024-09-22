<?php

namespace WPUmbrella\Models\Backup;

use WPUmbrella\Core\UmbrellaDateTime;
use DateTimeInterface;
use Exception;

class BackupTask
{

	/**
	 * @var array
	 */
	protected $data = [];

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function getBackupStatus(){
		if(!isset($this->data['backup_status'])){
			return null;
		}
		return $this->data['backup_status'];
	}

	public function getBackupFinishFile(){
		if(!isset($this->data['finish_file'])){
			return null;
		}
		return $this->data['finish_file'];
	}

	public function getBackupFinishDatabase(){
		if(!isset($this->data['finish_database'])){
			return null;
		}
		return $this->data['finish_database'];
	}


	/**
	 * @return int|null
	 */
	public function getBackupId(): ?int
	{
		return $this->data['backupId'];
	}

	public function getType(): ?string
	{
		return $this->data['type'];
	}

	public function getDateStart(): ?DateTimeInterface
	{
		try{
			if(is_null($this->data['date_start'])){
				return null;
			}
			return new UmbrellaDateTime( $this->data['date_start'] );
		}catch (Exception $e){
			return null;
		}
	}

	public function getDateSchedule(): ?DateTimeInterface
	{
		try{
			return new UmbrellaDateTime( $this->data['date_schedule'] );
		}catch (Exception $e){
			return null;
		}
	}

	public function getDateEnd(): ?DateTimeInterface
	{
		try{
			if(is_null($this->data['date_end'])){
				return null;
			}

			return new UmbrellaDateTime( $this->data['date_end'] );
		}catch (Exception $e){
			return null;
		}
	}

	public function getId()
	{
		return $this->data['id'];
	}

	/**
	 * @return string|null
	 */
	public function getStatus(): ?string
	{
		return $this->data['status'];
	}
}
