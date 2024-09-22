<?php
namespace WPUmbrella\Models\Backup;


interface BackupDestination
{
    public function send($extension);
}
