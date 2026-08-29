<?php

declare(strict_types=1);

/**
 * 插件重命名脚本（跨平台，需 PHP 8.3+）。
 *
 * 用法（仓库根目录执行）：
 *   php scripts/rename.php <new_slug> [<new_name>]
 *
 * 示例：
 *   php scripts/rename.php my_sms "我的短信"
 *
 * 将全仓替换：
 *   - my_plugin   -> my_sms     （slug / key / 目录名）
 *   - MyPlugin    -> MySms      （类名 / 命名空间）
 *   - My Plugin   -> 我的短信    （manifest 显示名，可选）
 *   - addons.my_plugin.health -> addons.my_sms.health（默认动作）
 *
 * 注意：
 *   - 如 domain 需变更（addons -> sms），请同时手动修改 config.php 的 'domain'
 *     与命名空间首段（TuraIDC\Plugins\Addons\ -> TuraIDC\Plugins\Sms\）。
 *   - 脚本不会重命名仓库自身目录；安装时插件目录名必须等于新 slug。
 */

$newSlug = strtolower(trim((string) ($argv[1] ?? '')));
if ($newSlug === '' || preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $newSlug) !== 1) {
    fwrite(STDERR, "用法：php scripts/rename.php <new_slug> [<new_name>]\n");
    fwrite(STDERR, "new_slug 必须是 snake_case（小写字母/数字/下划线，以字母开头）。\n");
    exit(1);
}

$newStudly = implode('', array_map('ucfirst', explode('_', $newSlug)));
$newName = trim((string) ($argv[2] ?? '')) !== ''
    ? trim((string) $argv[2])
    : ucwords(str_replace('_', ' ', $newSlug));

$base = dirname(__DIR__);

/** @var array<string, string> 长串在前，避免部分匹配残留。 */
$replacements = [
    'MyPlugin' => $newStudly,
    'my_plugin' => $newSlug,
    'My Plugin' => $newName,
];

/** @var list<string> */
$errors = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $relative = substr($file->getPathname(), strlen($base) + 1);
    if (str_starts_with($relative, '.git'.DIRECTORY_SEPARATOR) || str_starts_with($relative, 'vendor'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    if ($file->isDir()) {
        continue;
    }

    $content = (string) file_get_contents($file->getPathname());
    $updated = strtr($content, $replacements);

    if ($updated !== $content) {
        if (file_put_contents($file->getPathname(), $updated) === false) {
            $errors[] = "写入失败：{$relative}";
        }
        echo "更新内容：{$relative}\n";
    }

    $oldName = $file->getFilename();
    $newFilename = strtr($oldName, $replacements);
    if ($newFilename !== $oldName) {
        $newPath = $file->getPath().DIRECTORY_SEPARATOR.$newFilename;
        if (rename($file->getPathname(), $newPath) === false) {
            $errors[] = "重命名失败：{$relative}";
        }
        echo "重命名：{$oldName} -> {$newFilename}\n";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "  ✗ {$error}\n");
    }
    exit(1);
}

echo "\n完成：slug={$newSlug} 类名={$newStudly} 显示名={$newName}\n";
echo "下一步：修改 config.php 的 domain（如需变更），补充 capabilities / config，然后实现动作。\n";
echo "验证：php -l *.php && php scripts/validate-manifest.php && vendor/bin/pint --test\n";
exit(0);
