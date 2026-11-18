<?php

namespace Vcsb\SystemSettings\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Vcsb\SystemSettings\Models\SystemSetting;

/**
 * 系统设置只给后台管理员看和改: 读写都过 viewNova 邮箱白名单; 行由 Seeder 维护, 建删一律 false.
 * 包内模型不在 app/Policies 自动发现范围, 由 ToolServiceProvider 用 Gate::policy 注册.
 */
class SystemSettingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('viewNova');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return $user->can('viewNova');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return $user->can('viewNova');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return false;
    }
}
