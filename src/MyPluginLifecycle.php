<?php

declare(strict_types=1);

namespace TuraIDC\Plugins\Addons\MyPlugin;

use App\Contracts\Integrations\Plugins\PluginLifecycleInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 插件生命周期钩子（清单 v2）。
 *
 * 平台会在安装 / 启用 / 停用 / 卸载 / 升级时调用对应方法，并且**可能重复调用**：
 * 升级失败会重试、管理员会连点启用、CLI 与后台可能并发操作。
 * 所以五个方法全部按幂等写——先判断再写，重复执行不产生副作用、不抛异常。
 *
 * $context 至少包含：plugin_id、version、platform_version。
 */
final class MyPluginLifecycle implements PluginLifecycleInterface
{
    /**
     * 插件自建表必须带 `plugin_{domain}_{slug}_` 前缀。
     * 本示例：plugin_addons_my_plugin_settings。
     */
    private const SETTINGS_TABLE = 'plugin_addons_my_plugin_settings';

    /**
     * 首次安装：补默认数据。
     *
     * 建表不写在这里：`database/migrations` 由平台在安装流程里先执行，
     * 放这里会导致重试安装时重复建表。这里只做「表已存在时」的数据初始化。
     */
    public function install(array $context): void
    {
        if (! Schema::hasTable(self::SETTINGS_TABLE)) {
            Log::warning('[my_plugin] 安装时未找到设置表，请确认 database/migrations 已被平台执行');

            return;
        }

        $pluginId = (int) ($context['plugin_id'] ?? 0);
        if ($this->settingsRowExists($pluginId)) {
            return; // 重复安装：已有数据就不覆盖管理员改过的值
        }

        DB::table(self::SETTINGS_TABLE)->insert([
            'plugin_id' => $pluginId,
            'legacy_mode' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 每次启用：只做插件侧准备动作。
     *
     * 启用状态由平台写回，插件不要在这里改 integration_plugins 表。
     */
    public function enable(array $context): void
    {
        Cache::forget($this->cacheKey($context));
    }

    /**
     * 每次停用：清运行期缓存，业务数据一律保留。
     */
    public function disable(array $context): void
    {
        Cache::forget($this->cacheKey($context));
    }

    /**
     * 卸载：默认只清配置与运行期缓存，**不删业务数据**。
     *
     * 确需清理自建表时，不要写在这里（平台卸载后无法回滚，误删不可恢复），
     * 而是在插件 README 写明并提供独立命令，例如：php artisan my-plugin:purge-data。
     */
    public function uninstall(array $context): void
    {
        Cache::forget($this->cacheKey($context));
    }

    /**
     * 升级：处理「代码层面」的数据修补。
     *
     * 迁移怎么跑（由平台控制，插件不要自己执行 artisan migrate）：
     *   1. 升级前迁移：跑 database/migrations 里新增表 / 新增列的部分；
     *   2. upgrade()：也就是本方法，做需要读旧值再写新值的数据修补；
     *   3. 升级后迁移：回填、加索引等在数据就位后才能做的部分；
     *   4. 写回 version：任一步失败都保留旧版本号，下次检测时重试。
     *
     * 因此这里的每一段修补都必须可重复执行（先查后写 / updateOrInsert）。
     */
    public function upgrade(string $fromVersion, string $toVersion, array $context): void
    {
        if (version_compare($fromVersion, $toVersion, '>=')) {
            return; // 重试或降级：不重复修补
        }

        if (version_compare($fromVersion, '1.1.0', '<')) {
            $this->backfillLegacyMode($context);
        }

        Log::info('[my_plugin] 升级完成', [
            'plugin_id' => (int) ($context['plugin_id'] ?? 0),
            'from' => $fromVersion,
            'to' => $toVersion,
            'platform_version' => (string) ($context['platform_version'] ?? ''),
        ]);
    }

    /**
     * 1.1.0 的数据修补示例：把旧配置里的 legacy_mode 落成一行记录。
     *
     * $context['config'] 不保证存在（平台只承诺 plugin_id / version / platform_version），
     * 拿不到旧值就跳过，下次升级重试时再补。
     *
     * @param  array<string, mixed>  $context
     */
    private function backfillLegacyMode(array $context): void
    {
        $config = is_array($context['config'] ?? null) ? $context['config'] : [];
        if (! array_key_exists('legacy_mode', $config) || ! Schema::hasTable(self::SETTINGS_TABLE)) {
            return;
        }

        DB::table(self::SETTINGS_TABLE)->updateOrInsert(
            ['plugin_id' => (int) ($context['plugin_id'] ?? 0)],
            ['legacy_mode' => (bool) $config['legacy_mode'], 'updated_at' => now()],
        );
    }

    private function settingsRowExists(int $pluginId): bool
    {
        return DB::table(self::SETTINGS_TABLE)->where('plugin_id', $pluginId)->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function cacheKey(array $context): string
    {
        return 'plugin:addons:my_plugin:'.(int) ($context['plugin_id'] ?? 0);
    }
}
