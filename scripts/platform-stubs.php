<?php

declare(strict_types=1);

/**
 * 平台类桩（仅供脚本在独立插件仓库里做静态校验）。
 *
 * 插件仓库不安装 TuraIDC 主仓的依赖，缺少 Illuminate\Support\ServiceProvider
 * 与生命周期接口时，class_exists / is_subclass_of 无法判断类签名。
 * 这里只在「类或接口未定义」时补最小桩；真实平台环境下该文件不会被加载。
 */

namespace Illuminate\Support {
    if (! class_exists(ServiceProvider::class)) {
        class ServiceProvider
        {
            public function register(): void {}

            public function boot(): void {}
        }
    }
}

namespace App\Contracts\Integrations\Plugins {
    if (! interface_exists(PluginLifecycleInterface::class)) {
        interface PluginLifecycleInterface
        {
            /**
             * @param  array<string, mixed>  $context
             */
            public function install(array $context): void;

            /**
             * @param  array<string, mixed>  $context
             */
            public function enable(array $context): void;

            /**
             * @param  array<string, mixed>  $context
             */
            public function disable(array $context): void;

            /**
             * @param  array<string, mixed>  $context
             */
            public function uninstall(array $context): void;

            /**
             * @param  array<string, mixed>  $context
             */
            public function upgrade(string $fromVersion, string $toVersion, array $context): void;
        }
    }
}
