<?php
namespace WPUmbrella\Core\Backup\Memento;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\Memento\Memento;

class BackupMemento implements Memento
{
    protected $state;

    protected $date;

    public function __construct($state)
    {
        $this->state = $state;
        $this->date = date('Y-m-d H:i:s');
    }

    public function getState()
    {
        return $this->state;
    }

    public function getName()
    {
        $handler = isset($this->state['handler']) ? $this->state['handler'] : '-';
        return $this->date . ' / (' . $handler . ')';
    }

    public function getDate()
    {
        return $this->date;
    }
}
