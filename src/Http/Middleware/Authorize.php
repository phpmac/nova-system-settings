<?php

namespace Vcsb\SystemSettings\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Symfony\Component\HttpFoundation\Response;
use Vcsb\SystemSettings\SystemSettings;

/**
 * 工具访问授权: 工具未注册返回 404, 当前用户无权查看返回 403.
 */
class Authorize
{
    /**
     * @param  Closure(Request): (Response)  $next  下一层管道
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tool = collect(Nova::registeredTools())->first([$this, 'matchesTool']);

        if (is_null($tool)) {
            abort(404);
        }

        if (! $tool->authorize($request)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * 判断注册列表里的工具是不是本工具.
     */
    public function matchesTool(Tool $tool): bool
    {
        return $tool instanceof SystemSettings;
    }
}
