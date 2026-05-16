<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\CreateAdminUserCommand;
use App\Application\Core\Commands\UpdateAdminUserCommand;
use App\Infrastructure\Http\Controllers\BaseController;
use App\Infrastructure\Http\Filters\AdminAuthContext;

class Users extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $users = service('adminUserRepository')->list();
        return $this->ok(['users' => $users]);
    }

    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $user = service('adminUserRepository')->findById($id);
        if ($user === null) {
            return $this->error('User not found.', 404);
        }
        return $this->ok(['user' => $user]);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body     = $this->jsonBody();
        $name     = trim($body['name'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $role     = $body['role'] ?? 'admin';

        if (empty($name)) {
            return $this->error('Name is required.', 400);
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email is required.', 400);
        }
        if (strlen($password) < 10) {
            return $this->error('Password must be at least 10 characters.', 400);
        }
        if (!in_array($role, ['admin', 'shop_admin'], true)) {
            return $this->error('Role must be admin or shop_admin.', 400);
        }

        try {
            $id = service('createAdminUserHandler')->handle(
                new CreateAdminUserCommand($name, $email, $password, $role)
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        $user = service('adminUserRepository')->findById($id);
        return $this->json(['user' => $user], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body      = $this->jsonBody();
        $name      = trim($body['name'] ?? '');
        $email     = trim($body['email'] ?? '');
        $role      = $body['role'] ?? '';
        $isActive  = isset($body['is_active']) ? (bool) $body['is_active'] : true;
        $password  = $body['password'] ?? null;

        if (empty($name)) {
            return $this->error('Name is required.', 400);
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email is required.', 400);
        }
        if (!in_array($role, ['admin', 'shop_admin'], true)) {
            return $this->error('Role must be admin or shop_admin.', 400);
        }
        if ($password !== null && $password !== '' && strlen($password) < 10) {
            return $this->error('Password must be at least 10 characters.', 400);
        }

        try {
            service('updateAdminUserHandler')->handle(
                new UpdateAdminUserCommand($id, $name, $email, $role, $isActive, $password ?: null)
            );
        } catch (\DomainException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 409;
            return $this->error($e->getMessage(), $code);
        }

        $user = service('adminUserRepository')->findById($id);
        return $this->ok(['user' => $user]);
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $requestingId = AdminAuthContext::userId();

        try {
            service('deleteAdminUserHandler')->handle($id, $requestingId);
        } catch (\DomainException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return $this->error($e->getMessage(), $code);
        }

        return $this->ok();
    }
}
