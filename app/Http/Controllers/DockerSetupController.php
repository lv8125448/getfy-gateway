<?php

namespace App\Http\Controllers;

use App\Support\DockerSetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DockerSetupController extends Controller
{
    private function sanitizeInputUrl(string $value): string
    {
        $v = trim($value);
        $v = str_replace(["\r", "\n", "\t"], '', $v);
        $v = trim($v, " \t\n\r\0\x0B`'\"");

        return trim($v);
    }

    private function isPublicIpHost(string $host): bool
    {
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function normalizeHostFromInput(string $raw, Request $request): string
    {
        $value = strtolower($this->sanitizeInputUrl($raw));
        $value = preg_replace('#\s+#', '', $value) ?: '';

        if ($value === '') {
            return strtolower($request->getHost());
        }

        if (str_contains($value, '://')) {
            $parts = parse_url($value);
            $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        } else {
            $host = $value;
        }

        $host = explode('/', $host)[0] ?? $host;
        $host = explode('?', $host)[0] ?? $host;
        $host = explode('#', $host)[0] ?? $host;
        if (substr_count($host, ':') === 1) {
            $host = explode(':', $host)[0] ?? $host;
        }
        $host = rtrim(trim($host), '.');

        if ($host === '') {
            $host = strtolower($request->getHost());
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? $host : strtolower($request->getHost());
    }

    private function normalizeAppUrlForDocker(string $host, Request $request): string
    {
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return $this->resolveRequestScheme($request).'://'.$host;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! $this->isPublicIpHost($host)) {
            return $this->resolveRequestScheme($request).'://'.$host;
        }

        return 'https://'.$host;
    }

    private function resolveRequestScheme(Request $request): string
    {
        $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        if (str_contains($forwardedProto, 'https')) {
            return 'https';
        }

        $cfVisitor = strtolower((string) $request->headers->get('cf-visitor', ''));
        if (str_contains($cfVisitor, 'https')) {
            return 'https';
        }

        return $request->isSecure() ? 'https' : 'http';
    }

    public function show(Request $request): View
    {
        if (! DockerSetupState::isDocker()) {
            abort(404);
        }
        if (DockerSetupState::isSetupDone()) {
            abort(404);
        }

        $scheme = $this->resolveRequestScheme($request);

        $forwardedHost = (string) $request->headers->get('x-forwarded-host', '');
        $host = trim(explode(',', $forwardedHost)[0] ?? '');
        $host = $host !== '' ? $host : $request->getHost();

        $host = $this->normalizeHostFromInput($host, $request);
        $suggestedUrl = $this->normalizeAppUrlForDocker($host, $request);

        return view('docker-setup', [
            'suggested_url' => $suggestedUrl,
            'host' => $host,
            'scheme' => $scheme,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! DockerSetupState::isDocker()) {
            abort(404);
        }
        if (DockerSetupState::isSetupDone()) {
            abort(404);
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $scheme = $this->resolveRequestScheme($request);

        $host = $this->normalizeHostFromInput((string) $validated['domain'], $request);
        $url = $this->normalizeAppUrlForDocker($host, $request);

        $cronSecret = null;
        $envPath = base_path('.env');
        if (is_file($envPath)) {
            $env = (string) file_get_contents($envPath);
            if (preg_match('/^\s*CRON_SECRET\s*=\s*(.*)\s*$/mi', $env, $m)) {
                $cronSecret = trim((string) ($m[1] ?? ''), " \t\n\r\0\x0B\"'");
            }
        }
        if ($cronSecret === null || $cronSecret === '') {
            $cronSecret = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        }

        $this->setEnv([
            'APP_URL' => $url,
            'DOCKER_SETUP_DONE' => 'true',
            'APP_INSTALLED' => 'true',
            'CRON_SECRET' => $cronSecret,
        ]);

        $dockerDir = base_path('.docker');
        if (! is_dir($dockerDir)) {
            mkdir($dockerDir, 0777, true);
        }
        file_put_contents($dockerDir.DIRECTORY_SEPARATOR.'app.url', $url);
        file_put_contents($dockerDir.DIRECTORY_SEPARATOR.'setup.done', 'true');
        file_put_contents($dockerDir.DIRECTORY_SEPARATOR.'Caddyfile.domains', $host." {\n\treverse_proxy app:80\n}\n");

        return redirect('/login')->with('success', 'Configuração inicial salva.');
    }

    private function setEnv(array $vars): void
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $content = (string) file_get_contents($envPath);
        foreach ($vars as $key => $value) {
            $value = (string) $value;
            $needsQuotes = (bool) preg_match('/\\s|#|"|\\x27/', $value);
            $line = $key.'='.($needsQuotes ? ('"'.str_replace('"', '\\"', $value).'"') : $value);
            $pattern = '/^\s*'.preg_quote($key, '/').'\s*=.*$/m';
            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $line, $content);
            } else {
                $content = rtrim($content, "\r\n")."\n".$line."\n";
            }
        }

        $content = str_replace("\r\n", "\n", $content);
        file_put_contents($envPath, $content);
    }
}
