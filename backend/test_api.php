<?php
/**
 * API Test - New Field Names (cartons, total_weight, price)
 */

$baseUrl = 'http://localhost:8000/api';
$token = null;

function request($method, $endpoint, $data = [])
{
    global $baseUrl, $token;

    $ch = curl_init();
    $url = $baseUrl . $endpoint;

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $method !== 'GET' ? json_encode($data) : null,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response];
}

echo "=== 🧪 Testing New Invoice API Fields ===\n\n";

// Login
$res = request('POST', '/auth/login', ['email' => 'admin@system.com', 'password' => 'test123']);
$token = $res['body']['token']
    ?? $res['body']['data']['access_token']
    ?? $res['body']['access_token']
    ?? null;
echo "1️⃣ Login: " . ($token ? "✅" : "❌") . "\n";

// Get product
$res = request('GET', '/products');
$productId = $res['body']['data'][0]['id'] ?? 1;
echo "2️⃣ Product ID: $productId\n";

// Get supplier/customer
$res = request('GET', '/suppliers');
$supplierId = $res['body']['data'][0]['id'] ?? 1;
$res = request('GET', '/customers');
$customerId = $res['body']['data'][0]['id'] ?? 1;
echo "3️⃣ Supplier: $supplierId, Customer: $customerId\n";

// Open daily
$today = date('Y-m-d');
request('POST', '/daily/open', ['date' => $today]);
echo "4️⃣ Daily opened: $today\n";

// Create shipment with 10 cartons × 25 kg = 250 kg
$res = request('POST', '/shipments', [
    'supplier_id' => $supplierId,
    'number' => 'SHIP-NEW-' . time(),
    'date' => $today,
    'items' => [
        [
            'product_id' => $productId,
            'cartons' => 10,
            'weight_per_unit' => 25.0
        ]
    ]
]);
echo "5️⃣ Shipment created: " . ($res['code'] < 300 ? "✅ 250 kg" : "❌") . "\n";

// Test NEW API: Create invoice with new field names
echo "\n--- Testing NEW Invoice API ---\n";
echo "   Selling: 3 cartons, actual weight: 73 kg (expected: 75 kg)\n";
echo "   Expected wastage: 2 kg\n\n";

$res = request('POST', '/invoices', [
    'customer_id' => $customerId,
    'date' => $today,
    'items' => [
        [
            'product_id' => $productId,
            'cartons' => 3,              // عدد الكراتين
            'total_weight' => 73.0,      // الوزن الفعلي من الميزان
            'price' => 50.0              // سعر الكيلو
        ]
    ]
]);

if ($res['code'] >= 200 && $res['code'] < 300) {
    $invoice = $res['body']['data'];
    echo "6️⃣ Invoice created: ✅\n";
    echo "   - Invoice #: " . ($invoice['invoice_number'] ?? 'N/A') . "\n";
    echo "   - Total: " . ($invoice['total'] ?? 'N/A') . " (should be 73 × 50 = 3,650)\n";
} else {
    echo "6️⃣ Invoice creation: ❌\n";
    echo "   Error: " . ($res['body']['message_ar'] ?? $res['body']['message'] ?? $res['raw']) . "\n";
}

// Check stock
$res = request('GET', '/shipments/stock');
$stocks = $res['body']['data'] ?? [];
$remaining = 0;
foreach ($stocks as $s) {
    if (($s['product_id'] ?? null) == $productId) {
        $remaining = $s['remaining_quantity'] ?? 0;
        break;
    }
}
echo "7️⃣ Remaining stock: $remaining kg (should be 250 - 73 = 177 kg)\n";

echo "\n=== Test Complete ===\n";
