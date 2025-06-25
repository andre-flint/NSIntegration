<?php
require_once 'bootstrap.php';
require_once 'netsuite_api.php';
require_once 'output_helper.php';
set_time_limit(300);
$cacheFile = __DIR__ . '/cache/sales_orders.json';
$cacheTTL = 300; // 5 minutes

try {
    $nsApi = new NetSuiteAPI($accountId, $accessToken);

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $orders = json_decode(file_get_contents($cacheFile), true);
    } else {
        $orders = $nsApi->getAllSalesOrders(5);
        file_put_contents($cacheFile, json_encode($orders));
    }

    renderHTMLTable($orders, $nsApi);

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
