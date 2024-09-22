<?php
namespace WPUmbrella\Core\Backup\Memento;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Restore\Memento\BackupMemento;
use WPUmbrella\Core\Models\Memento\Memento;

class BackupOriginator
{
    protected $state = [
    ];

    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    public function setValueInState($key, $value)
    {
        $this->state[$key] = $value;
        return $this;
    }

    public function getValueInState($key)
    {
        if (isset($this->state[$key])) {
            return $this->state[$key];
        }
        return null;
    }

	public function getTableByIterator($index){
		$tables = $this->getValueInState('tables');
		if(empty($tables)){
			return null;
		}

		foreach($tables as $table){
			if($table['index'] == $index){
				return $table;
			}
		}
	}

    public function getState()
    {
        return $this->state;
    }

    public function save()
    {
        return new BackupMemento($this->state);
    }

    public function restore(Memento $memento)
    {
        $this->setState($memento->getState());
    }
}
