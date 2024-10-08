<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}

class DownloadZip
{
    /**
     * Copy remote file over HTTP one small chunk at a time.
     *
     * @param $infile The full URL to the remote file
     * @param $outfile The path where to save the file
     */
    public function copyFileChunked($infile, $outfile)
    {
        try {
            @set_time_limit(0);

            $chunksize = 10 * (1024 * 1024); // 10 Megs

            /**
             * parse_url breaks a part a URL into it's parts, i.e. host, path,
             * query string, etc.
             */
            $parts = parse_url($infile);
            $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
            $o_handle = fopen($outfile, 'wb');

            if ($i_handle == false || $o_handle == false) {
                return false;
            }

            if (!empty($parts['query'])) {
                $parts['path'] .= '?' . $parts['query'];
            }

            /**
             * Send the request to the server for the file
             */
            $request = "GET {$parts['path']} HTTP/1.1\r\n";
            $request .= "Host: {$parts['host']}\r\n";
            $request .= "User-Agent: Mozilla/5.0\r\n";
            $request .= "Keep-Alive: 115\r\n";
            $request .= "Connection: keep-alive\r\n\r\n";
            fwrite($i_handle, $request);

            /**
             * Now read the headers from the remote server. We'll need
             * to get the content length.
             */
            $headers = [];
            while (!feof($i_handle)) {
                $line = fgets($i_handle);
                if ($line == "\r\n") {
                    break;
                }
                $headers[] = $line;
            }

            /**
             * Look for the Content-Length header, and get the size
             * of the remote file.
             */
            $length = 0;
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Length:') === 0) {
                    $length = (int)str_replace('Content-Length: ', '', $header);
                    break;
                }
            }

            /**
             * Start reading in the remote file, and writing it to the
             * local file one chunk at a time.
             */
            $cnt = 0;
            while (!feof($i_handle)) {
                $buf = '';
                $buf = fread($i_handle, $chunksize);
                $bytes = fwrite($o_handle, $buf);
                if ($bytes == false) {
                    return false;
                }
                $cnt += $bytes;

                /**
                 * We're done reading when we've reached the conent length
                 */
                if ($cnt >= $length) {
                    break;
                }
            }

            fclose($i_handle);
            fclose($o_handle);
            // return $cnt;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function handle($url, $filename)
    {
        try {
            if (empty($url)) {
                return false;
            }

            if (file_exists($filename)) {
                wp_umbrella_remove_file($filename);
            }

            $result = $this->copyFileChunked($url, $filename);

            if (!$result) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
