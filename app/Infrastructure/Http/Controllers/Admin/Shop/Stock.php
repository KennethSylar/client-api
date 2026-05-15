<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Shop\Commands\AdjustStockCommand;
use App\Application\Shop\Queries\GetStockHistoryQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Stock extends BaseController
{
    public function adjust(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $body      = $this->jsonBody();
        $mode      = $body['mode'] ?? 'adjust';
        $variantId = isset($body['variant_id']) ? (int) $body['variant_id'] : null;
        $note      = trim($body['note'] ?? '');

        try {
            $result = service('adjustStockHandler')->handle(new AdjustStockCommand(
                productId: $productId,
                variantId: $variantId,
                mode:      $mode,
                delta:     isset($body['delta']) ? (int) $body['delta'] : 0,
                qty:       isset($body['qty'])   ? (int) $body['qty']   : null,
                note:      $note,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok($result);
    }

    public function history(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $adjustments = service('getStockHistoryHandler')->handle(new GetStockHistoryQuery(
                productId: $productId,
                limit:     100,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok(['adjustments' => $adjustments]);
    }
}
