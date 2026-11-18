<?php

namespace Vcsb\SystemSettings;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

/**
 * 系统设置 Tool: 后台自定义设置页, Schema 驱动的表单替代资源列表页.
 */
class SystemSettings extends Tool
{
    /**
     * 注册构建产物 mix 清单, Nova 据此给后台页面注入 js/css.
     */
    public function boot(): void
    {
        Nova::mix('vcsb-system-settings', __DIR__.'/../dist/mix-manifest.json');
    }

    /**
     * 标准侧栏菜单项; 项目在 mainMenu 手挂时以手挂为准, 此处为标准实现兜底.
     *
     * @param  Request  $request  当前后台请求
     */
    public function menu(Request $request): MenuSection
    {
        return MenuSection::make('系统设置')
            ->path('/system-settings')
            ->icon('adjustments');
    }
}
