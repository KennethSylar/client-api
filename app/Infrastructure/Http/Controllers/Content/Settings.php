<?php

namespace App\Infrastructure\Http\Controllers\Content;

use App\Application\Core\Queries\GetSettingsQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Settings extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $settings = service('getSettingsHandler')->handle(new GetSettingsQuery());

        // Decode accreditations from JSON string to array
        if (isset($settings['accreditations'])) {
            $decoded = json_decode($settings['accreditations'], true);
            $settings['accreditations'] = is_array($decoded) ? $decoded : [];
        }

        return $this->json($settings);
    }
}
