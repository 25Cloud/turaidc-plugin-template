<?php

declare(strict_types=1);

namespace TuraIDC\Plugins\Addons\MyPlugin\Providers;

use Illuminate\Support\ServiceProvider;

class MyPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 只注册插件内部服务（容器绑定）。仅在插件启用后由平台的 Provider 注册表激活，
        // 插件未启用时不会执行到这里，可以放心做依赖注入。
    }

    public function boot(): void
    {
        // 路由只能挂在**自己 slug 前缀**下，且必须复用平台已有中间件：
        //   api/v2/client/my_plugin/*   前台入口，叠加 ensure.client
        //   api/v2/admin/my_plugin/*    后台入口，叠加 ensure.admin（+ 权限中间件）
        //   my_plugin/v1/*              对外协议，叠加 auth:sanctum
        //
        // 不要做：注册全局中间件、自建鉴权入口绕过统一认证、占用核心公共路径、
        // 注册调度任务（调度走清单 extra.scheduled_tasks 声明）。
        //
        // 示例（控制器需自行实现）：
        // Route::middleware(['ensure.admin', 'permission:<插件自己的权限标识>'])
        //     ->prefix('api/v2/admin/my_plugin')
        //     ->group(function (): void {
        //         Route::get('status', MyPluginStatusController::class);
        //     });
    }
}
