<?php
require_once __DIR__ . '/../bootstrap.php';

$user = require_mobile_user();
$conversations = get_conversations_for_user((int) $user['id']);

mobile_json([
    'ok' => true,
    'conversations' => array_map('mobile_conversation_payload', $conversations),
]);
