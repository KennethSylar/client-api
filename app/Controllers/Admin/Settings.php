<?php

namespace App\Controllers\Admin;

use App\Application\Core\Commands\UpdateSettingsCommand;
use App\Controllers\BaseController;

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
