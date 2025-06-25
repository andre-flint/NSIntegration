<?php

function exportOrdersToCSV(array $orders, NetSuiteAPI $nsApi, string $filePath): void {
    $fp = fopen($filePath, 'w');
    if ($fp === false) {
        throw new Exception("Unable to open CSV file for writing: $filePath");
    }

    // CSV Header
    fputcsv($fp, ['Order ID', 'Document Number', 'Date', 'Customer', 'Status', 'Total', 'Item Name', 'Quantity', 'Rate']);

    if (!empty($orders['items'])) {
        foreach ($orders['items'] as $order) {
            if (!isset($order['links'][0]['href'])) continue;

            try {
                $orderDetails = $nsApi->getOrderDetails($order['links'][0]['href']);
            } catch (Exception $e) {
                // Log error or skip
                continue;
            }

            $orderId = $orderDetails['id'] ?? '';
            $docNum = $orderDetails['tranId'] ?? '';
            $date = $orderDetails['tranDate'] ?? '';
            $customer = $orderDetails['custbody_ava_customercompanyname'] ?? '';
            $statusObj = $orderDetails['status'] ?? null;
            $status = '';
            if (is_array($statusObj)) {
                $status = $statusObj['refName'] ?? json_encode($statusObj);
            } else if (is_string($statusObj)) {
                $status = $statusObj;
            }
            $total = $orderDetails['total'] ?? 0;

            if (!empty($orderDetails['item']) && is_array($orderDetails['item'])) {
                $items = $orderDetails['item']['items'] ?? $orderDetails['item'];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $itemName = $item['item']['name'] ?? '';
                        $qty = $item['quantity'] ?? '';
                        $rate = $item['rate'] ?? '';
                        fputcsv($fp, [$orderId, $docNum, $date, $customer, $status, $total, $itemName, $qty, $rate]);
                    }
                } else {
                    // no items
                    fputcsv($fp, [$orderId, $docNum, $date, $customer, $status, $total, '', '', '']);
                }
            } else {
                fputcsv($fp, [$orderId, $docNum, $date, $customer, $status, $total, '', '', '']);
            }
        }
    }

    fclose($fp);
}
