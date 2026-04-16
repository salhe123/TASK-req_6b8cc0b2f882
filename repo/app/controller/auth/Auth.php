<?php
declare(strict_types=1);

namespace app\controller\auth;

use app\BaseController;
use app\service\AuthService;
use think\exception\ValidateException;

class Auth extends BaseController
{
    public function login()
    {
        $data = $this->request->getInput();
        $data = json_decode($data, true) ?: [];

        if (empty($data['username']) || empty($data['password'])) {
            return json_error('Username and password are required', 400);
        }

        try {
            $result = AuthService::login(
                $data['username'],
                $data['password'],
                $data['fingerprint'] ?? null
            );
            return json_success($result, 'Login successful');
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 401);
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        }
    }

    public function logout()
    {
        AuthService::logout();
        return json_success(['message' => 'Logged out']);
    }

    public function changePassword()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['oldPassword']) || empty($data['newPassword'])) {
            return json_error('Old password and new password are required', 400);
        }

        try {
            AuthService::changePassword(
                $user['id'],
                $data['oldPassword'],
                $data['newPassword']
            );
            return json_success([], 'Password changed successfully');
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }
}
