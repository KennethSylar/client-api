<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\UpdateSettingsCommand;
use App\Infrastructure\Http\Controllers\BaseController;

class Settings extends BaseController
{
    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body)) {
            return $this->error('No data provided.', 400);
        }

        service('updateSettingsHandler')->handle(new UpdateSettingsCommand($body));

        return $this->ok();
    }
}
