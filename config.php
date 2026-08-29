<?php

declare(strict_types=1);

use TuraIDC\Plugins\Addons\MyPlugin\MyPluginPlugin;

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'my_plugin',
        'key' => 'my_plugin',
        'name' => 'My Plugin',
        'version' => '1.0.0',
        'entry' => MyPluginPlugin::class,
        'capabilities' => [
            // 运行期能力声明，取值与语义见插件包规范。
            // 留空数组表示暂不暴露能力。
        ],
        'extra' => [
            // 可选：心跳定时任务 / 钩子 / 管理端入口等。
            // 'scheduled_tasks' => [
            //     MyPluginScheduledTask::class,
            // ],
        ],
    ],
    'config' => [
        // 插件配置项定义：管理端插件详情页据此渲染配置表单。
        // 支持 text / textarea / password / switch / select / divider / notice 等类型。
        // 示例：
        // 'api_key' => [
        //     'title' => 'API Key',
        //     'type' => 'password',
        //     'value' => '',
        //     'required' => true,
        //     'secret' => true,
        //     'placeholder' => 'sk-...',
        // ],
    ],
];
