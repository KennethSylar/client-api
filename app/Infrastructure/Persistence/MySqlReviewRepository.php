<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shop\Review;
use App\Domain\Shop\ReviewRepositoryInterface;
use App\Domain\Shop\ReviewStatus;
use App\Domain\Shared\PaginatedResult;

class MySqlReviewRepository extends AbstractMysqlRepository implements ReviewRepositoryInterface
{
    public function findById(int $id): ?Review
    {
        $row = $this->db->query("
            SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, p.name AS product_name
            FROM shop_product_reviews r
            JOIN shop_customers c ON c.id = r.customer_id
            JOIN shop_products  p ON p.id = r.product_id
            WHERE r.id = ?
        ", [$id])->getRowArray();

        return $row ? Review::fromArray($row) : null;
    }

    public function findByCustomerAndProduct(int $customerId, int $productId): ?Review
    {
        $row = $this->db->table('shop_product_reviews')
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->get()->getRowArray();

        return $row ? Review::fromArray($row) : null;
    }

    public function findVerifiedPurchaseOrderId(int $customerId, int $productId): ?int
    {
        $row = $this->db->query("
            SELECT o.id FROM shop_orders o
            JOIN shop_order_items oi ON oi.order_id = o.id
            WHERE o.customer_id = ?
              AND oi.product_id  = ?
              AND o.status IN ('paid','processing','shipped','delivered','refunded','partially_refunded')
            LIMIT 1
        ", [$customerId, $productId])->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    public function findByProduct(int $productId, string $status, int $page, int $perPage): PaginatedResult
    {
        $builder = $this->db->table('shop_product_reviews r')
            ->select('r.*, CONCAT(c.first_name, \' \', c.last_name) AS customer_name')
            ->join('shop_customers c', 'c.id = r.customer_id')
            ->where('r.product_id', $productId)
            ->where('r.status', $status)
            ->orderBy('r.created_at', 'DESC');

        $result = $this->paginate($builder, $page, $perPage);

        return new PaginatedResult(
            items:   array_map(fn($r) => Review::fromArray($r), $result->items),
            total:   $result->total,
            page:    $result->page,
            perPage: $result->perPage,
        );
    }

    public function findPaginated(string $status, int $page, int $perPage): PaginatedResult
    {
        $builder = $this->db->table('shop_product_reviews r')
            ->select('r.*, CONCAT(c.first_name, \' \', c.last_name) AS customer_name, p.name AS product_name')
            ->join('shop_customers c', 'c.id = r.customer_id')
            ->join('shop_products  p', 'p.id = r.product_id')
            ->orderBy('r.created_at', 'DESC');

        if ($status !== '') {
            $builder->where('r.status', $status);
        }

        $result = $this->paginate($builder, $page, $perPage);

        return new PaginatedResult(
            items:   array_map(fn($r) => Review::fromArray($r), $result->items),
            total:   $result->total,
            page:    $result->page,
            perPage: $result->perPage,
        );
    }

    public function save(Review $review): Review
    {
        $this->db->table('shop_product_reviews')->insert([
            'product_id'  => $review->productId,
            'customer_id' => $review->customerId,
            'order_id'    => $review->orderId,
            'rating'      => $review->rating,
            'title'       => $review->title,
            'body'        => $review->body,
            'status'      => $review->status->value,
            'admin_note'  => $review->adminNote,
            'created_at'  => $this->now(),
            'updated_at'  => $this->now(),
        ]);

        return $this->findById((int) $this->db->insertID());
    }

    public function updateStatus(int $id, ReviewStatus $status, ?string $adminNote): void
    {
        $this->db->table('shop_product_reviews')->where('id', $id)->update([
            'status'     => $status->value,
            'admin_note' => $adminNote,
            'updated_at' => $this->now(),
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->table('shop_product_reviews')->where('id', $id)->delete();
    }
}
