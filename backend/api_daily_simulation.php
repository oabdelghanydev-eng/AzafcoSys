<?php
/**
 * Full Daily Workflow API Simulation
 * Mimics frontend behavior exactly via API calls
 * 
 * Flow: Login → Open Day → Create Supplier → Create Customers → 
 *       Create Shipments → Create Invoices → Create Collections → 
 *       Create Expenses → Close Day
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$today = '2025-12-20';

function api($method, $endpoint, $data = null, $token = null)
{
    global $baseUrl;

    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_filter([
            'Accept: application/json',
            'Content-Type: application/json',
            $token ? "Authorization: Bearer $token" : null,
        ]),
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'data' => json_decode($response, true)];
}

function printStep($num, $title)
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  STEP $num: $title\n";
    echo str_repeat('=', 60) . "\n";
}

function printResult($success, $message, $details = null)
{
    echo ($success ? "✅" : "❌") . " $message\n";
    if ($details)
        echo "   → $details\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     FULL DAILY WORKFLOW API SIMULATION                     ║\n";
echo "║     Date: $today                                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

// ═══════════════════════════════════════════════════════════════
// STEP 1: LOGIN
// ═══════════════════════════════════════════════════════════════
printStep(1, "تسجيل الدخول / Login");

$login = api('POST', '/login', [
    'email' => 'admin@azafco.com',
    'password' => 'password'
]);

if ($login['code'] !== 200) {
    die("❌ Login failed!\n");
}

$token = $login['data']['data']['token'] ?? $login['data']['token'];
printResult(true, "Logged in as admin", "Token: " . substr($token, 0, 20) . "...");

// ═══════════════════════════════════════════════════════════════
// STEP 2: OPEN DAILY REPORT
// ═══════════════════════════════════════════════════════════════
printStep(2, "فتح اليومية / Open Day");

$openDay = api('POST', '/daily-reports/open', ['date' => $today], $token);
printResult(
    $openDay['code'] == 200 || $openDay['code'] == 201,
    "Day opened for $today",
    json_encode($openDay['data']['message'] ?? 'OK')
);

// ═══════════════════════════════════════════════════════════════
// STEP 3: CREATE SUPPLIER
// ═══════════════════════════════════════════════════════════════
printStep(3, "إنشاء المورد / Create Supplier");

$supplier = api('POST', '/suppliers', [
    'name' => 'شركة الأسماك الطازجة',
    'name_en' => 'Fresh Fish Co.',
    'phone' => '0501234567',
    'email' => 'supplier@freshfish.com',
    'address' => 'دبي - ميناء راشد',
], $token);

$supplierId = $supplier['data']['data']['id'] ?? 1;
printResult($supplier['code'] == 201, "Supplier created", "ID: $supplierId");

// ═══════════════════════════════════════════════════════════════
// STEP 4: CREATE CUSTOMERS
// ═══════════════════════════════════════════════════════════════
printStep(4, "إنشاء العملاء / Create Customers");

$customers = [
    ['name' => 'مطعم السمك الذهبي', 'name_en' => 'Golden Fish Restaurant', 'phone' => '0551111111'],
    ['name' => 'سوبرماركت الخير', 'name_en' => 'Al Khair Supermarket', 'phone' => '0552222222'],
    ['name' => 'فندق البحر', 'name_en' => 'Sea Hotel', 'phone' => '0553333333'],
];

$customerIds = [];
foreach ($customers as $c) {
    $res = api('POST', '/customers', $c, $token);
    $id = $res['data']['data']['id'] ?? null;
    $customerIds[] = $id;
    printResult($res['code'] == 201, "Customer: {$c['name']}", "ID: $id");
}

// ═══════════════════════════════════════════════════════════════
// STEP 5: CREATE ACCOUNTS (for expenses)
// ═══════════════════════════════════════════════════════════════
printStep(5, "إنشاء الحسابات / Create Accounts");

$cashbox = api('POST', '/accounts', [
    'name' => 'الخزينة الرئيسية',
    'type' => 'cashbox',
    'balance' => 100000,
    'is_active' => true,
], $token);
printResult($cashbox['code'] == 201 || $cashbox['code'] == 200, "Cashbox account created", "Balance: 100,000 AED");

$bank = api('POST', '/accounts', [
    'name' => 'بنك الإمارات',
    'type' => 'bank',
    'balance' => 500000,
    'is_active' => true,
], $token);
printResult($bank['code'] == 201 || $bank['code'] == 200, "Bank account created", "Balance: 500,000 AED");

// ═══════════════════════════════════════════════════════════════
// STEP 6: GET PRODUCTS
// ═══════════════════════════════════════════════════════════════
printStep(6, "جلب المنتجات / Get Products");

$products = api('GET', '/products', null, $token);
$productList = $products['data']['data'] ?? [];
printResult(count($productList) > 0, "Found " . count($productList) . " products");

foreach (array_slice($productList, 0, 5) as $p) {
    echo "   - {$p['name']} (ID: {$p['id']})\n";
}

// ═══════════════════════════════════════════════════════════════
// STEP 7: CREATE SHIPMENT 1
// ═══════════════════════════════════════════════════════════════
printStep(7, "إنشاء الشحنة الأولى / Create Shipment 1");

$shipment1Items = [
    ['product_id' => $productList[0]['id'], 'cartons' => 100, 'weight_per_unit' => 25.5, 'unit_cost' => 150],
    ['product_id' => $productList[1]['id'], 'cartons' => 80, 'weight_per_unit' => 22.0, 'unit_cost' => 140],
    ['product_id' => $productList[2]['id'], 'cartons' => 60, 'weight_per_unit' => 28.0, 'unit_cost' => 160],
];

$shipment1 = api('POST', '/shipments', [
    'supplier_id' => $supplierId,
    'date' => $today,
    'notes' => 'الشحنة الصباحية',
    'items' => $shipment1Items,
], $token);

$shipment1Id = $shipment1['data']['data']['id'] ?? null;
printResult($shipment1['code'] == 201, "Shipment 1 created", "ID: $shipment1Id, Items: 3");

// ═══════════════════════════════════════════════════════════════
// STEP 8: CREATE SHIPMENT 2
// ═══════════════════════════════════════════════════════════════
printStep(8, "إنشاء الشحنة الثانية / Create Shipment 2");

$shipment2Items = [
    ['product_id' => $productList[3]['id'] ?? $productList[0]['id'], 'cartons' => 50, 'weight_per_unit' => 20.0, 'unit_cost' => 130],
    ['product_id' => $productList[4]['id'] ?? $productList[1]['id'], 'cartons' => 70, 'weight_per_unit' => 24.0, 'unit_cost' => 145],
];

$shipment2 = api('POST', '/shipments', [
    'supplier_id' => $supplierId,
    'date' => $today,
    'notes' => 'الشحنة المسائية',
    'items' => $shipment2Items,
], $token);

$shipment2Id = $shipment2['data']['data']['id'] ?? null;
printResult($shipment2['code'] == 201, "Shipment 2 created", "ID: $shipment2Id, Items: 2");

// ═══════════════════════════════════════════════════════════════
// STEP 9: CREATE INVOICES
// ═══════════════════════════════════════════════════════════════
printStep(9, "إنشاء فواتير المبيعات / Create Sales Invoices");

$invoices = [
    [
        'customer_id' => $customerIds[0],
        'items' => [
            ['product_id' => $productList[0]['id'], 'cartons' => 10, 'total_weight' => 240, 'price' => 55],
            ['product_id' => $productList[1]['id'], 'cartons' => 5, 'total_weight' => 105, 'price' => 52],
        ]
    ],
    [
        'customer_id' => $customerIds[1],
        'items' => [
            ['product_id' => $productList[2]['id'], 'cartons' => 8, 'total_weight' => 220, 'price' => 58],
        ]
    ],
    [
        'customer_id' => $customerIds[2],
        'items' => [
            ['product_id' => $productList[0]['id'], 'cartons' => 15, 'total_weight' => 360, 'price' => 54],
            ['product_id' => $productList[1]['id'], 'cartons' => 10, 'total_weight' => 215, 'price' => 51],
        ]
    ],
    [
        'customer_id' => $customerIds[0],
        'items' => [
            ['product_id' => $productList[3]['id'] ?? $productList[0]['id'], 'cartons' => 6, 'total_weight' => 115, 'price' => 48],
        ]
    ],
];

$invoiceIds = [];
$totalSales = 0;
foreach ($invoices as $i => $inv) {
    $res = api('POST', '/invoices', array_merge($inv, ['date' => $today, 'discount' => 0]), $token);
    $id = $res['data']['data']['id'] ?? null;
    $total = $res['data']['data']['total'] ?? 0;
    $invoiceIds[] = $id;
    $totalSales += $total;
    printResult($res['code'] == 201, "Invoice " . ($i + 1) . " for Customer #{$inv['customer_id']}", "ID: $id, Total: $total AED");
}

echo "\n   📊 Total Sales: $totalSales AED\n";

// ═══════════════════════════════════════════════════════════════
// STEP 10: CREATE COLLECTIONS
// ═══════════════════════════════════════════════════════════════
printStep(10, "إنشاء التحصيلات / Create Collections");

$collections = [
    ['customer_id' => $customerIds[0], 'amount' => 10000, 'payment_method' => 'cash'],
    ['customer_id' => $customerIds[1], 'amount' => 5000, 'payment_method' => 'bank'],
    ['customer_id' => $customerIds[2], 'amount' => 15000, 'payment_method' => 'cash'],
    ['customer_id' => $customerIds[0], 'amount' => 3000, 'payment_method' => 'cash'],
];

$totalCollections = 0;
foreach ($collections as $i => $col) {
    $res = api('POST', '/collections', array_merge($col, [
        'date' => $today,
        'distribution_method' => 'oldest_first',
        'notes' => 'تحصيل يومي',
    ]), $token);
    $id = $res['data']['data']['id'] ?? null;
    $totalCollections += $col['amount'];
    printResult($res['code'] == 201, "Collection " . ($i + 1) . " ({$col['payment_method']})", "Amount: {$col['amount']} AED");
}

echo "\n   📊 Total Collections: $totalCollections AED\n";

// ═══════════════════════════════════════════════════════════════
// STEP 11: CREATE EXPENSES
// ═══════════════════════════════════════════════════════════════
printStep(11, "إنشاء المصروفات / Create Expenses");

$expenses = [
    ['amount' => 500, 'type' => 'company', 'category' => 'transport', 'description' => 'مصروفات نقل الشحنات', 'payment_method' => 'cash'],
    ['amount' => 200, 'type' => 'company', 'category' => 'utilities', 'description' => 'فاتورة كهرباء', 'payment_method' => 'bank'],
    ['amount' => 1000, 'type' => 'supplier', 'category' => 'payment', 'description' => 'دفعة للمورد', 'payment_method' => 'cash', 'supplier_id' => $supplierId],
    ['amount' => 150, 'type' => 'company', 'category' => 'office', 'description' => 'مستلزمات مكتبية', 'payment_method' => 'cash'],
];

$totalExpenses = 0;
foreach ($expenses as $i => $exp) {
    $res = api('POST', '/expenses', array_merge($exp, ['date' => $today]), $token);
    $id = $res['data']['data']['id'] ?? null;
    $totalExpenses += $exp['amount'];
    printResult($res['code'] == 201, "{$exp['description']}", "Amount: {$exp['amount']} AED ({$exp['payment_method']})");
}

echo "\n   📊 Total Expenses: $totalExpenses AED\n";

// ═══════════════════════════════════════════════════════════════
// STEP 12: CLOSE DAILY REPORT
// ═══════════════════════════════════════════════════════════════
printStep(12, "إغلاق اليومية / Close Day");

$closeDay = api('POST', '/daily-reports/close', ['date' => $today], $token);
printResult(
    $closeDay['code'] == 200 || $closeDay['code'] == 201,
    "Day closed for $today",
    json_encode($closeDay['data']['message'] ?? 'OK')
);

// ═══════════════════════════════════════════════════════════════
// FINAL SUMMARY
// ═══════════════════════════════════════════════════════════════
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    DAILY SUMMARY                           ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  📦 Shipments:    2 (Total: %d cartons)                    ║\n", 100 + 80 + 60 + 50 + 70);
printf("║  📄 Invoices:     %d (Total: %s AED)              ║\n", count($invoiceIds), number_format($totalSales, 2));
printf("║  💰 Collections:  %d (Total: %s AED)                ║\n", count($collections), number_format($totalCollections, 2));
printf("║  💸 Expenses:     %d (Total: %s AED)                 ║\n", count($expenses), number_format($totalExpenses, 2));
echo "╚════════════════════════════════════════════════════════════╝\n";
