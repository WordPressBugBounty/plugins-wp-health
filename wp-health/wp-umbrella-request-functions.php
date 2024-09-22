<?php

function wp_umbrella_get_headers()
{
    $function = 'getallheaders';
    $headers = [];

    if (function_exists($function)) {
        $headers = $function();
    } else {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = substr($name, 5);
                $name = str_replace('_', ' ', $name);
                $name = strtolower($name);
                $name = ucwords($name);
                $name = str_replace(' ', '-', $name);

                $headers[$name] = $value;
            } elseif ($function === 'apache_request_headers') {
                $headers[$name] = $value;
            }
        }
    }

    return array_change_key_case($headers, CASE_LOWER);
}


function wp_umbrella_get_parameters($method = 'POST')
{
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            return $_POST;
        }
        return $data;
    } elseif ($method === 'GET') {
        return $_GET;
    }
}
