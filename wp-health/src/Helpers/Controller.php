<?php
namespace WPUmbrella\Helpers;

abstract class Controller
{
    const API = 'api';
    const PHP = 'php';

    const PERMISSION_ONLY_API_TOKEN = 'authorize_only_api_token';
    const PERMISSION_WITH_SECRET_TOKEN = 'authorize_secret_token';
}
