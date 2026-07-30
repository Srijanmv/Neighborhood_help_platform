<?php
require 'inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'reply' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($payload['message'] ?? ''));

if ($message === '') {
    echo json_encode([
        'success' => true,
        'reply' => 'Type your question and I will help you quickly.',
        'suggestions' => ['OTP help', 'Create new post', 'Google sign-in issue']
    ]);
    exit;
}

$user = current_user();
$userName = $user['name'] ?? 'Resident';
$userArea = $user['area'] ?? 'Unknown area';

$systemPrompt = "You are Neighborhood Help Chatbot for a local community issue reporting app. " .
    "Keep answers short, practical, and friendly. " .
    "Prioritize app workflows: register/login, OTP verify, Google/Apple signup, create post, map view, comments, admin status updates. " .
    "When user asks technical setup, give step-by-step bullets. " .
    "If user asks non-app question, politely redirect to app help.";

function fallback_reply($text) {
    $normalized = mb_strtolower($text, 'UTF-8');

    if (strpos($normalized, 'otp') !== false || strpos($normalized, 'verify') !== false) {
        return 'OTP flow: register -> verify OTP page -> enter 6-digit code -> account activates. If email is delayed, use Resend OTP and check spam/promotions.';
    }

    if (strpos($normalized, 'google') !== false || strpos($normalized, 'apple') !== false) {
        return 'For Google/Apple signup, select Area first. Google needs valid client ID. Apple needs client ID + redirect URI configured in app and Apple Developer console.';
    }

    if (strpos($normalized, 'post') !== false || strpos($normalized, 'issue') !== false) {
        return 'Go to New Post, add title/description/location and optional image, then submit. Track progress in Community feed.';
    }

    if (strpos($normalized, 'map') !== false) {
        return 'Open Map View to see issue pins by location and click any pin to open the post.';
    }

    return 'I can help with OTP verification, social sign-in, posting issues, map view, and account/login problems.';
}

$reply = '';
$error = '';

if ($llm_api_key !== '') {
    $requestBody = [
        'model' => $llm_model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "User: $userName | Area: $userArea\nQuestion: " . $message]
        ],
        'temperature' => 0.5,
        'max_tokens' => 240
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($llm_api_base);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $llm_api_key,
                'HTTP-Referer: http://localhost/neighborhood_help',
                'X-Title: ' . $llm_app_name
            ],
            CURLOPT_POSTFIELDS => json_encode($requestBody)
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw !== false && $status >= 200 && $status < 300) {
            $decoded = json_decode($raw, true);
            $reply = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        } else {
            $error = $curlError !== '' ? $curlError : ('HTTP ' . $status);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $llm_api_key,
                    'HTTP-Referer: http://localhost/neighborhood_help',
                    'X-Title: ' . $llm_app_name
                ]),
                'content' => json_encode($requestBody)
            ]
        ]);

        $raw = @file_get_contents($llm_api_base, false, $context);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            $reply = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        } else {
            $error = 'Network request failed.';
        }
    }
}

if ($reply === '') {
    $reply = fallback_reply($message);
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'suggestions' => ['OTP help', 'Create new post', 'Google/Apple setup'],
    'meta' => [
        'model' => $llm_model,
        'fallback' => $error !== '' ? true : false
    ]
]);
