<?php

/** @noinspection ForgottenDebugOutputInspection */

include '../vendor/autoload.php';

use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

try {
    $client = new Client(
        [
            'api_key' => 'xyz',
            'nodes' => [
                [
                    'host' => 'localhost',
                    'port' => '8108',
                    'protocol' => 'http',
                ],
            ],
            'client' => new HttplugClient(),
        ]
    );
    echo '<pre>';

    print_r($client->debug->retrieve());
    print_r($client->metrics->retrieve());
    print_r($client->health->retrieve());
} catch (Exception $e) {
    echo $e->getMessage();
}
