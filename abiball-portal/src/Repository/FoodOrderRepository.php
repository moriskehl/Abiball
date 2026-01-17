<?php
declare(strict_types=1);

// src/Repository/FoodOrderRepository.php
require_once __DIR__ . '/../Config.php';

final class FoodOrderRepository
{
    private static string $csvPath = __DIR__ . '/../../storage/data/food_orders.csv';
    private static string $lockPath = __DIR__ . '/../../storage/data/food_orders.csv.lock';
    
    /**
     * Acquire exclusive lock for write operations
     */
    private static function acquireLock(): mixed
    {
        $lockFile = fopen(self::$lockPath, 'c');
        if ($lockFile === false) {
            throw new RuntimeException('Cannot create lock file');
        }
        
        // Wait up to 5 seconds to acquire lock
        $maxWait = 50; // 50 x 100ms = 5s
        $waited = 0;
        while (!flock($lockFile, LOCK_EX | LOCK_NB)) {
            if ($waited >= $maxWait) {
                fclose($lockFile);
                throw new RuntimeException('Could not acquire lock after 5 seconds');
            }
            usleep(100000); // 100ms
            $waited++;
        }
        
        return $lockFile;
    }
    
    /**
     * Release lock
     */
    private static function releaseLock($lockFile): void
    {
        if ($lockFile) {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    /**
     * Create a new order with multiple items (one row per item)
     */
    public static function create(string $mainId, array $items, float $totalPrice): string
    {
        $lockFile = self::acquireLock();
        
        try {
            $orderId = self::generateOrderIdUnsafe();
            $now = date('Y-m-d H:i:s');

            // Check if file exists and is empty (needs header)
            $needsHeader = !is_file(self::$csvPath) || filesize(self::$csvPath) === 0;

            $fp = fopen(self::$csvPath, 'a');
            if ($fp === false) {
                throw new RuntimeException('Cannot write to food_orders.csv');
            }

            // Add UTF-8 BOM and header if file is new/empty
            if ($needsHeader) {
                fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
                fputcsv($fp, [
                    'OrderID', 'MainID', 'ItemID', 'ItemName', 'Price', 'Quantity', 'Subtotal',
                    'TotalPrice', 'PaidAmount', 'Status', 'CreatedAt', 'UpdatedAt', 'RedeemedAt', 'RedeemedBy'
                ]);
            }

            // Write one row per item
            foreach ($items as $item) {
                $row = [
                    $orderId,
                    $mainId,
                    $item['item_id'],
                    $item['name'],
                    number_format((float)$item['price'], 2, '.', ''),
                    (int)$item['quantity'],
                    number_format((float)$item['subtotal'], 2, '.', ''),
                    number_format($totalPrice, 2, '.', ''),
                    '0.00',
                    'open',
                    $now,
                    $now,
                    '',
                    ''
                ];
                fputcsv($fp, $row);
            }

            fclose($fp);
            return $orderId;
        } finally {
            self::releaseLock($lockFile);
        }
    }

    /**
     * Find an order by OrderID (returns grouped order with items array)
     */
    public static function findByOrderId(string $orderId): ?array
    {
        $all = self::getAllOrders();
        foreach ($all as $order) {
            if ($order['order_id'] === $orderId) {
                return $order;
            }
        }
        return null;
    }

    /**
     * Find all orders for a MainID
     */
    public static function findByMainId(string $mainId): array
    {
        $all = self::getAllOrders();
        return array_values(array_filter($all, fn($o) => $o['main_id'] === $mainId));
    }

    /**
     * Get all orders grouped by OrderID
     */
    public static function getAllOrders(): array
    {
        $rows = self::readAllRows();
        return self::groupRowsByOrderId($rows);
    }

    /**
     * Read all raw rows from CSV
     */
    private static function readAllRows(): array
    {
        if (!is_file(self::$csvPath)) {
            return [];
        }

        $fp = fopen(self::$csvPath, 'r');
        if ($fp === false) {
            return [];
        }

        // Skip UTF-8 BOM if present
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $rows = [];
        $header = fgetcsv($fp);
        if ($header === false) {
            fclose($fp);
            return [];
        }

        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) < 14) continue;

            $rows[] = [
                'order_id' => $row[0],
                'main_id' => $row[1],
                'item_id' => $row[2],
                'item_name' => $row[3],
                'price' => (float)$row[4],
                'quantity' => (int)$row[5],
                'subtotal' => (float)$row[6],
                'total_price' => (float)$row[7],
                'paid_amount' => (float)$row[8],
                'status' => $row[9],
                'created_at' => $row[10],
                'updated_at' => $row[11],
                'redeemed_at' => $row[12],
                'redeemed_by' => $row[13]
            ];
        }

        fclose($fp);
        return $rows;
    }

    /**
     * Group rows by OrderID into order objects with items array
     */
    private static function groupRowsByOrderId(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $orderId = $row['order_id'];

            if (!isset($grouped[$orderId])) {
                $grouped[$orderId] = [
                    'order_id' => $orderId,
                    'main_id' => $row['main_id'],
                    'items' => [],
                    'total_price' => $row['total_price'],
                    'paid_amount' => $row['paid_amount'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'redeemed_at' => $row['redeemed_at'],
                    'redeemed_by' => $row['redeemed_by']
                ];
            }

            $grouped[$orderId]['items'][] = [
                'item_id' => $row['item_id'],
                'name' => $row['item_name'],
                'price' => $row['price'],
                'quantity' => $row['quantity'],
                'subtotal' => $row['subtotal']
            ];
        }

        return array_values($grouped);
    }

    /**
     * Update paid amount for an order
     */
    public static function updatePaidAmount(string $orderId, float $amount): bool
    {
        return self::updateOrderField($orderId, 'paid_amount', $amount);
    }

    /**
     * Update status for an order
     */
    public static function updateStatus(string $orderId, string $status): bool
    {
        return self::updateOrderField($orderId, 'status', $status);
    }

    /**
     * Redeem an order
     */
    public static function redeem(string $orderId, string $helperId): bool
    {
        $lockFile = self::acquireLock();
        
        try {
            $rows = self::readAllRows();
            $found = false;
            $now = date('Y-m-d H:i:s');

            foreach ($rows as &$row) {
                if ($row['order_id'] === $orderId) {
                    $row['status'] = 'redeemed';
                    $row['redeemed_at'] = $now;
                    $row['redeemed_by'] = $helperId;
                    $row['updated_at'] = $now;
                    $found = true;
                }
            }

            if (!$found) {
                return false;
            }

            return self::writeAllRowsUnsafe($rows);
        } finally {
            self::releaseLock($lockFile);
        }
    }

    /**
     * Cancel an order
     */
    public static function cancel(string $orderId): bool
    {
        return self::updateStatus($orderId, 'cancelled');
    }

    /**
     * Get statistics about orders
     */
    public static function getStatistics(): array
    {
        $orders = self::getAllOrders();

        $stats = [
            'open' => 0,
            'paid' => 0,
            'redeemed' => 0,
            'cancelled' => 0,
            'total_open' => 0.0,
            'total_paid' => 0.0,
            'total_redeemed' => 0.0,
            'total_cancelled' => 0.0
        ];

        foreach ($orders as $order) {
            $status = $order['status'];
            if (isset($stats[$status])) {
                $stats[$status]++;
                $stats['total_' . $status] += $order['total_price'];
            }
        }

        return $stats;
    }

    /**
     * Update a field for all rows of an order
     */
    private static function updateOrderField(string $orderId, string $field, $value): bool
    {
        $lockFile = self::acquireLock();
        
        try {
            $rows = self::readAllRows();
            $found = false;
            $now = date('Y-m-d H:i:s');

            foreach ($rows as &$row) {
                if ($row['order_id'] === $orderId) {
                    $row[$field] = $value;
                    $row['updated_at'] = $now;
                    $found = true;
                }
            }

            if (!$found) {
                return false;
            }

            return self::writeAllRowsUnsafe($rows);
        } finally {
            self::releaseLock($lockFile);
        }
    }

    /**
     * Write all rows back to CSV (unsafe - caller must hold lock)
     */
    private static function writeAllRowsUnsafe(array $rows): bool
    {
        $fp = fopen(self::$csvPath, 'w');
        if ($fp === false) {
            return false;
        }

        // Write UTF-8 BOM
        fwrite($fp, "\xEF\xBB\xBF");

        // Header
        fputcsv($fp, [
            'OrderID', 'MainID', 'ItemID', 'ItemName', 'Price', 'Quantity', 'Subtotal',
            'TotalPrice', 'PaidAmount', 'Status', 'CreatedAt', 'UpdatedAt', 'RedeemedAt', 'RedeemedBy'
        ]);

        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['order_id'],
                $row['main_id'],
                $row['item_id'],
                $row['item_name'],
                number_format($row['price'], 2, '.', ''),
                $row['quantity'],
                number_format($row['subtotal'], 2, '.', ''),
                number_format($row['total_price'], 2, '.', ''),
                is_numeric($row['paid_amount']) ? number_format((float)$row['paid_amount'], 2, '.', '') : $row['paid_amount'],
                $row['status'],
                $row['created_at'],
                $row['updated_at'],
                $row['redeemed_at'] ?? '',
                $row['redeemed_by'] ?? ''
            ]);
        }

        fclose($fp);
        return true;
    }

    /**
     * Write all rows back to CSV (public, acquires lock)
     */
    private static function writeAllRows(array $rows): bool
    {
        $lockFile = self::acquireLock();
        try {
            return self::writeAllRowsUnsafe($rows);
        } finally {
            self::releaseLock($lockFile);
        }
    }

    /**
     * Generate next OrderID (unsafe - caller must hold lock)
     */
    private static function generateOrderIdUnsafe(): string
    {
        $rows = self::readAllRows();
        $maxNum = 0;

        $seenOrderIds = [];
        foreach ($rows as $row) {
            if (isset($seenOrderIds[$row['order_id']])) continue;
            $seenOrderIds[$row['order_id']] = true;

            if (preg_match('/^FOOD(\d+)$/', $row['order_id'], $matches)) {
                $maxNum = max($maxNum, (int)$matches[1]);
            }
        }

        return 'FOOD' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate next OrderID (public, acquires lock)
     */
    private static function generateOrderId(): string
    {
        $lockFile = self::acquireLock();
        try {
            return self::generateOrderIdUnsafe();
        } finally {
            self::releaseLock($lockFile);
        }
    }
}
