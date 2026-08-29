<?php

declare(strict_types=1);

namespace TuraIDC\Plugins\Addons\MyPlugin\Providers;

use Illuminate\Support\ServiceProvider;

class MyPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 注册插件内部服务。仅在插件启用后由 PluginProviderRegistry 激活。
    }

    public function boot(): void
    {
        // 插件启用后的引导逻辑：路由、事件、调度等。
    }
}
