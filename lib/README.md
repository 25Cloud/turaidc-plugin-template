# lib

插件业务逻辑目录。

运行期由主仓 `PluginFileLoader` 自动加载目录下（含子目录）的 PHP 文件，无需 composer autoload。
命名空间统一为 `TuraIDC\Plugins\{Domain}\{Slug}\Lib`，与 `config.php` 中 manifest 声明保持对应。

建议按职责拆分：

```text
lib/
├── MyPluginService.php        # 业务服务
├── Support/                   # 内部支撑类
└── MyPluginScheduledTask.php  # 心跳定时任务（可选）
```

本目录删除后骨架仍可运行，无需保留占位。
