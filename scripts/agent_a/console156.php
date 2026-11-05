<?php

declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$response = $kernel->handle(Request::create('/console-fiscal/status', 'GET'));
$data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
echo json_encode(['http_status' => $response->getStatusCode(), 'observed_endpoint' => '/console-fiscal/status',
    'read_only' => true, 'state' => $data], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
