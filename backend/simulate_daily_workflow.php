<?php
/**
 * Daily Workflow API Simulation
 * Simulates a full business day via API calls
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$today = date('Y-m-d');

// Get auth token first
function apiCall($method, $endpoint, $data = null, $token = null)
{
    global $baseUrl;

    $ch = curl_init();
    $url = $baseUrl . $endpoint;

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "=== Daily Workflow API Simulation ===\n";
echo "Date: $today\n\n";

// 1. Login
echo "1. Logging in...\n";
$loginResponse = apiCall('POST', '/login', [
    'email' => 'admin@azafco.com',
    'password' => 'password'
]);

if ($loginResponse['status'] !== 200) {
    echo "   ❌ Login failed! Status: {$loginResponse['status']}\n";
    print_r($loginResponse['data']);
    exit(1);
}

$token = $loginResponse['data']['data']['token'] ?? $loginResponse['data']['token'] ?? null;
echo "   ✅ Logged in successfully\n";
echo "   Token: " . substr($token, 0, 20) . "...\n\n";

// 2. Open Daily Report
echo "2. Opening daily report for $today...\n";
$openDayResponse = apiCall('POST', '/daily-reports/open', ['date' => $today], $token);
echo "   Status: {$openDayResponse['status']}\n";
if ($openDayResponse['status'] == 200 || $openDayResponse['status'] == 201) {
    echo "   ✅ Day opened successfully\n\n";
} else {
    echo "   ⚠️ Response: " . json_encode($openDayResponse['data']) . "\n\n";
}

// 3. Get available products
echo "3. Fetching products...\n";
$productsResponse = apiCall('GET', '/products', null, $token);
$products = $productsResponse['data']['data'] ?? [];
echo "   ✅ Found " . count($products) . " products\n\n";

// 4. Get supplier
echo "4. Fetching suppliers...\n";
$suppliersResponse = apiCall('GET', '/suppliers', null, $token);
$suppliers = $suppliersResponse['data']['data'] ?? [];
$supplierId = $suppliers[0]['id'] ?? 1;
echo "   ✅ Using supplier ID: $supplierId\n\n";

// 5. Create Shipment
echo "5. Creating shipment...\n";
$shipmentItems = [];
foreach (array_slice($products, 0, 3) as $product) {
    $shipmentItems[] = [
        'product_id' => $product['id'],
        'cartons' => rand(50, 100),
        'weight_per_unit' => round(rand(20, 30) + rand(0, 99) / 100, 2),
        'unit_cost' => round(rand(100, 200) + rand(0, 99) / 100, 2),
    ];
}

$shipmentResponse = apiCall('POST', '/shipments', [
    'supplier_id' => $supplierId,
    'date' => $today,
    'notes' => 'شحنة تجريبية - API Simulation',
    'items' => $shipmentItems
], $token);

echo "   Status: {$shipmentResponse['status']}\n";
if ($shipmentResponse['status'] == 200 || $shipmentResponse['status'] == 201) {
    $shipmentId = $shipmentResponse['data']['data']['id'] ?? null;
    echo "   ✅ Shipment created (ID: $shipmentId)\n";
    echo "   Items: " . count($shipmentItems) . " products\n\n";
} else {
    echo "   ❌ Failed: " . json_encode($shipmentResponse['data']) . "\n\n";
}

// 6. Get customers
echo "6. Fetching customers...\n";
$customersResponse = apiCall('GET', '/customers', null, $token);
$customers = $customersResponse['data']['data'] ?? [];
echo "   ✅ Found " . count($customers) . " customers\n\n";

// 7. Create Invoice
echo "7. Creating invoice...\n";
$customerId = $customers[0]['id'] ?? 1;

// Get shipment items to get their IDs
$shipmentDetailResponse = apiCall('GET', "/shipments/$shipmentId", null, $token);
$shipmentItemsData = $shipmentDetailResponse['data']['data']['items'] ?? [];

$invoiceItems = [];
foreach (array_slice($shipmentItemsData, 0, 2) as $item) {
    $cartons = rand(3, 10);
    $weightPerUnit = $item['weight_per_unit'] ?? 25;
    $totalWeight = $cartons * $weightPerUnit * (rand(95, 100) / 100); // Slight wastage

    $invoiceItems[] = [
        'product_id' => $item['product_id'],
        'cartons' => $cartons,
        'total_weight' => round($totalWeight, 2),
        'price' => round(rand(40, 60) + rand(0, 99) / 100, 2), // Price per KG
    ];
}

$invoiceResponse = apiCall('POST', '/invoices', [
    'customer_id' => $customerId,
    'date' => $today,
    'items' => $invoiceItems,
    'discount' => 0,
    'notes' => 'فاتورة تجريبية'
], $token);

echo "   Status: {$invoiceResponse['status']}\n";
if ($invoiceResponse['status'] == 200 || $invoiceResponse['status'] == 201) {
    $invoiceId = $invoiceResponse['data']['data']['id'] ?? null;
    $invoiceTotal = $invoiceResponse['data']['data']['total'] ?? 0;
    echo "   ✅ Invoice created (ID: $invoiceId)\n";
    echo "   Total: $invoiceTotal AED\n\n";
} else {
    echo "   ❌ Failed: " . json_encode($invoiceResponse['data']) . "\n\n";
}

// 8. Create Collection
echo "8. Creating collection...\n";
$collectionAmount = round($invoiceTotal * 0.5, 2); // Pay 50%

$collectionResponse = apiCall('POST', '/collections', [
    'customer_id' => $customerId,
    'date' => $today,
    'amount' => $collectionAmount,
    'payment_method' => 'cash',
    'distribution_method' => 'oldest_first',
    'notes' => 'تحصيل جزئي - API Simulation'
], $token);

echo "   Status: {$collectionResponse['status']}\n";
if ($collectionResponse['status'] == 200 || $collectionResponse['status'] == 201) {
    $collectionId = $collectionResponse['data']['data']['id'] ?? null;
    echo "   ✅ Collection created (ID: $collectionId)\n";
    echo "   Amount: $collectionAmount AED\n\n";
} else {
    echo "   ❌ Failed: " . json_encode($collectionResponse['data']) . "\n\n";
}

// 9. Create Expense
echo "9. Creating expense...\n";
$expenseResponse = apiCall('POST', '/expenses', [
    'date' => $today,
    'amount' => rand(50, 200),
    'type' => 'company',
    'payment_method' => 'cash',
    'description' => 'مصروفات نقل - API Simulation'
], $token);

echo "   Status: {$expenseResponse['status']}\n";
if ($expenseResponse['status'] == 200 || $expenseResponse['status'] == 201) {
    echo "   ✅ Expense created\n\n";
} else {
    echo "   ⚠️ Response: " . json_encode($expenseResponse['data']) . "\n\n";
}

// 10. Get Daily Report
echo "10. Fetching daily report...\n";
$reportResponse = apiCall('GET', "/reports/daily/$today", null, $token);
echo "    Status: {$reportResponse['status']}\n";
if ($reportResponse['status'] == 200) {
    $report = $reportResponse['data']['data'] ?? [];
    echo "    ✅ Daily Report Summary:\n";
    echo "    - Sales: " . ($report['sales']['total'] ?? 0) . " AED\n";
    echo "    - Collections: " . ($report['collections']['total'] ?? 0) . " AED\n";
    echo "    - Expenses: " . ($report['expenses']['total'] ?? 0) . " AED\n\n";
}

// 11. Close Daily Report
echo "11. Closing daily report...\n";
$closeDayResponse = apiCall('POST', '/daily-reports/close', ['date' => $today], $token);
echo "    Status: {$closeDayResponse['status']}\n";
if ($closeDayResponse['status'] == 200 || $closeDayResponse['status'] == 201) {
    echo "    ✅ Day closed successfully\n\n";
} else {
    echo "    ⚠️ Response: " . json_encode($closeDayResponse['data']) . "\n\n";
}

echo "=== Workflow Complete ===\n";
echo "Summary:\n";
echo "- 1 Shipment with " . count($shipmentItems) . " products\n";
echo "- 1 Invoice (Total: $invoiceTotal AED)\n";
echo "- 1 Collection ($collectionAmount AED)\n";
echo "- 1 Expense\n";
