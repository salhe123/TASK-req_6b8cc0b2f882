<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\User as UserModel;
use app\service\AuditService;
use app\service\AuthService;
use think\exception\ValidateException;

class User extends BaseController
{
    private static function validRoles(): array
    {
        return [
            'PRODUCTION_PLANNER', 'OPERATOR', 'SERVICE_COORDINATOR', 'PROVIDER',
            'REVIEWER', 'REVIEW_MANAGER', 'PRODUCT_SPECIALIST',
            'CONTENT_MODERATOR', 'FINANCE_CLERK', 'SYSTEM_ADMIN',
        ];
    }

    public function index()
    {
        $role   = $this->request->get('role', '');
        $status = $this->request->get('status', '');
        $page   = (int) $this->request->get('page', 1);
        $size   = (int) $this->request->get('size', 20);

        $query = UserModel::order('id', 'asc');

        if ($role) {
            $query->where('role', $role);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
        ]);
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
            return json_error('Username, password, and role are required', 400);
        }

        $validRoles = self::validRoles();

        if (!in_array($data['role'], $validRoles, true)) {
            return json_error('Invalid role', 400);
        }

        // Check duplicate username
        if (UserModel::where('username', $data['username'])->find()) {
            return json_error('Username already exists', 409);
        }

        try {
            AuthService::validatePasswordComplexity($data['password']);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }

        $now = date('Y-m-d H:i:s');
        $user = new UserModel();
        $user->username              = $data['username'];
        $user->password              = $data['password'];
        $user->role                  = $data['role'];
        $user->status                = 'ACTIVE';
        $user->failed_login_attempts = 0;
        $user->created_at            = $now;
        $user->updated_at            = $now;
        $user->save();

        AuditService::log('USER_CREATED', 'user', $user->id, null, [
            'username' => $user->username,
            'role'     => $user->role,
        ]);

        return json_success($user->toArray(), 'User created', 201);
    }

    public function update($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return json_error('User not found', 404);
        }

        $data   = json_decode($this->request->getInput(), true) ?: [];
        $before = $user->toArray();

        if (isset($data['role'])) {
            if (!in_array($data['role'], self::validRoles(), true)) {
                return json_error('Invalid role', 400);
            }
            $user->role = $data['role'];
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['ACTIVE', 'LOCKED', 'INACTIVE'], true)) {
                return json_error('Invalid status', 400);
            }
            $user->status = $data['status'];
        }

        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        AuditService::log('USER_UPDATED', 'user', $user->id, $before, $user->toArray());

        return json_success($user->toArray());
    }

    public function lock($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return json_error('User not found', 404);
        }

        $before = $user->toArray();
        $user->status    = 'LOCKED';
        $user->locked_at = date('Y-m-d H:i:s');
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        AuditService::log('USER_LOCKED', 'user', $user->id, $before, $user->toArray());

        return json_success($user->toArray());
    }

    public function unlock($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return json_error('User not found', 404);
        }

        $before = $user->toArray();
        $user->status                = 'ACTIVE';
        $user->locked_at             = null;
        $user->failed_login_attempts = 0;
        $user->updated_at            = date('Y-m-d H:i:s');
        $user->save();

        AuditService::log('USER_UNLOCKED', 'user', $user->id, $before, $user->toArray());

        return json_success($user->toArray());
    }
}
