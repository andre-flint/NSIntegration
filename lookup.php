<?php
set_time_limit(300); 
$cacheFile = __DIR__ . '/cache/sales_orders.json';
$cacheTTL = 300; // 5 minutes

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $json = file_get_contents($cacheFile);
    $orders = json_decode($json, true);
} else {
    // Pull fresh from NetSuite
    $orders = $netsuite->getSalesOrders(5);

    // Save to cache
    file_put_contents($cacheFile, json_encode($orders));
}


require_once __DIR__ . '/token_manager.php';
require_once __DIR__ . '/netsuite_api.php';
require_once __DIR__ . '/output_helper.php';

// --- CONFIG ---
$clientId = '0d04b387a72628cb3408e18a7dc1b4644eaba3b7008daecd7ffe7533ac8acbb2';
$clientSecret = 'f0ebe7656a671cf92078e90faaffce77efda6cb77bd691d303a196d82406b8ee';
$redirectUri = 'http://localhost';
$accountId = '9245359-sb1';

// Put your authorization code here ONLY for the first run, then clear it out
$authorizationCode = ''; 

try {
    // Initialize Token Manager and get access token
    $tokenManager = new TokenManager($clientId, $clientSecret, $redirectUri, $accountId);
    $accessToken = $tokenManager->getAccessToken($authorizationCode);

    // Initialize API with access token
    $nsApi = new NetSuiteAPI($accountId, $accessToken);

    // Fetch last 5 sales orders
    $orders = $nsApi->getAllSalesOrders(5);



    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8" />
    <title>Sales Orders</title>
    <style>
      body { font-family: Arial, sans-serif; padding: 20px; }
      table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
      th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
      th { background-color: #f4f4f4; }
      .line-items-table { margin-top: 10px; width: 95%; }
      .line-items-table th, .line-items-table td { font-size: 0.9em; }
    </style>
    </head>
    <body>
    <h2>Last 5 Sales Orders</h2>
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Document Number</th>
          <th>Date</th>
          <th>Customer</th>
          <th>Status</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
    HTML;
    
    foreach ($orders['items'] as $order) {
        if (!isset($order['links'][0]['href'])) continue;
        $orderDetails = $nsApi->getOrderDetails($order['links'][0]['href']);
    
        $customer = htmlspecialchars($orderDetails['custbody_ava_customercompanyname'] ?? 'N/A');
        $statusObj = $orderDetails['status'] ?? null;
        $status = 'N/A';
        if (is_array($statusObj)) {
            $status = htmlspecialchars($statusObj['refName'] ?? json_encode($statusObj));
        } else if (is_string($statusObj)) {
            $status = htmlspecialchars($statusObj);
        }
        $total = number_format($orderDetails['total'] ?? 0, 2);
    
        echo "<tr>";
        echo "<td>" . htmlspecialchars($orderDetails['id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($orderDetails['tranId'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($orderDetails['tranDate'] ?? 'N/A') . "</td>";
        echo "<td>$customer</td>";
        echo "<td>$status</td>";
        echo "<td>\$$total</td>";
        echo "</tr>";
    
        // Line items table
        echo "<tr><td colspan='6'>";
        echo "<table class='line-items-table'>";
        echo "<thead><tr><th>Item Name</th><th>Quantity</th><th>Rate</th></tr></thead><tbody>";
    
        if (!empty($orderDetails['item']['items']) && is_array($orderDetails['item']['items'])) {
            foreach ($orderDetails['item']['items'] as $item) {
                $name = htmlspecialchars($item['item']['name'] ?? 'Unknown');
                $qty = htmlspecialchars($item['quantity'] ?? 'N/A');
                $rate = number_format($item['rate'] ?? 0, 2);
    
                echo "<tr><td>$name</td><td>$qty</td><td>\$$rate</td></tr>";
            }
        } else {
            echo "<tr><td colspan='3'>No items found</td></tr>";
        }
        echo "</tbody></table>";
        echo "</td></tr>";
    }
    
    echo <<<HTML
      </tbody>
    </table>
    </body>
    </html>
    HTML;
    

    // Export CSV file
    $exportDir = __DIR__ . '/exports';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0700, true);
    }
    $csvFile = $exportDir . '/sales_orders_export_' . date('Ymd_His') . '.csv';

    exportOrdersToCSV($orders, $nsApi, $csvFile);
    echo "CSV export saved to $csvFile\n";

} catch (Exception $ex) {
    echo "Error: " . $ex->getMessage() . "\n";
}
