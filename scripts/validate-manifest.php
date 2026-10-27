<?php

declare(strict_types=1);

/**
 * 插件清单校验（清单 v2）。本地与 CI 共用：只读、不联网、不改文件。
 *
 * 用法：
 *   php scripts/validate-manifest.php
 *   php scripts/validate-manifest.php --path=/path/to/plugin
 *
 * 退出码：0 = 通过（允许存在警告）；1 = 存在错误。
 */

const PLUGIN_DOMAINS = ['payment', 'verification', 'captcha', 'mail', 'sms', 'upstream', 'addons'];

const CONFIG_TYPES = [
    'text', 'textarea', 'number', 'switch', 'select', 'multi_select', 'radio',
    'checkbox', 'json', 'password', 'url', 'email', 'phone', 'notice', 'divider', 'readonly',
];

/** 语义化版本：MAJOR.MINOR.PATCH，可带预发布与构建元信息。 */
const SEMVER_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

/** 能力声明：点号命名空间，至少两段，如 addons.health、ai.chat。 */
const CAPABILITY_PATTERN = '/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9-]*)+$/';

$options = getopt('', ['path::']);
$base = rtrim((string) ($options['path'] ?? dirname(__DIR__)), '/\\');

/** @var list<string> */
$errors = [];

/** @var list<string> */
$warnings = [];

$passed = 0;

/**
 * 记录一项检查结果并立即打印，避免错误报告与检查顺序错位。
 */
$check = static function (bool $ok, string $pass, string $fail, string $prefix = '  ') use (&$errors, &$passed): void {
    if ($ok) {
        $passed++;
        echo "{$prefix}✓ {$pass}\n";

        return;
    }

    $errors[] = $fail;
    echo "{$prefix}✗ {$fail}\n";
};

$warn = static function (string $message) use (&$warnings): void {
    $warnings[] = $message;
    echo "  ! {$message}\n";
};

/**
 * 与平台 PluginFileLoader 一致：插件根目录 *.php 与 src/、lib/、logic/、controller/ 递归加载，
 * 这样才能用 class_exists 判断清单声明的类是否真的存在。
 */
$loadPluginCode = static function (string $base, array &$errors): void {
    $files = [];

    foreach (glob($base.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
        if (basename($file) !== 'config.php') {
            $files[] = $file;
        }
    }

    foreach (['src', 'lib', 'logic', 'controller'] as $directory) {
        $path = $base.DIRECTORY_SEPARATOR.$directory;
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    foreach ($files as $file) {
        try {
            require_once $file;
        } catch (Throwable $exception) {
            $errors[] = '加载插件代码失败：'.basename((string) $file).' — '.$exception->getMessage();
            echo '  ✗ 加载插件代码失败：'.basename((string) $file).' — '.$exception->getMessage()."\n";
        }
    }
};

$classFileInside = static function (string $class, string $base): ?string {
    if (! class_exists($class)) {
        return null;
    }

    $file = (new ReflectionClass($class))->getFileName();
    if ($file === false) {
        return null;
    }

    $basePath = rtrim(str_replace('\\', '/', (string) (realpath($base) ?: $base)), '/');
    $filePath = str_replace('\\', '/', (string) (realpath($file) ?: $file));

    return str_starts_with($filePath, $basePath.'/') ? $filePath : null;
};

echo "TuraIDC 插件清单校验（v2）\n";
echo "  目录：{$base}\n";

$configPath = $base.DIRECTORY_SEPARATOR.'config.php';
if (! is_file($configPath)) {
    fwrite(STDERR, "  ✗ 缺少 config.php\n");
    exit(1);
}

require_once __DIR__.DIRECTORY_SEPARATOR.'platform-stubs.php';

try {
    $manifest = require $configPath;
} catch (Throwable $exception) {
    fwrite(STDERR, '  ✗ config.php 解析失败：'.$exception->getMessage()."\n");
    exit(1);
}

$manifest = is_array($manifest) ? $manifest : [];
$info = is_array($manifest['info'] ?? null) ? $manifest['info'] : [];
$config = is_array($manifest['config'] ?? null) ? $manifest['config'] : [];

echo '  插件：'.(string) ($info['domain'] ?? '?').'/'.(string) ($info['slug'] ?? '?')."\n\n";

// —— 1. 必填字段（v1 契约，保持不变）——
echo "必填字段\n";

$missing = [];
foreach (['domain', 'slug', 'key', 'name', 'version', 'entry', 'capabilities'] as $field) {
    if (! array_key_exists($field, $info) || $info[$field] === '' || $info[$field] === []) {
        $missing[] = $field;
    }
}
$check($missing === [], 'domain / slug / key / name / version / entry / capabilities 均已声明', '缺少必填字段：'.implode('、', $missing));

// —— 2. domain / slug / key ——
echo "\n标识\n";

$domain = (string) ($info['domain'] ?? '');
$check(in_array($domain, PLUGIN_DOMAINS, true), "domain = {$domain}", "domain 非法：{$domain}（可选：".implode(' / ', PLUGIN_DOMAINS).'）');

$slug = (string) ($info['slug'] ?? '');
$check(
    $slug !== '' && preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $slug) === 1,
    "slug = {$slug}",
    "slug 必须是 snake_case 且以字母开头：{$slug}"
);

$key = (string) ($info['key'] ?? '');
$check($key !== '' && preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $key) === 1, "key = {$key}", "key 必须是 snake_case 且以字母开头：{$key}");
if ($key !== '' && $slug !== '' && $key !== $slug) {
    $warn("key（{$key}）与 slug（{$slug}）不一致；清单 v2 约定两者保持一致");
}

// —— 3. 版本 ——
echo "\n版本\n";

$version = (string) ($info['version'] ?? '');
$check($version !== '' && preg_match(SEMVER_PATTERN, $version) === 1, "version = {$version}（语义化版本）", "version 必须是 MAJOR.MINOR.PATCH：{$version}");

$manifestVersion = (int) ($info['manifest_version'] ?? 1);
$check(in_array($manifestVersion, [1, 2], true), "manifest_version = {$manifestVersion}", "manifest_version 只能是 1 或 2：{$manifestVersion}");

$minPlatformVersion = (string) ($info['min_platform_version'] ?? '');
if ($minPlatformVersion === '') {
    echo "  · 未声明 min_platform_version（表示不限制平台版本）\n";
} else {
    $check(
        preg_match(SEMVER_PATTERN, $minPlatformVersion) === 1,
        "min_platform_version = {$minPlatformVersion}",
        "min_platform_version 必须是 MAJOR.MINOR.PATCH：{$minPlatformVersion}"
    );
}

// —— 4. 能力声明 ——
echo "\n能力\n";

$capabilities = $info['capabilities'] ?? [];
if (! is_array($capabilities)) {
    $check(false, '', 'capabilities 必须是数组');
} else {
    $badCapabilities = [];
    foreach ($capabilities as $capability) {
        if (! is_string($capability) || preg_match(CAPABILITY_PATTERN, $capability) !== 1) {
            $badCapabilities[] = is_string($capability) ? $capability : gettype($capability);
        }
    }
    $check(
        $badCapabilities === [],
        $capabilities === [] ? 'capabilities 为空数组（不暴露能力）' : 'capabilities 点号命名合法：'.implode(', ', $capabilities),
        'capabilities 必须是「域.能力」点号格式，非法项：'.implode(', ', $badCapabilities)
    );
}

// —— 5. 入口类 ——
echo "\n入口\n";

$entry = (string) ($info['entry'] ?? '');
if ($entry !== '') {
    $short = (string) substr((string) strrchr('\\'.$entry, '\\'), 1);
    $check($short !== '' && is_file($base.DIRECTORY_SEPARATOR.$short.'.php'), "入口类文件存在：{$short}.php", "入口类文件不存在：{$entry}（应位于插件根目录 {$short}.php）");

    $expected = implode('', array_map('ucfirst', explode('_', $slug))).'Plugin';
    if ($short !== '' && $short !== $expected) {
        $warn("入口类名建议为 {$expected}（Studly(slug)Plugin），当前为 {$short}");
    }
}

// —— 6. v2：provider / lifecycle / upgrade ——
$loadPluginCode($base, $errors);

echo "\nv2 扩展\n";

$provider = (string) ($info['provider'] ?? '');
if ($provider === '') {
    echo "  · 未声明 provider（不需要注册路由与中间件时可以不写）\n";
} else {
    $check(class_exists($provider), "provider 类存在：{$provider}", "provider 类不存在：{$provider}");

    if (class_exists($provider)) {
        $check(
            is_subclass_of($provider, Illuminate\Support\ServiceProvider::class),
            'provider 继承 ServiceProvider',
            "provider 必须继承 Illuminate\\Support\\ServiceProvider：{$provider}"
        );
        $check(
            $classFileInside($provider, $base) !== null,
            'provider 类位于插件目录内',
            "provider 类必须位于插件目录内：{$provider}"
        );
    }
}

$lifecycle = (string) ($info['lifecycle'] ?? '');
if ($lifecycle === '') {
    echo "  · 未声明 lifecycle（不需要安装/升级钩子时可以不写）\n";
} else {
    $interface = App\Contracts\Integrations\Plugins\PluginLifecycleInterface::class;
    $check(class_exists($lifecycle), "lifecycle 类存在：{$lifecycle}", "lifecycle 类不存在：{$lifecycle}");

    if (class_exists($lifecycle)) {
        $check(is_subclass_of($lifecycle, $interface), 'lifecycle 实现 PluginLifecycleInterface', "lifecycle 必须实现 PluginLifecycleInterface：{$lifecycle}");
        $check($classFileInside($lifecycle, $base) !== null, 'lifecycle 类位于插件目录内', "lifecycle 类必须位于插件目录内：{$lifecycle}");

        $missingMethods = [];
        foreach (['install', 'enable', 'disable', 'uninstall', 'upgrade'] as $method) {
            if (! method_exists($lifecycle, $method) || ! (new ReflectionMethod($lifecycle, $method))->isPublic()) {
                $missingMethods[] = $method;
            }
        }
        $check($missingMethods === [], '生命周期五个方法齐全且为 public', '生命周期方法缺失或非 public：'.implode('、', $missingMethods));
    }
}

$upgrade = $info['upgrade'] ?? null;
if ($upgrade === null) {
    echo "  · 未声明 upgrade（不支持跨版本升级时可以省略）\n";
} elseif (! is_array($upgrade)) {
    $check(false, '', 'upgrade 必须是数组，例如 [\'from\' => [\'1.0.0\'], \'migrations\' => true]');
} else {
    $from = $upgrade['from'] ?? null;
    $fromOk = is_array($from) && $from !== [] && array_is_list($from);
    $badFrom = [];
    if ($fromOk) {
        foreach ($from as $fromVersion) {
            if (! is_string($fromVersion) || preg_match(SEMVER_PATTERN, $fromVersion) !== 1) {
                $badFrom[] = is_string($fromVersion) ? $fromVersion : gettype($fromVersion);
            }
        }
    }
    $check(
        $fromOk && $badFrom === [],
        'upgrade.from 是语义化版本数组：'.(is_array($from) ? implode(', ', array_filter($from, 'is_string')) : ''),
        'upgrade.from 必须是非空数组且每项为语义化版本'.($badFrom === [] ? '' : '，非法项：'.implode(', ', $badFrom))
    );

    if (array_key_exists('migrations', $upgrade)) {
        $check(is_bool($upgrade['migrations']), 'upgrade.migrations 是布尔值', 'upgrade.migrations 必须是布尔值');
    }
}

foreach (['description', 'author', 'homepage', 'license'] as $optional) {
    if (isset($info[$optional]) && ! is_string($info[$optional])) {
        $check(false, '', "{$optional} 必须是字符串");
    }
}
if (isset($info['homepage']) && is_string($info['homepage']) && $info['homepage'] !== ''
    && filter_var($info['homepage'], FILTER_VALIDATE_URL) === false) {
    $check(false, '', "homepage 不是合法 URL：{$info['homepage']}");
}

// —— 7. 配置项定义 ——
echo "\n配置项\n";

if ($config === []) {
    echo "  · 没有声明配置项（config 为空）\n";
} else {
    $badTypes = [];
    $badSince = [];
    $emptyOptions = [];
    foreach ($config as $configKey => $definition) {
        if (! is_string($configKey) || trim($configKey) === '' || ! is_array($definition)) {
            $badTypes[] = (string) $configKey;

            continue;
        }

        $type = (string) ($definition['type'] ?? 'text');
        if (! in_array($type, CONFIG_TYPES, true)) {
            $badTypes[] = $configKey.'('.$type.')';
        }

        if ($type === 'select' && (! is_array($definition['options'] ?? null) || ($definition['options'] ?? []) === [])) {
            $emptyOptions[] = $configKey;
        }

        foreach (['since', 'deprecated'] as $marker) {
            if (isset($definition[$marker]) && is_string($definition[$marker]) && preg_match(SEMVER_PATTERN, $definition[$marker]) !== 1) {
                $badSince[] = $configKey.'.'.$marker;
            }
        }
    }

    $check($badTypes === [], '配置项类型合法（'.count($config).' 项）', '配置项类型非法或定义不是数组：'.implode(', ', $badTypes));
    if ($emptyOptions !== []) {
        $check(false, '', 'select 类型缺少 options：'.implode(', ', $emptyOptions));
    }
    if ($badSince !== []) {
        $check(false, '', 'since / deprecated 必须是语义化版本：'.implode(', ', $badSince));
    } else {
        echo "  ✓ since / deprecated 标记格式合法\n";
        $passed++;
    }
}

// —— 汇总 ——
echo "\n";

if ($warnings !== []) {
    echo '警告 '.count($warnings)." 项（不阻断）\n";
}

if ($errors !== []) {
    echo '校验未通过：'.count($errors)." 项错误，{$passed} 项通过\n";
    exit(1);
}

echo "清单校验通过 ✓  {$domain}/{$slug} v{$version}（manifest_version={$manifestVersion}），{$passed} 项检查全部通过\n";

exit(0);
