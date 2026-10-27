# TuraIDC 插件规范（清单 v2）

本文件描述一个 TuraIDC 插件必须满足的契约：清单字段、配置项、生命周期钩子、数据与路由边界、升级方式，以及插件市场索引的 schema。

- 版本：清单 v2（`manifest_version: 2`）
- 兼容：v1 插件不受影响。v2 新增字段与接口**全部可选**，不写就还是 v1 行为。
- 适用：本模板派生出的所有插件仓库，以及向插件市场提交的条目。

## 1. 目录结构

```text
├── config.php                 # 清单：info（元信息）+ config（配置项定义）
├── MyPluginPlugin.php         # 入口类，必须实现 execute(array $request): array
├── src/                       # 可选：ServiceProvider、生命周期实现、内部类
│   ├── MyPluginLifecycle.php
│   └── Providers/MyPluginServiceProvider.php
├── lib/                       # 可选：业务逻辑，运行期自动加载
├── database/migrations/       # 可选：自建表迁移，只允许新增
├── README.md                  # 插件说明（配置项含义、如何卸载清理数据）
└── scripts/validate-manifest.php
```

插件安装到 `{domain}/{slug}/` 目录，目录名必须等于清单里的 `slug`。

## 2. 清单（`config.php` 的 `info`）

```php
return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'my_plugin',
        'key' => 'my_plugin',
        'name' => 'My Plugin',
        'version' => '1.0.0',
        'entry' => MyPluginPlugin::class,
        'capabilities' => ['addons.health'],
        // —— v2 新增，全部可选 ——
        'manifest_version' => 2,
        'min_platform_version' => '1.0.0',
        'description' => '一句话说明插件做什么',
        'author' => '25Cloud',
        'homepage' => 'https://github.com/25Cloud/turaidc-plugin-template',
        'license' => 'MIT',
        'provider' => MyPluginServiceProvider::class,
        'lifecycle' => MyPluginLifecycle::class,
        'upgrade' => ['from' => ['1.0.0'], 'migrations' => true],
        'extra' => [],
    ],
    'config' => [],
];
```

| 字段 | 必填 | 说明 |
| ---- | ---- | ---- |
| `domain` | ✓ | `payment` / `verification` / `captcha` / `mail` / `sms` / `upstream` / `addons` |
| `slug` | ✓ | 插件标识，snake_case，必须等于插件目录名 |
| `key` | ✓ | 平台内唯一键，与 `slug` 保持一致 |
| `name` | ✓ | 管理端展示名 |
| `version` | ✓ | 语义化版本 `MAJOR.MINOR.PATCH`，平台靠它判断是否需要升级 |
| `entry` | ✓ | 入口类，需实现 `execute(array $request): array` |
| `capabilities` | ✓ | 能力声明，点号命名空间（`域.能力`），如 `addons.health`；没有能力就写空数组 |
| `manifest_version` | ✗ | 缺省为 1；写 `2` 才启用 v2 语义 |
| `min_platform_version` | ✗ | 平台低于该版本时拒绝启用，语义化版本 |
| `description` / `author` / `homepage` / `license` | ✗ | 展示与许可信息，`homepage` 必须是合法 URL |
| `provider` | ✗ | ServiceProvider 类；需要注册路由或中间件时必填 |
| `lifecycle` | ✗ | 生命周期实现类，需实现 `PluginLifecycleInterface` |
| `upgrade` | ✗ | `from`：允许直接升级到当前版本的旧版本列表；`migrations`：升级时是否执行 `database/migrations` |
| `extra` | ✗ | v1 的扩展声明（定时任务、调度钩子、绑定信息等），结构保持不变 |

规则：

- `version` 必须语义化；发布 tag 与清单版本必须一致（`v1.2.3` ↔ `1.2.3`）。
- `capabilities` 只写点号标识，不写自然语言，也不要把 `version`、`name` 之类塞进去。
- `manifest_version` 只能是 1 或 2；声明 2 之后才校验 `provider` / `lifecycle` / `upgrade`。

## 3. 配置项（`config`）

管理端插件详情页按 `config` 渲染表单，插件只能声明受控组件：

| `type` | 用途 |
| ---- | ---- |
| `text` / `textarea` | 普通文本、长文本 |
| `number` | 金额、端口、次数 |
| `switch` | 开关 |
| `select` / `multi_select` | 下拉单选 / 多选，需提供 `options` |
| `radio` / `checkbox` | 少量互斥选项 / 多项开关 |
| `password` | 密钥；配合 `secret => true` 加密保存且不明文回显 |
| `url` / `email` / `phone` | 带格式提示的文本 |
| `json` | 结构化配置 |
| `notice` / `divider` / `readonly` | 提示、分组分隔、只读展示；不参与保存 |

v2 额外支持两个标记，只影响管理端展示，不影响读取（避免升级后配置读不到）：

- `since`：这一项从哪个版本开始存在。
- `deprecated`：从哪个版本起弃用；**已保存的值仍会被读取**。

## 4. 生命周期

```php
interface PluginLifecycleInterface
{
    public function install(array $context): void;   // 首次安装：建表、写默认值
    public function enable(array $context): void;    // 每次启用
    public function disable(array $context): void;   // 每次停用
    public function uninstall(array $context): void; // 卸载：默认只清配置，不删业务数据
    public function upgrade(string $fromVersion, string $toVersion, array $context): void;
}
```

约定：

- **五个方法都必须幂等**。升级重试、管理员连点启用、CLI 与后台并发都会让同一方法执行多次。
- `$context` 至少包含 `plugin_id`、`version`、`platform_version`。
- 启用状态由平台写回，插件不要自己去改插件注册表。
- `uninstall()` 默认只清配置与运行期缓存，**不删业务表**；确需清理时在插件 README 写明并提供独立命令。

## 5. 升级

平台按固定顺序执行，任一步失败都保留旧版本号，下次检测时重试：

1. 升级前迁移：执行 `database/migrations` 中新增表 / 新增列的部分；
2. `upgrade()`：需要读旧值再写新值的数据修补；
3. 升级后迁移：数据就位后才能做的回填与索引；
4. 写回 `version`。

插件不要自己执行 `artisan migrate`，也不要把建表语句写在 `upgrade()` 里——重试会重复执行，建表只应存在于迁移中。

## 6. 数据边界

- 插件自建表统一前缀 `plugin_{domain}_{slug}_`，例如 `plugin_addons_my_plugin_settings`。
- 迁移只新增：不删列、不改列类型、不给既有列加会破坏存量数据的默认值。
- SQL 需同时兼容 MySQL 5.7 与 8.x：不使用窗口函数、CTE 等 8.0 专属语法。
- 复用平台核心表时只读；需要写入必须走平台 Service，不直接改核心表。

## 7. 路由与中间件

插件只能注册自己前缀下的路由：

```text
api/v2/client/{slug}/*    前台入口（叠加 ensure.client）
api/v2/admin/{slug}/*     后台入口（叠加 ensure.admin + 权限中间件）
{slug}/v1/*               对外协议（叠加 auth:sanctum）
```

禁止：

- 自建鉴权中间件或登录入口绕过统一认证；
- 注册全局中间件、修改全局配置、占用核心公共路径；
- 自己注册调度任务（调度走清单 `extra.scheduled_tasks` 声明）。

平滑迁移场景允许短期保留旧路径作为别名，但必须在 `upgrade()` 里登记，并在下一个 MAJOR 版本删除。

## 8. 插件市场索引

索引仓库的 `plugins.json`：

```json
{
    "schema": 1,
    "schema_version": 2,
    "updated_at": "2026-10-27T00:00:00+08:00",
    "plugins": [
        {
            "key": "my_plugin",
            "name": "My Plugin",
            "domain": "addons",
            "slug": "my_plugin",
            "repo": "owner/my_plugin",
            "tag": "v1.0.0",
            "sha": "审核锁定的 commit sha（可选）",
            "version": "1.0.0",
            "min_platform_version": "1.0.0",
            "manifest_version": 2,
            "summary": "一句话说明",
            "description": "一句话说明（v1 字段，市场列表读取）",
            "developer": "owner",
            "license": "MIT",
            "capabilities": ["addons.health"],
            "official": false
        }
    ]
}
```

- `schema` 是平台当前解析的字段，**必须保持 `1`**；`schema_version` 表达索引语义版本（`2` = 含 v2 字段）。
  两者并存：新增字段不升 `schema`，删字段或改语义才考虑升版，且必须同步平台解析逻辑。
- 平台只读取自己认识的字段，未知字段会被忽略而不是报错；因此**只加字段是安全的**，改字段名或结构是破坏性变更。
- 条目里的 `version` 必须与插件 `config.php` 的 `info.version` 一致（去掉 tag 的 `v` 前缀）。
- 独立仓库的条目必须提供 `tag`（安装器只下载该 tag 的代码）；提供 `sha` 可把审核锁定到具体 commit。

## 9. 校验

```bash
php -l $(git ls-files '*.php')          # 语法
php scripts/validate-manifest.php       # 清单 + 类签名
vendor/bin/pint --test                  # 代码风格（模板自带 laravel preset）
```

`validate-manifest.php` 校验：必填字段、`domain` 取值、`slug` 格式、语义化版本、`capabilities` 点号格式、入口类文件、`provider` 是否继承 ServiceProvider、`lifecycle` 是否实现接口且五个方法齐全、`min_platform_version` 格式、`upgrade.from` 是否为语义化版本数组、配置项类型。任一项不通过退出码为 1，可直接用于 CI 门禁。

## 10. 已知取舍

- 不做插件沙箱与运行时依赖隔离：PHP 单进程共享 autoload，依赖冲突靠插件自带 `vendor/` 与 `composer.json` 声明解决。
- 不做插件热更新：升级需要重新扫描并由平台写回版本号，避免半升级状态被当成已升级。
- 生命周期钩子只由平台动作触发，插件不得调用其他插件的钩子。
