# TuraIDC 插件模板仓库

按 [TuraIDC 插件包规范](https://github.com/25Cloud/TuraIDC/blob/main/docs/references/integrations/plugins/plugin-package-spec.md) 搭建的最小可运行插件骨架。点 **Use this template** 复制本仓库，改名并补业务逻辑，即可得到一个独立分发的 TuraIDC 插件仓库——无需 fork 主仓。

## 快速开始

```bash
# 1. Use this template 复制本仓库到你的账号/组织
# 2. 克隆并重命名（slug 全局替换，含类名/命名空间/动作）
php scripts/rename.php my_sms "我的短信"

# 3. 如需改域（默认 addons），手动调整：
#    - config.php 的 'domain'
#    - 类文件命名空间首段：TuraIDC\Plugins\Addons\ -> TuraIDC\Plugins\Sms\

# 4. 本地验证
php -l MySmsPlugin.php
php scripts/validate-manifest.php
composer install && vendor/bin/pint --test
```

## 目录结构

```text
├── config.php            # 插件清单（manifest info + 配置项 config schema）
├── MyPluginPlugin.php    # 统一执行入口（execute）
├── lib/                  # 业务逻辑（运行期自动加载）
├── src/Providers/        # ServiceProvider（可选）
├── scripts/              # rename / validate-manifest 工具
└── .github/workflows/    # CI：php -l + manifest 校验 + pint
```

## 契约要点

| 项 | 约定 |
| ---- | ---- |
| 命名空间 | `TuraIDC\Plugins\{Domain}\{Slug}`（Domain/Slug 取 Studly） |
| manifest | `config.php` 的 `info` 数组：domain / slug / key / name / version / entry / capabilities / extra |
| 入口 | `entry` 指向的类必须实现 `execute(array $request): array`，返回 `success/action/message/data` |
| 配置项 | `config` 数组定义，管理端据此渲染表单；支持 text/password/switch/select 等 |
| 业务逻辑 | 放 `lib/`，运行期由主仓自动加载 |
| 定时任务 | 可选，`extra.scheduled_tasks` 声明，实现主仓心跳 `ScheduledTask` 契约 |

完整契约见 [主仓插件文档](https://github.com/25Cloud/TuraIDC/blob/main/docs/references/integrations/plugins/README.md)。

## 安装到 TuraIDC

1. 将插件目录放入 `backend/plugins/{domain-dir}/{slug}/`（域目录映射见插件包规范）
2. 管理端 → 插件中心 → 安装并启用
3. 在插件详情页填写配置

## 发布与许可

- 版本号遵循语义化版本，Git tag 与 manifest `info.version` 保持一致。
- 本模板骨架为 MIT；你复制的插件代码可自行声明许可。
- 注意主仓为 AGPL-3.0：独立分发的插件避免直接复制主仓代码，通过 manifest / `execute()` 契约集成；是否构成联合作品的认定请自行评估，见插件包规范「协议与安全边界」。

## 相关链接

- [TuraIDC 主仓](https://github.com/25Cloud/TuraIDC)
- [插件包规范](https://github.com/25Cloud/TuraIDC/blob/main/docs/references/integrations/plugins/plugin-package-spec.md)
- [插件运行期契约](https://github.com/25Cloud/TuraIDC/blob/main/docs/references/integrations/plugins/README.md)
