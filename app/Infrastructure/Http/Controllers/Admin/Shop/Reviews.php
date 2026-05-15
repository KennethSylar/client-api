<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Shop\Commands\ModerateReviewCommand;
use App\Application\Shop\Queries\ListReviewsQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Reviews extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $status  = $this->request->getGet('status') ?? '';
        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 25)));

        $result = service('listReviewsHandler')->handle(new ListReviewsQuery(
            productId: null,
            status:    $status,
            page:      $page,
            perPage:   $perPage,
        ));

        return $this->ok([
            'reviews'    => array_map(fn($r) => $r->toArray(), $result->items),
            'pagination' => $result->meta(),
        ]);
    }

    public function moderate(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        try {
            service('moderateReviewHandler')->handle(new ModerateReviewCommand(
                reviewId:  $id,
                status:    $body['status']     ?? '',
                adminNote: $body['admin_note'] ?? null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok();
    }

    public function destroy(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        service('reviewRepository')->delete($id);
        return $this->ok();
    }
}
