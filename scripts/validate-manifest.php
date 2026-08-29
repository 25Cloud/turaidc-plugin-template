<?php

declare(strict_types=1);

/**
 * 插件 manifest 校验脚本（本地 / CI 共用）。
 * 与主仓 PluginInstaller 的安装前校验保持一致，防止发布破损包。
 *
 * 用法：php scripts/validate-manifest.php
 */

$base = dirname(__DIR__);
$configPath = $base.DIRECTORY_SEPARATOR.'config.php';
if (! is_file($configPath)) {
    fwrite(STDERR, "✗ 缺少 config.php\n");

    exit(1);
}

$manifest = require $configPath;
$info = is_array($manifest['info'] ?? null) ? $manifest['info'] : [];

/** @var list<string> */
$errors = [];

foreach (['domain', 'slug', 'key', 'name', 'version', 'entry'] as $field) {
    if (empty($info[$field])) {
        $errors[] = "manifest 缺少字段：{$field}";
    }
}

$domain = (string) ($info['domain'] ?? '');
if (! in_array($domain, ['payment', 'verification', 'captcha', 'mail', 'sms', 'upstream', 'addons'], true)) {
    $errors[] = "domain 非法：{$domain}";
}

$slug = (string) ($info['slug'] ?? '');
if ($slug !== '' && preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $slug) !== 1) {
    $errors[] = "slug 格式非法：{$slug}";
}

$entry = (string) ($info['entry'] ?? '');
if ($entry !== '') {
    // 入口类名必须与 slug 对应（Studly(slug)Plugin），保证安装与运行期契约一致。
    $expectedEntry = implode('', array_map('ucfirst', explode('_', $slug))).'Plugin';
    $short = substr((string) strrchr($entry, '\\'), 1);
    if ($short !== $expectedEntry) {
        $errors[] = "入口类名应为 {$expectedEntry}（Studly(slug)Plugin），当前为：{$entry}";
    }
    if ($short !== '' && ! is_file($base.DIRECTORY_SEPARATOR.$short.'.php')) {
        $errors[] = "入口类文件不存在：{$entry}";
    }
}

// 声明的类文件必须位于插件目录内（防目录穿越）。
foreach (($info['extra'] ?? []) as $declarations) {
    foreach ((array) $declarations as $class) {
        $file = str_replace('\\', '/', (string) $class).'.php';
        if (str_contains($file, '../') || str_starts_with($file, '/')) {
            $errors[] = "非法类路径：{$class}";
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "  ✗ {$error}\n");
    }
    exit(1);
}

echo "manifest 校验通过 ✓（{$domain}/{$slug} v{$info['version']}）\n";

exit(0);
