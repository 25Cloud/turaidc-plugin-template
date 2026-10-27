<?php

declare(strict_types=1);

use TuraIDC\Plugins\Addons\MyPlugin\MyPluginLifecycle;
use TuraIDC\Plugins\Addons\MyPlugin\MyPluginPlugin;
use TuraIDC\Plugins\Addons\MyPlugin\Providers\MyPluginServiceProvider;

return [
    'info' => [
        // —— v1 必填字段，保持不变 ——
        'domain' => 'addons',
        'slug' => 'my_plugin',
        'key' => 'my_plugin',
        'name' => 'My Plugin',
        // 语义化版本：平台用「已安装版本 < 清单版本」判断是否触发升级
        'version' => '1.0.0',
        'entry' => MyPluginPlugin::class,
        'capabilities' => [
            // 运行期能力，点号命名空间（域.能力），用于权限与依赖判断。
            // 不要写自然语言；留空数组表示暂不暴露能力。
            'addons.health',
        ],

        // —— v2 新增字段，全部可选；写 manifest_version => 2 才启用新语义 ——
        'manifest_version' => 2,
        // 平台低于该版本时拒绝启用（语义化版本，不写下限就留空省略）
        'min_platform_version' => '1.0.0',
        'description' => '插件模板：演示 v2 清单、生命周期钩子与配置项写法',
        'author' => '25Cloud',
        'homepage' => 'https://github.com/25Cloud/turaidc-plugin-template',
        'license' => 'MIT',
        // 需要注册路由或中间件时才填；只允许注册自己 slug 前缀下的路由
        'provider' => MyPluginServiceProvider::class,
        // 生命周期钩子：五个方法都必须幂等，见 src/MyPluginLifecycle.php
        'lifecycle' => MyPluginLifecycle::class,
        'upgrade' => [
            // 允许从这些版本直接升级到当前 version；不在这里的版本要求重装
            'from' => ['1.0.0'],
            // 升级时执行 database/migrations 下尚未跑过的迁移
            'migrations' => true,
        ],

        'extra' => [
            // 可选扩展声明，v1 结构保持不变：心跳定时任务 / 调度 Hooks / 绑定信息等。
            // 'scheduled_tasks' => [MyPluginScheduledTask::class],
        ],
    ],
    'config' => [
        // 配置项定义：管理端插件详情页据此渲染表单。
        // 可用类型：text / textarea / number / switch / select / multi_select / radio /
        // checkbox / json / password / url / email / phone / notice / divider / readonly
        'endpoint' => [
            'title' => '接口地址',
            'type' => 'url',
            'value' => 'https://api.example.com',
            'required' => true,
            'placeholder' => '例如 https://api.example.com',
            'description' => '插件请求上游的根地址，末尾不要带斜杠',
        ],
        'api_key' => [
            'title' => 'API Key',
            'type' => 'password',
            'value' => '',
            'required' => true,
            'secret' => true,
            'placeholder' => 'sk-...',
            'description' => '保存后不再明文回显，留空表示不修改已保存的值',
        ],
        'mode' => [
            'title' => '工作模式',
            'type' => 'select',
            'value' => 'standard',
            'options' => [
                ['label' => '标准', 'value' => 'standard'],
                ['label' => '严格', 'value' => 'strict'],
            ],
            // v2：这一项从哪个版本开始存在（只影响管理端展示与升级提示）
            'since' => '1.0.0',
        ],
        'legacy_mode' => [
            'title' => '旧模式',
            'type' => 'switch',
            'value' => false,
            // v2：标记从哪个版本起弃用。已保存的值仍会被读取，只是不再作为新增项展示
            'deprecated' => '1.1.0',
        ],
    ],
];
