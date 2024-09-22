<?php
namespace WPUmbrella\Services\Manage;

abstract class BaseManageUpdate
{
    protected function getError($error)
    {
        if (!is_wp_error($error)) {
            return $error != '' ? $error : '';
        } else {
            $errors = [];
            if (!empty($error->error_data)) {
                foreach ($error->error_data as $error_key => $error_string) {
                    $errors[] = $error_key;
                }
            } elseif (!empty($error->errors)) {
                foreach ($error->errors as $error_key => $err) {
                    $errors[] = strtolower($error_key);
                }
            }

            return $errors;
        }
    }
}
