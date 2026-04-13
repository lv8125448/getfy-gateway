<?php

/**
 * Getfy Installer API - test-db, install-step (evita timeout dividindo em etapas)
 */

declare(strict_types=1);

@set_time_limit(120);
@ini_set('max_execution_time', '120');
@ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=utf-8');

// Evita output acidental antes do JSON (warnings, notices)
ob_start();

$basePath = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);

/** Verifica se está instalado via .env (APP_INSTALLED=true). Sem .env = não instalado. */
$isAppInstalled = static function (string $base): bool {
    $envPath = $base . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return false;
    }
    $content = file_get_contents($envPath);
    return (bool) preg_match('/^\s*APP_INSTALLED\s*=\s*["\']?true["\']?\s*(?:#|$)/mi', $content);
};

if ($isAppInstalled($basePath)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Aplicação já instalada.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$action = $input['action'] ?? '';

/**
 * Retorna JSON e encerra. Limpa output buffer para evitar resposta vazia.
 */
$sendJson = function ($data): void {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

// Captura erros fatais para retornar JSON em vez de resposta vazia
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Erro PHP: ' . ($err['message'] ?? 'Erro fatal'),
            'log' => ($err['file'] ?? '') . ':' . ($err['line'] ?? 0)
        ], JSON_UNESCAPED_UNICODE);
    }
});

if ($action === 'test-db') {
    $host = $input['db_host'] ?? '127.0.0.1';
    $port = (int) ($input['db_port'] ?? 3306);
    $dbname = $input['db_database'] ?? '';
    $user = $input['db_username'] ?? '';
    $pass = $input['db_password'] ?? '';
    if (empty($dbname) || empty($user)) {
        $sendJson(['success' => false, 'message' => 'Nome do banco e usuário são obrigatórios.']);
    }
    try {
        $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $dbname . ";charset=utf8mb4";
        new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sendJson(['success' => true]);
    } catch (PDOException $e) {
        $sendJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'install-step') {
    @set_time_limit(600);
    @ini_set('max_execution_time', '600');

    $step = (int) ($input['step'] ?? 0);
    if ($step < 1 || $step > 4) {
        $sendJson(['success' => false, 'message' => 'Etapa inválida.']);
    }

    $dbHost = $input['db_host'] ?? '127.0.0.1';
    $dbPort = $input['db_port'] ?? '3306';
    $dbDatabase = $input['db_database'] ?? '';
    $dbUser = $input['db_username'] ?? '';
    $dbPass = $input['db_password'] ?? '';
    $appName = $input['app_name'] ?? 'Getfy';
    $appUrl = rtrim($input['app_url'] ?? '', '/');
    $appEnv = $input['app_env'] ?? 'production';
    $sessionDriver = $input['session_driver'] ?? 'file';

    if (empty($dbDatabase) || empty($dbUser)) {
        $sendJson(['success' => false, 'message' => 'Dados do banco incompletos.']);
    }

    $queueDriver = $sessionDriver;
    $cacheDriver = $sessionDriver === 'redis' ? 'redis' : 'file';
    $envPath = $basePath . '/.env';
    $isDev = ($appEnv === 'local');

    $phpBin = null;
    if (defined('PHP_BINARY') && PHP_BINARY) {
        $bin = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, PHP_BINARY);
        $name = strtolower(basename($bin));
        if (!in_array($name, ['httpd.exe', 'httpd', 'nginx', 'nginx.exe', 'apache.exe', 'apache2.exe'], true)) {
            $phpBin = $bin;
        }
    }
    if (!$phpBin && defined('PHP_BINDIR') && PHP_BINDIR) {
        $tryExe = rtrim(PHP_BINDIR, '\/') . DIRECTORY_SEPARATOR . 'php' . (DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
        if (file_exists($tryExe)) {
            $phpBin = $tryExe;
        }
    }
    if (!$phpBin && DIRECTORY_SEPARATOR === '\\') {
        foreach (['C:\\laragon\\bin\\php', 'C:\\xampp\\php', 'C:\\wamp64\\bin\\php', 'C:\\php'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $versions = @scandir($dir);
            if (!$versions) {
                continue;
            }
            rsort($versions);
            foreach ($versions as $v) {
                if ($v === '.' || $v === '..' || !is_dir($dir . '\\' . $v)) {
                    continue;
                }
                $exe = $dir . '\\' . $v . '\\php.exe';
                if (file_exists($exe)) {
                    $phpBin = $exe;
                    break 2;
                }
            }
        }
    }
    $phpExe = $phpBin ?: 'php';
    $artisan = $basePath . DIRECTORY_SEPARATOR . 'artisan';
    $log = [];
    $procEnv = null;
    if (DIRECTORY_SEPARATOR !== '\\') {
        $path = getenv('PATH') ?: '';
        $procEnv = ['PATH' => '/usr/bin:/usr/local/bin' . ($path ? ':' . $path : '')];
    }
    $run = function ($cmdOrArgs, $cwd = null) use (&$log, $basePath, $phpExe, $procEnv) {
        $cwd = $cwd ?? $basePath;
        $isArray = is_array($cmdOrArgs);
        $cmd = $isArray ? implode(' ', array_map(fn ($a) => strpos($a, ' ') !== false ? '"' . str_replace('"', '""', $a) . '"' : $a, $cmdOrArgs)) : (string) $cmdOrArgs;
        $log[] = '$ ' . $cmd;
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $procCmd = $isArray ? $cmdOrArgs : $cmd;
        $p = @proc_open($procCmd, $descriptor, $pipes, $cwd, $procEnv);
        if (!is_resource($p)) {
            $log[] = 'Erro ao executar comando.';
            return false;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $write = [];
        $except = [];
        do {
            $status = proc_get_status($p);
            if (DIRECTORY_SEPARATOR !== '\\') {
                $read = [$pipes[1], $pipes[2]];
                if (@stream_select($read, $write, $except, 1) > 0) {
                    foreach ($read as $pipe) {
                        $chunk = stream_get_contents($pipe);
                        if ($pipe === $pipes[1]) {
                            $out .= $chunk;
                        } else {
                            $err .= $chunk;
                        }
                    }
                }
            } else {
                $out .= stream_get_contents($pipes[1]);
                $err .= stream_get_contents($pipes[2]);
                usleep(100000);
            }
        } while ($status['running']);
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($out) {
            $log[] = trim($out);
        }
        if ($err) {
            $log[] = trim($err);
        }
        return $code === 0;
    };

    /** Fallback: executa comando no shell via popen (às vezes funciona quando proc_open falha ou PATH está vazio). */
    $runViaPopen = function (string $shellCmd) use (&$log, $basePath) {
        $fullCmd = 'cd ' . escapeshellarg($basePath) . ' && ' . $shellCmd . ' 2>&1';
        $log[] = '(popen) $ ' . $shellCmd;
        $h = @popen($fullCmd, 'r');
        if (!is_resource($h)) {
            $log[] = 'popen falhou.';
            return false;
        }
        $out = stream_get_contents($h);
        $code = pclose($h);
        if ($out) {
            $log[] = trim($out);
        }
        return $code === 0;
    };

    /** Executa Composer em processo (sem proc_open/popen): carrega o phar e roda Application. */
    $runComposerInProcess = function (bool $noDev) use (&$log, $basePath, $sendJson, $logStr) {
        $composerPhar = $basePath . DIRECTORY_SEPARATOR . 'composer.phar';
        if (!is_file($composerPhar)) {
            return false;
        }
        $log[] = 'Executando Composer em processo (phar)...';
        $cwd = getcwd();
        try {
            chdir($basePath);
            putenv('COMPOSER_HOME=' . $basePath . '/.composer');
            $pharUri = 'phar://' . $composerPhar;
            $autoload = $pharUri . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                $autoload = $pharUri . '/autoload.php';
            }
            if (!is_file($autoload)) {
                $log[] = 'Estrutura do phar não reconhecida.';
                return false;
            }
            require $autoload;
            $app = new \Composer\Console\Application();
            $app->setAutoExit(false);
            $input = new \Symfony\Component\Console\Input\ArrayInput([
                'command' => 'install',
                '--no-interaction' => true,
                '--no-dev' => $noDev,
                '--optimize-autoloader' => $noDev,
            ]);
            $output = new \Symfony\Component\Console\Output\StreamOutput(fopen('php://temp', 'w+'));
            $exitCode = $app->run($input, $output);
            rewind($output->getStream());
            $out = stream_get_contents($output->getStream());
            if ($out) {
                $log[] = trim($out);
            }
            return $exitCode === 0;
        } catch (Throwable $e) {
            $log[] = 'Composer em processo: ' . $e->getMessage();
            return false;
        } finally {
            chdir($cwd);
        }
    };

    $logStr = fn () => implode("\n", $log);

    if ($step === 1) {
        $envContent = "APP_NAME=\"" . addslashes($appName) . "\"
APP_ENV={$appEnv}
APP_KEY=
APP_DEBUG=false
APP_URL={$appUrl}
APP_INSTALLED=false

APP_LOCALE=pt
APP_FALLBACK_LOCALE=pt
APP_FAKER_LOCALE=pt_BR

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbDatabase}
DB_USERNAME=" . addslashes($dbUser) . "
DB_PASSWORD=\"" . addslashes($dbPass) . "\"

SESSION_DRIVER={$sessionDriver}
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION={$queueDriver}

CACHE_STORE={$cacheDriver}

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

VITE_APP_NAME=\"" . addslashes($appName) . "\"
PLUGIN_STORE_URL=https://getfy.store

# PWA Painel: chaves VAPID (preenchidas pelo instalador ou php artisan pwa:vapid)
PWA_VAPID_PUBLIC=
PWA_VAPID_PRIVATE=
";
        if (!file_put_contents($envPath, $envContent)) {
            $sendJson(['success' => false, 'message' => 'Não foi possível escrever o arquivo .env.']);
        }
        $composerPhar = $basePath . DIRECTORY_SEPARATOR . 'composer.phar';
        $composerArgs = $isDev
            ? ['install', '--no-interaction']
            : ['install', '--no-interaction', '--no-dev', '--optimize-autoloader'];
        $composerOk = false;

        if (is_file($composerPhar)) {
            $composerOk = $run(array_merge([$phpExe, '-d', 'memory_limit=512M', $composerPhar], $composerArgs));
        }
        if (!$composerOk) {
            $composerOk = $run(array_merge(['composer'], $composerArgs));
        }
        if (!$composerOk && !is_file($composerPhar)) {
            $log[] = 'Baixando Composer (composer.phar)...';
            $phar = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
            if ($phar && strlen($phar) > 10000) {
                if (file_put_contents($composerPhar, $phar)) {
                    $composerOk = $run(array_merge([$phpExe, '-d', 'memory_limit=512M', $composerPhar], $composerArgs));
                }
            } else {
                $log[] = 'Não foi possível baixar composer.phar.';
            }
        }
        if (!$composerOk) {
            $composerCmdStr = ($isDev ? 'composer install --no-interaction' : 'composer install --no-interaction --no-dev --optimize-autoloader');
            $log[] = 'Tentando via popen (shell)...';
            if (is_file($composerPhar)) {
                $composerOk = $runViaPopen('php -d memory_limit=512M composer.phar ' . ($isDev ? 'install --no-interaction' : 'install --no-interaction --no-dev --optimize-autoloader'));
            }
            if (!$composerOk) {
                $composerOk = $runViaPopen($composerCmdStr);
            }
        }
        if (!$composerOk && is_file($composerPhar)) {
            $composerOk = $runComposerInProcess(!$isDev);
        }
        if (!$composerOk && is_file($basePath . '/vendor/autoload.php')) {
            $log[] = '[Fallback] Composer falhou – usando vendor/ existente.';
        }
        if (!$composerOk) {
            $log[] = '[Aviso] Composer não executado (proc_open/popen podem estar desativados). Suba a pasta vendor ou rode composer install depois.';
        }
        $sendJson(['success' => true, 'step' => 1, 'label' => 'Composer concluído']);
    }

    if ($step === 2) {
        if (!is_file($basePath . '/vendor/autoload.php')) {
            $log[] = 'vendor/ não encontrado – executando composer install...';
            $log[] = 'proc_open disponível: ' . (function_exists('proc_open') ? 'sim' : 'não');
            $composerPhar = $basePath . DIRECTORY_SEPARATOR . 'composer.phar';
            $composerArgs = $isDev
                ? ['install', '--no-interaction']
                : ['install', '--no-interaction', '--no-dev', '--optimize-autoloader'];
            $composerOk = false;
            if (!is_file($composerPhar)) {
                $log[] = 'Baixando composer.phar...';
                $phar = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
                if ($phar && strlen($phar) > 10000) {
                    file_put_contents($composerPhar, $phar);
                }
            }
            if (is_file($composerPhar)) {
                $composerOk = $run(array_merge([$phpExe, '-d', 'memory_limit=512M', $composerPhar], $composerArgs));
            }
            if (!$composerOk) {
                $composerOk = $run(array_merge(['composer'], $composerArgs));
            }
            if (!$composerOk) {
                $log[] = 'Tentando via popen (shell)...';
                if (is_file($composerPhar)) {
                    $composerOk = $runViaPopen('php -d memory_limit=512M composer.phar install --no-interaction --no-dev --optimize-autoloader');
                }
                if (!$composerOk) {
                    $composerOk = $runViaPopen('composer install --no-interaction --no-dev --optimize-autoloader');
                }
            }
            if (!$composerOk && is_file($composerPhar)) {
                $composerOk = $runComposerInProcess(true);
            }
            if (!is_file($basePath . '/vendor/autoload.php')) {
                $log[] = '[Aviso] vendor/ não encontrado. Suba a pasta vendor (composer install local + upload) e recarregue, ou use SSH.';
                $manualCmd = 'cd ' . str_replace([' ', '"'], ['\ ', '\"'], $basePath) . ' && php composer.phar install --no-interaction --no-dev --optimize-autoloader';
                $sendJson([
                    'success' => false,
                    'message' => 'Pasta vendor não encontrada. Suba o projeto com a pasta vendor ou rode composer install (SSH) e recarregue.',
                    'log' => $logStr() . "\n\n--- Comando manual (SSH) ---\n" . $manualCmd,
                ]);
            }
        }
        if (!$run([$phpExe, $artisan, 'key:generate', '--force'])) {
            // Fallback: gera APP_KEY manualmente (artisan pode falhar em hospedagens restritas)
            $key = 'base64:' . base64_encode(random_bytes(32));
            $envContent = file_get_contents($envPath);
            $updated = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY=' . $key, $envContent);
            if ($updated === $envContent) {
                $updated = preg_replace('/^(APP_DEBUG=)/m', "APP_KEY={$key}\n$1", $envContent);
            }
            if (!file_put_contents($envPath, $updated)) {
                $sendJson(['success' => false, 'message' => 'Não foi possível definir APP_KEY.', 'log' => $logStr()]);
            }
            $log[] = '[Fallback] APP_KEY gerada manualmente.';
        }
        if (!$run([$phpExe, $artisan, 'migrate', '--force'])) {
            // Fallback: executa migrate em processo (evita proc_open em hospedagens restritas)
            try {
                $log[] = 'Tentando migrate em processo...';
                $autoload = $basePath . '/vendor/autoload.php';
                if (!is_file($autoload)) {
                    throw new Exception('vendor/autoload.php não encontrado.');
                }
                require $autoload;
                $app = require $basePath . '/bootstrap/app.php';
                $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
                $kernel->bootstrap();
                $kernel->call('migrate', ['--force' => true]);
                $output = $kernel->output();
                if ($output) {
                    $log[] = trim($output);
                }
                $log[] = '[Fallback] Migrações executadas em processo.';
            } catch (Throwable $e) {
                $log[] = 'Erro em migrate (fallback): ' . get_class($e) . ' - ' . $e->getMessage();
                $log[] = 'Em: ' . $e->getFile() . ':' . $e->getLine();
                $sendJson(['success' => false, 'message' => 'Falha em migrate: ' . $e->getMessage(), 'log' => $logStr()]);
            }
        }
        $sendJson(['success' => true, 'step' => 2, 'label' => 'Migrações concluídas']);
    }

    if ($step === 3) {
        $npmCmd = 'npm install && npm run build';
        if (!$run($npmCmd)) {
            // Fallback: hospedagem pode não ter Node.js – permite seguir usando public/build pré-compilado
            $log[] = '[Aviso] npm não disponível ou falhou – usando public/build existente.';
        }
        $sendJson(['success' => true, 'step' => 3, 'label' => 'Build concluído']);
    }

    if ($step === 4) {
        $dirs = [
            $basePath . '/storage/framework/cache/data',
            $basePath . '/storage/framework/sessions',
            $basePath . '/storage/framework/views',
            $basePath . '/storage/logs',
            $basePath . '/bootstrap/cache',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0755, true);
            }
        }
        $run([$phpExe, $artisan, 'storage:link']);
        if (!$run([$phpExe, $artisan, 'pwa:vapid'])) {
            try {
                $autoload = $basePath . '/vendor/autoload.php';
                if (is_file($autoload)) {
                    require $autoload;
                    $app = require $basePath . '/bootstrap/app.php';
                    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                    $app->make(\Illuminate\Contracts\Console\Kernel::class)->call('pwa:vapid');
                    $log[] = '[Fallback] Chaves VAPID geradas em processo.';
                }
            } catch (Throwable $e) {
                $log[] = '[Aviso] pwa:vapid falhou: ' . $e->getMessage() . ' – rode depois: php artisan pwa:vapid';
            }
        }
        $cronSecret = bin2hex(random_bytes(24));
        $envContent = file_get_contents($envPath);
        if (!preg_match('/^\s*CRON_SECRET\s*=/mi', $envContent)) {
            file_put_contents($envPath, "\nCRON_SECRET={$cronSecret}\n", FILE_APPEND);
        }
        $envContent = preg_replace('/^APP_INSTALLED\s*=.*$/mi', 'APP_INSTALLED=true', $envContent);
        file_put_contents($envPath, $envContent);
        $run([$phpExe, $artisan, 'config:cache']);
        $run([$phpExe, $artisan, 'route:cache']);
        $run([$phpExe, $artisan, 'view:cache']);
        $storageApp = $basePath . '/storage/app';
        if (!is_dir($storageApp)) {
            @mkdir($storageApp, 0755, true);
        }
        $installPath = __DIR__;
        $parentPath = dirname($installPath);
        $newPath = $parentPath . '/.install';
        if (is_dir($installPath) && !is_dir($newPath)) {
            @rename($installPath, $newPath);
        }
        $sendJson(['success' => true, 'step' => 4, 'redirect' => '/', 'label' => 'Instalação finalizada']);
    }
}

// Ação desconhecida
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
