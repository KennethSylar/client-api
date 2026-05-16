<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Http\Controllers\BaseController;
use CodeIgniter\Database\ConnectionInterface;

class Analytics extends BaseController
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * GET /admin/analytics/overview
     * Returns KPI summary cards: total revenue, order count, avg order value, new customers
     */
    public function overview(): \CodeIgniter\HTTP\ResponseInterface
    {
        $days = max(1, min(365, (int) ($this->request->getGet('days') ?? 30)));
        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $revenue = $this->db->query(
            "SELECT COALESCE(SUM(total_cents), 0) AS total_cents, COUNT(*) AS order_count
             FROM shop_orders
             WHERE status NOT IN ('cancelled','refunded') AND created_at >= ?",
            [$from]
        )->getRowArray();

        $customers = $this->db->query(
            "SELECT COUNT(*) AS new_count FROM shop_customers WHERE created_at >= ?",
            [$from]
        )->getRowArray();

        $orderCount = (int) $revenue['order_count'];

        return $this->ok([
            'revenue_cents'    => (int) $revenue['total_cents'],
            'order_count'      => $orderCount,
            'avg_order_cents'  => $orderCount > 0 ? (int) round($revenue['total_cents'] / $orderCount) : 0,
            'new_customers'    => (int) $customers['new_count'],
            'period_days'      => $days,
        ]);
    }

    /**
     * GET /admin/analytics/revenue
     * Daily revenue + order count for sparkline chart
     */
    public function revenue(): \CodeIgniter\HTTP\ResponseInterface
    {
        $days = max(1, min(365, (int) ($this->request->getGet('days') ?? 30)));
        $from = date('Y-m-d', strtotime("-{$days} days"));

        $rows = $this->db->query(
            "SELECT DATE(created_at) AS day,
                    COALESCE(SUM(total_cents), 0) AS revenue_cents,
                    COUNT(*) AS orders
             FROM shop_orders
             WHERE status NOT IN ('cancelled','refunded') AND DATE(created_at) >= ?
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [$from]
        )->getResultArray();

        return $this->ok(['data' => $rows]);
    }

    /**
     * GET /admin/analytics/orders-by-status
     * Breakdown of order counts per status
     */
    public function ordersByStatus(): \CodeIgniter\HTTP\ResponseInterface
    {
        $days = max(1, min(365, (int) ($this->request->getGet('days') ?? 30)));
        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $rows = $this->db->query(
            "SELECT status, COUNT(*) AS count
             FROM shop_orders WHERE created_at >= ?
             GROUP BY status ORDER BY count DESC",
            [$from]
        )->getResultArray();

        return $this->ok(['data' => $rows]);
    }

    /**
     * GET /admin/analytics/top-products
     * Top 10 products by revenue
     */
    public function topProducts(): \CodeIgniter\HTTP\ResponseInterface
    {
        $days = max(1, min(365, (int) ($this->request->getGet('days') ?? 30)));
        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $rows = $this->db->query(
            "SELECT oi.product_name,
                    SUM(oi.qty) AS units_sold,
                    SUM(oi.line_total_cents) AS revenue_cents
             FROM shop_order_items oi
             JOIN shop_orders o ON o.id = oi.order_id
             WHERE o.status NOT IN ('cancelled','refunded') AND o.created_at >= ?
             GROUP BY oi.product_name
             ORDER BY revenue_cents DESC
             LIMIT 10",
            [$from]
        )->getResultArray();

        return $this->ok(['data' => $rows]);
    }

    /**
     * GET /admin/analytics/export
     * Downloads a CSV report: overview summary + daily revenue + top products
     */
    public function export(): \CodeIgniter\HTTP\ResponseInterface
    {
        $days = max(1, min(365, (int) ($this->request->getGet('days') ?? 30)));
        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $overview = $this->db->query(
            "SELECT COALESCE(SUM(total_cents), 0) AS revenue_cents,
                    COALESCE(SUM(vat_cents), 0) AS vat_cents,
                    COALESCE(SUM(shipping_cents), 0) AS shipping_cents,
                    COUNT(*) AS order_count
             FROM shop_orders
             WHERE status NOT IN ('cancelled','refunded') AND created_at >= ?",
            [$from]
        )->getRowArray();

        $customers = $this->db->query(
            "SELECT COUNT(*) AS new_count FROM shop_customers WHERE created_at >= ?",
            [$from]
        )->getRowArray();

        $daily = $this->db->query(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total_cents), 0) AS revenue_cents
             FROM shop_orders
             WHERE status NOT IN ('cancelled','refunded') AND DATE(created_at) >= ?
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [$fromDate]
        )->getResultArray();

        $products = $this->db->query(
            "SELECT oi.product_name,
                    SUM(oi.qty) AS units_sold,
                    SUM(oi.line_total_cents) AS revenue_cents
             FROM shop_order_items oi
             JOIN shop_orders o ON o.id = oi.order_id
             WHERE o.status NOT IN ('cancelled','refunded') AND o.created_at >= ?
             GROUP BY oi.product_name
             ORDER BY revenue_cents DESC
             LIMIT 50",
            [$from]
        )->getResultArray();

        $out = fopen('php://temp', 'r+');

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Period',        "Last {$days} days"]);
        fputcsv($out, ['Revenue',       $this->fmt($overview['revenue_cents'])]);
        fputcsv($out, ['VAT',           $this->fmt($overview['vat_cents'])]);
        fputcsv($out, ['Shipping',      $this->fmt($overview['shipping_cents'])]);
        fputcsv($out, ['Orders',        (int) $overview['order_count']]);
        fputcsv($out, ['Avg Order',     $overview['order_count'] > 0 ? $this->fmt((int) round($overview['revenue_cents'] / $overview['order_count'])) : '0.00']);
        fputcsv($out, ['New Customers', (int) $customers['new_count']]);
        fputcsv($out, []);

        fputcsv($out, ['DAILY REVENUE']);
        fputcsv($out, ['Date', 'Orders', 'Revenue']);
        foreach ($daily as $row) {
            fputcsv($out, [$row['day'], $row['orders'], $this->fmt($row['revenue_cents'])]);
        }
        fputcsv($out, []);

        fputcsv($out, ['TOP PRODUCTS']);
        fputcsv($out, ['Product', 'Units Sold', 'Revenue']);
        foreach ($products as $row) {
            fputcsv($out, [$row['product_name'], $row['units_sold'], $this->fmt($row['revenue_cents'])]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $filename = 'analytics-' . date('Y-m-d') . "-{$days}d.csv";

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    private function fmt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
