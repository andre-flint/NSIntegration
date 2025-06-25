<?php
// output_helper.php

function renderHTMLTable(array $orders, NetSuiteAPI $nsApi): void {
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
    <h2>Sales Orders</h2>
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

    if (empty($orders)) {
        echo "<tr><td colspan='6'>No orders found</td></tr>";
    } else {
        foreach ($orders as $order) {
            if (empty($order['links'][0]['href'])) {
                continue;
            }

            try {
                $orderDetails = $nsApi->getOrderDetails($order['links'][0]['href']);
                $lineItemsData = $nsApi->getOrderLineItems($orderDetails);
                $lineItems = $lineItemsData['items'] ?? [];
            } catch (Exception $e) {
                echo "<tr><td colspan='6'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                continue;
            }

            $customer = htmlspecialchars($orderDetails['custbody_ava_customercompanyname'] ?? 'N/A');
            $statusObj = $orderDetails['status'] ?? null;
            $status = 'N/A';
            if (is_array($statusObj)) {
                $status = htmlspecialchars($statusObj['refName'] ?? json_encode($statusObj));
            } elseif (is_string($statusObj)) {
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

            if (!empty($lineItems)) {
                foreach ($lineItems as $item) {
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
    }

    echo "</tbody></table></body></html>";
}
