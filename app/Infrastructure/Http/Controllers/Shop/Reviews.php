<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Application\Shop\Commands\SubmitReviewCommand;
use App\Application\Shop\Queries\ListReviewsQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Reviews extends BaseController
{
    public function index(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(50, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        $result = service('listReviewsHandler')->handle(new ListReviewsQuery(
            productId: $productId,
            status:    'approved',
            page:      $page,
            perPage:   $perPage,
        ));

        return $this->ok([
            'reviews'    => array_map(fn($r) => $this->formatReview($r), $result->items),
            'pagination' => $result->meta(),
        ]);
    }

    public function store(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $token = $this->getBearerToken();
        if (!$token) {
            return $this->unauthorized('Authentication required.');
        }

        $customer = service('customerRepository')->findByToken($token);
        if (!$customer) {
            return $this->unauthorized('Session expired or invalid.');
        }

        $body = $this->jsonBody();

        try {
            $review = service('submitReviewHandler')->handle(new SubmitReviewCommand(
                customerId: $customer->id,
                productId:  $productId,
                rating:     (int) ($body['rating'] ?? 0),
                title:      trim($body['title'] ?? ''),
                body:       trim($body['body'] ?? ''),
            ));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->json(['review' => $this->formatReview($review)], 201);
    }

    private function formatReview(\App\Domain\Shop\Review $r): array
    {
        return [
            'id'            => $r->id,
            'rating'        => $r->rating,
            'title'         => $r->title,
            'body'          => $r->body,
            'customer_name' => $r->customerName,
            'created_at'    => $r->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    private function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }
}
