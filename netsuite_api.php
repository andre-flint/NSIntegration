<?php
class NetSuiteAPI {
    private $accountId;
    private $accessToken;
    private $cacheDir;
    private $cacheTtl; // time-to-live in seconds
    private $cacheFileSalesOrders;

    public function __construct(string $accountId, string $accessToken, string $cacheDir = __DIR__ . '/cache', int $cacheTtl = 3600) {
        $this->accountId = $accountId;
        $this->accessToken = $accessToken;
        $this->cacheDir = $cacheDir;
        $this->cacheTtl = $cacheTtl;
        $this->cacheFileSalesOrders = $this->cacheDir . '/sales_orders.json';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Make a GET request to the given URL.
     *
     * @param string $url
     * @return array Parsed JSON response
     * @throws Exception on HTTP or cURL error
     */
    private function callApi(string $url): array {
        $headers = [
            "Authorization: Bearer {$this->accessToken}",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Sandbox only
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("API call error: " . curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("API request failed. HTTP Code: $http_code Response: $response");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new Exception("Failed to decode JSON response: $response");
        }

        return $decoded;
    }

    /**
     * Get all sales orders combined into one array, cached in one JSON file.
     *
     * @param int $pageSize Number of records per API call (max limit)
     * @return array Combined list of all sales orders.
     */
    public function getAllSalesOrders(int $pageSize = 100): array {
        // Use cache if available and fresh
        if (file_exists($this->cacheFileSalesOrders) && (time() - filemtime($this->cacheFileSalesOrders)) < $this->cacheTtl) {
            $cached = file_get_contents($this->cacheFileSalesOrders);
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $allOrders = [];
        $offset = 0;

        while (true) {
            $url = "https://{$this->accountId}.suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?limit={$pageSize}&offset={$offset}";
            $response = $this->callApi($url);

            // Adjust below line depending on your actual response structure:
            // Some NetSuite REST API responses put data under 'items', 'data', or directly in root.
            $orders = $response['items'] ?? $response['data'] ?? [];

            if (empty($orders)) {
                break; // No more orders
            }

            $allOrders = array_merge($allOrders, $orders);

            if (count($orders) < $pageSize) {
                break; // Last page
            }

            $offset += $pageSize;
        }

        // Cache the combined orders
        file_put_contents($this->cacheFileSalesOrders, json_encode($allOrders));

        return $allOrders;
    }

    /**
     * Example: Get details of a specific order by URL
     *
     * @param string $orderUrl
     * @return array
     * @throws Exception
     */
    public function getOrderDetails(string $orderUrl): array {
        return $this->callApi($orderUrl);
    }

    /**
     * Example: Get line items from an order details array
     *
     * @param array $orderDetails
     * @return array
     * @throws Exception
     */
    public function getOrderLineItems(array $orderDetails): array {
        if (empty($orderDetails['item']['links'][0]['href'])) {
            throw new Exception("No item link found in order details.");
        }

        $itemUrl = $orderDetails['item']['links'][0]['href'];
        return $this->callApi($itemUrl);
    }
}
