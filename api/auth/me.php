<?php
require_once __DIR__ . '/../bootstrap.php';

$user = require_mobile_user();

mobile_json([
    'ok' => true,
    'user' => mobile_user_payload($user),
]);
