<?php

namespace Vcsb\SystemSettings;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Http\Middleware\Authorize as NovaAuthorize;
use Laravel\Nova\Nova;
use Vcsb\SystemSettings\Http\Middleware\Authorize;
use Vcsb\SystemSettings\Models\SystemSetting;
use Vcsb\SystemSettings\Policies\SystemSettingPolicy;

/**
 * 系统设置包服务提供者: 注册 Nova 工具页面路由、数据端点路由、包内迁移与模型 Policy, 发布构建产物.
 * 自包含 Nova 扩展: 模型/枚举/迁移/Seeder/校验器全在包内, app 侧只留菜单与 DatabaseSeeder 调用.
 */
class ToolServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 包内模型迁移 (文件名与 app 时期一致, 已跑过的库不会重跑)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 包内模型不在 app/Policies 自动发现范围, 按模型显式挂 Policy
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);

        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            // Nova 每次响应重新注册 mix 资产, 保证工具脚本不丢
            Nova::mix('vcsb-system-settings', __DIR__.'/../dist/mix-manifest.json');
        });
    }

    /**
     * 注册路由: 页面壳走 Nova::router, 读写端点走 nova-vendor 前缀.
     * 链里必须带 Nova 自家的 Authorize (viewNova 门禁, nova-api 同款): nova.auth 只管登录态, 缺它普通用户也能进.
     */
    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(['nova', 'nova.auth', NovaAuthorize::class, Authorize::class], 'system-settings')
            ->group(__DIR__.'/routes/inertia.php');

        Route::middleware(['nova', 'nova.auth', NovaAuthorize::class, Authorize::class])
            ->prefix('nova-vendor/system-settings')
            ->group(__DIR__.'/routes/api.php');
    }

    public function register(): void
    {
        //
    }
}
