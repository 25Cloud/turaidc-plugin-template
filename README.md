# TuraIDC 插件模板仓库（清单 v2）

按 [插件规范](docs/SPEC.md) 搭好的最小可运行插件骨架。点 **Use this template** 复制到你的账号，改名、补业务逻辑，就能得到一个可独立分发的 TuraIDC 插件仓库。

本仓库同时是 v2 清单的**活样例**：`config.php`、生命周期实现、Provider、校验脚本都可直接对照抄。

## 三分钟上手

```bash
# 1. Use this template 复制本仓库，然后克隆到本地
git clone https://github.com/<你>/<你的插件>.git my_plugin && cd my_plugin

# 2. 一条命令改名：slug、类名、命名空间、动作前缀一起换
php scripts/rename.php my_sms "我的短信"

# 3. 改域（默认 addons）：同步改 config.php 的 'domain' 与命名空间首段
#    TuraIDC\Plugins\Addons\ -> TuraIDC\Plugins\Sms\

# 4. 本地验证（三条都要过）
php -l config.php
php scripts/validate-manifest.php
composer install && vendor/bin/pint --test
```

校验脚本会打印逐项结果并以退出码表示成败：`0` 通过，`1` 有错误。CI 与本地跑的是同一个脚本。

## 目录说明

```text
├── config.php                  # 清单：info（元信息）+ config（配置项定义）
├── MyPluginPlugin.php          # 入口类：execute(array $request): array
├── src/
│   ├── MyPluginLifecycle.php   # 生命周期钩子（安装/启用/停用/卸载/升级）
│   └── Providers/              # ServiceProvider：只注册自己前缀下的路由
├── lib/                        # 业务逻辑，运行期自动加载
├── docs/SPEC.md                # 插件规范原文（清单字段、生命周期、数据与路由边界）
├── scripts/
│   ├── rename.php              # 模板改名
│   ├── validate-manifest.php   # 清单与类签名校验
│   └── platform-stubs.php      # 校验用的平台类桩，不参与运行
└── .github/workflows/          # CI：php -l + 清单校验 + Pint
```

## 清单要点

- `version` 语义化；**Git tag 必须与它一致**（`v1.2.3` ↔ `1.2.3`）。
- `capabilities` 用点号命名空间（`addons.health`），不写自然语言。
- 需要注册路由/中间件才填 `provider`；需要安装/升级钩子才填 `lifecycle`。
- 配置项用 `config` 声明，管理端据此渲染表单；密钥字段加 `secret => true`。
- 详细字段表见 [docs/SPEC.md](docs/SPEC.md#2-清单configphp-的-info)。

## 怎么做升级

1. 改 `config.php` 的 `version`（例如 `1.0.0` → `1.1.0`）。
2. 把 `upgrade.from` 加上你允许直接升上来的旧版本，如 `['1.0.0']`。
3. 新增表/列写成 `database/migrations/` 下的迁移（只新增，不删列、不改类型）。
4. 需要读旧值再写新值的修补写进 `src/MyPluginLifecycle.php` 的 `upgrade()`，**按版本分段、可重复执行**。
5. 平台执行顺序：升级前迁移 → `upgrade()` → 升级后迁移 → 写回版本；任一步失败保留旧版本号，下次重试。

不要自己跑 `artisan migrate`，也不要在 `upgrade()` 里建表——建表只应存在于迁移中，否则重试会重复执行。

## 不要做什么

- **不要自建鉴权**：不写登录接口、不造鉴权中间件，鉴权一律复用平台中间件（`ensure.admin` / `ensure.client` / `auth:sanctum` / `permission:*`）。
- **不要注册全局中间件、全局配置或调度任务**：路由只能挂在自己 slug 前缀下，调度走清单 `extra.scheduled_tasks` 声明。
- **不要删表删列**：迁移只新增；卸载默认不删业务数据，确需清理要在 README 写明并提供独立命令。
- **不要占用核心公共路径**：`api/v2/client/*`、`api/v2/admin/*` 下除自己 slug 前缀以外的路径都不属于插件。
- **不要改已审核版本的 tag/sha**：市场安装器按审核锁定的引用下载代码，改了就不一致。

## 发布到插件市场

1. 发 Release：tag = `v{major.minor.patch}`，与清单 `version` 一致。
2. 到 [插件市场索引仓库](https://github.com/25Cloud/turaidc-plugin-index) 提 PR，在 `plugins.json` 的 `plugins` 数组加一条目。
3. 合并即上架，管理端插件中心可直接搜索安装。

## 许可

模板骨架为 MIT；你复制出来的插件可自行声明许可（与清单 `license` 字段保持一致）。TuraIDC 主仓为 AGPL-3.0-or-later：独立插件通过清单与 `execute()` 契约集成，不直接复制主仓代码；是否构成联合作品请自行评估。

## 相关链接

- [插件规范（本仓库）](docs/SPEC.md)
- [TuraIDC 主仓](https://github.com/25Cloud/TuraIDC)
- [插件开发指南](https://github.com/25Cloud/TuraIDC/blob/main/docs/develop/plugins/index.md)
- [插件包规范（分发与市场）](https://github.com/25Cloud/TuraIDC/blob/main/docs/develop/plugins/package-spec.md)
