<?php

namespace App\Plugins;

use App\Models\Plugin as PluginModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class PluginRegistry
{
    private static ?string $pluginsPath = null;

    public static function pluginsPath(): string
    {
        if (self::$pluginsPath === null) {
            self::$pluginsPath = base_path('plugins');
        }
        return self::$pluginsPath;
    }

    /**
     * List all plugins found on disk (with valid plugin.json).
     * Merges with DB state for is_enabled when table exists.
     *
     * @return array<int, array{slug: string, name: string, version: string, path: string, is_enabled: bool, menu?: array, routes?: string|array, events?: array}>
     */
    public static function installed(): array
    {
        $path = self::pluginsPath();
        if (! is_dir($path)) {
            return [];
        }

        $dbPlugins = [];
        if (self::tableExists()) {
            $dbPlugins = PluginModel::all()->keyBy('slug')->all();
        }

        $result = [];
        $dirs = array_filter(glob($path.DIRECTORY_SEPARATOR.'*'), 'is_dir');
        foreach ($dirs as $dir) {
            $manifestFile = $dir.DIRECTORY_SEPARATOR.'plugin.json';
            if (! is_file($manifestFile)) {
                continue;
            }
            $manifest = self::readManifest($dir);
            if (! $manifest) {
                continue;
            }
            $slug = $manifest['slug'] ?? basename($dir);
            $record = $dbPlugins[$slug] ?? null;
            $isRegistered = $record !== null;
            $isEnabled = $record ? $record->is_enabled : false;

            $result[] = [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'version' => $manifest['version'] ?? '1.0.0',
                'path' => $dir,
                'is_registered' => $isRegistered,
                'is_enabled' => (bool) $isEnabled,
                'type' => $manifest['type'] ?? null,
                'banner' => ! empty($manifest['banner']) ? $manifest['banner'] : null,
                'category' => ! empty($manifest['category']) ? $manifest['category'] : 'outros',
                'menu' => $manifest['menu'] ?? null,
                'routes' => $manifest['routes'] ?? null,
                'events' => $manifest['events'] ?? [],
                'migrations' => $manifest['migrations'] ?? null,
                'description' => $manifest['description'] ?? null,
                'author' => $manifest['author'] ?? null,
                'settings_tab' => $manifest['settings_tab'] ?? null,
            ];
        }
        return $result;
    }

    /**
     * Abas extra em Configurações declaradas no plugin.json (plugins ativos).
     *
     * @return array<int, array{id: string, label: string, component: string}>
     */
    public static function getSettingsTabs(): array
    {
        $items = [];
        foreach (self::enabled() as $plugin) {
            $tab = $plugin['settings_tab'] ?? null;
            if (! is_array($tab)) {
                continue;
            }
            $id = trim((string) ($tab['id'] ?? ''));
            $label = trim((string) ($tab['label'] ?? ''));
            $component = trim((string) ($tab['component'] ?? ''));
            if ($id === '' || $label === '' || $component === '') {
                continue;
            }
            if (! str_starts_with($component, 'Plugin/')) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'label' => $label,
                'component' => $component,
            ];
        }

        return $items;
    }

    /**
     * Only plugins that are enabled (for loading bootstrap and routes).
     *
     * @return array<int, array{slug: string, name: string, version: string, path: string, menu?: array, routes?: string|array, events?: array}>
     */
    public static function enabled(): array
    {
        $installed = self::installed();
        return array_values(array_filter($installed, fn ($p) => $p['is_enabled']));
    }

    public static function enable(string $slug): bool
    {
        self::syncFromDisk();
        if (! self::tableExists()) {
            return false;
        }
        $plugin = PluginModel::find($slug);
        if (! $plugin) {
            $plugin = PluginModel::create([
                'slug' => $slug,
                'name' => $slug,
                'version' => '1.0.0',
                'is_enabled' => true,
            ]);
        } else {
            $plugin->update(['is_enabled' => true]);
        }
        self::clearRouteCacheIfCached();

        return true;
    }

    public static function disable(string $slug): bool
    {
        if (! self::tableExists()) {
            return false;
        }
        $plugin = PluginModel::find($slug);
        if ($plugin) {
            $plugin->update(['is_enabled' => false]);
            self::clearRouteCacheIfCached();

            return true;
        }
        return false;
    }

    /**
     * Uninstall plugin: delete plugin directory from disk, then remove from DB.
     * Pass $pluginPath (from installed()['path']) when the folder name differs from slug.
     */
    public static function uninstall(string $slug, ?string $pluginPath = null): bool
    {
        $basePath = realpath(self::pluginsPath()) ?: self::pluginsPath();
        $pluginDir = $pluginPath !== null && $pluginPath !== ''
            ? realpath($pluginPath)
            : realpath($basePath.DIRECTORY_SEPARATOR.$slug);

        if ($pluginDir !== false && is_dir($pluginDir)) {
            $basePathReal = realpath($basePath);
            $sep = DIRECTORY_SEPARATOR;
            $len = $basePathReal !== false ? strlen($basePathReal) : 0;
            if ($basePathReal === false
                || $pluginDir === $basePathReal
                || strpos($pluginDir, $basePathReal) !== 0
                || (strlen($pluginDir) > $len && $pluginDir[$len] !== $sep)) {
                return false;
            }
            if (! self::deletePluginDirectory($pluginDir)) {
                return false;
            }
        }

        if (self::tableExists()) {
            PluginModel::where('slug', $slug)->delete();
        }
        self::clearRouteCacheIfCached();

        return true;
    }

    /**
     * Recursively delete a directory. Makes files/dirs writable first to avoid failures on Windows.
     */
    private static function deletePluginDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full)) {
                if (! is_link($full) && ! self::deletePluginDirectory($full)) {
                    return false;
                }
            } else {
                @chmod($full, 0777);
                if (! @unlink($full) && file_exists($full)) {
                    return false;
                }
            }
        }
        @chmod($dir, 0777);
        if (! @rmdir($dir) && is_dir($dir)) {
            return false;
        }
        return true;
    }

    /**
     * Read and validate plugin.json. Returns manifest array or null.
     *
     * @return array<string, mixed>|null
     */
    public static function readManifest(string $pluginPath): ?array
    {
        $manifestFile = rtrim($pluginPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'plugin.json';
        if (! is_file($manifestFile)) {
            return null;
        }
        $raw = file_get_contents($manifestFile);
        $manifest = json_decode($raw, true);
        if (! is_array($manifest)) {
            return null;
        }
        if (empty($manifest['name'])) {
            $manifest['name'] = basename($pluginPath);
        }
        if (empty($manifest['slug'])) {
            $manifest['slug'] = basename($pluginPath);
        }
        if (empty($manifest['version'])) {
            $manifest['version'] = '1.0.0';
        }
        return $manifest;
    }

    /**
     * Menu items for the sidebar: aggregate from all enabled plugins that have "menu" in manifest.
     * Format: [{ name, href, icon? }, ...]
     *
     * @return array<int, array{name: string, href: string, icon?: string}>
     */
    public static function getMenuItems(): array
    {
        $items = [];
        foreach (self::enabled() as $plugin) {
            $menu = $plugin['menu'] ?? null;
            if (! is_array($menu)) {
                continue;
            }
            foreach ($menu as $entry) {
                if (empty($entry['label']) || empty($entry['href'])) {
                    continue;
                }
                $items[] = [
                    'name' => $entry['label'],
                    'href' => $entry['href'],
                    'icon' => $entry['icon'] ?? null,
                ];
            }
        }
        return $items;
    }

    /**
     * Register a plugin that is on disk but not yet in DB (e.g. extracted manually).
     * Creates the DB record and returns true. Caller should run migrations after.
     */
    public static function register(string $slug): bool
    {
        $installed = collect(self::installed())->keyBy('slug');
        $plugin = $installed->get($slug);
        if (! $plugin) {
            return false;
        }
        if (! self::tableExists()) {
            return false;
        }
        PluginModel::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $plugin['name'],
                'version' => $plugin['version'],
                'is_enabled' => true,
            ]
        );
        self::clearRouteCacheIfCached();

        return true;
    }

    /**
     * Sync DB from disk: insert any new plugin dirs as enabled by default; do not disable existing.
     */
    public static function syncFromDisk(): void
    {
        if (! self::tableExists()) {
            return;
        }
        foreach (self::installed() as $p) {
            PluginModel::firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'version' => $p['version'],
                    'is_enabled' => true,
                ]
            );
        }
    }

    private static function tableExists(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('plugins');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Rotas de plugins são registradas no boot conforme o que está habilitado no banco.
     * Com `php artisan route:cache`, a lista fica congelada até limpar o cache.
     */
    private static function clearRouteCacheIfCached(): void
    {
        try {
            if (app()->routesAreCached()) {
                Artisan::call('route:clear');
            }
        } catch (\Throwable) {
            //
        }
    }
}
