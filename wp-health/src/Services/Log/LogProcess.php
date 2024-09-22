<?php
namespace WPUmbrella\Services\Log;

if (!defined('ABSPATH')) {
    exit;
}

class LogProcess
{
    public function log($message, $options = [])
    {
        if (!isset($options['logfile'])) {
            return false;
        }

        $pathInfo = \pathinfo($options['logfile']);
        if (isset($pathInfo['dirname']) && !\file_exists($pathInfo['dirname'])) {
            \mkdir($pathInfo['dirname'], 0777, true);
        }

        $logfile = $options['logfile'];

        try {
            if (!file_exists($logfile)) {
                file_put_contents($logfile, '[]');
            }

            $current = $this->getContent($logfile);
            $current[] = [
				"date" => date("Y-m-d H:i:s"),
				"message" => $message,
			];

            file_put_contents($logfile, json_encode($current));
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


	public function getContent($logfile){
		if (!file_exists($logfile)) {
			return [];
		}

		return json_decode(file_get_contents($logfile), true);
	}
}
