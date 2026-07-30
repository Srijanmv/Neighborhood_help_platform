<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$token = token_from_request();
if ($token !== '') {
    revoke_api_token($token);
}

mobile_json([
    'ok' => true,
]);
