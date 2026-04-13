<?php

namespace App\Support;

class DockerSetupState
{
    public static function isDocker(): bool
    {
        $raw = getenv('GETFY_DOCKER');
        if ($raw === false) {
            $raw = $_ENV['GETFY_DOCKER'] ?? $_SERVER['GETFY_DOCKER'] ?? null;
        }
        if (filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $dockerenv = '/.dockerenv';
        if (self::isPathAllowedByOpenBasedir($dockerenv) && is_file($dockerenv)) {
            return true;
        }

        $cgroupPath = '/proc/1/cgroup';
        if (self::isPathAllowedByOpenBasedir($cgroupPath) && is_file($cgroupPath)) {
            $cgroup = (string) @file_get_contents($cgroupPath);
            $cgroup = strtolower($cgroup);
            if (str_contains($cgroup, 'docker') || str_contains($cgroup, 'kubepods') || str_contains($cgroup, 'containerd')) {
                return true;
            }
        }

        return false;
    }

    private static function isPathAllowedByOpenBasedir(string $path): bool
    {
        $openBasedir = ini_get('open_basedir');
        if (! is_string($openBasedir) || trim($openBasedir) === '') {
            return true;
        }

        $separator = DIRECTORY_SEPARATOR === '\\' ? ';' : ':';
        $parts = array_values(array_filter(array_map('trim', explode($separator, $openBasedir)), fn ($v) => $v !== ''));
        foreach ($parts as $allowed) {
            if ($allowed === '.' || $allowed === './') {
                continue;
            }
            if ($allowed === '/') {
                return true;
            }
            if (str_starts_with($path, $allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function isSetupDone(): bool
    {
        $doneFile = base_path('.docker/setup.done');
        $urlFile = base_path('.docker/app.url');

        if (! is_file($doneFile) || ! is_file($urlFile)) {
            return false;
        }

        $url = trim((string) @file_get_contents($urlFile));
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return false;
        }

        return true;
    }
}

