<?php

use LaMetric\Api;
use LaMetric\Response;
use LaMetric\Validator;

use Predis\Client as RedisClient;

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$response = new Response();

try {
    $credentials = is_file(__DIR__ . '/../config/credentials.php') ? include_once __DIR__ . '/../config/credentials.php' : [];
    $parameters  = include_once __DIR__ . '/../config/fields.php';

    $validator = new Validator($_GET);
    $validator->check($parameters);

    $api    = new Api(new RedisClient());
    $frames = $api->fetchData($validator->getData());

    echo $response->printData($frames);
} catch (Exception $exception) {
    echo $response->printError($exception->getMessage());
}
