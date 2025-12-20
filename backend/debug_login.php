<?php
$baseUrl = 'http://localhost:8000/api';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$baseUrl/auth/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'admin@system.com',
        'password' => 'test123'
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";
echo "Response:\n";
print_r(json_decode($response, true));
