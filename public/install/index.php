<?php

/**
 * Getfy Installer Wizard - Entry Point
 * Standalone installer, no Laravel dependency.
 */

declare(strict_types=1);

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

// Já instalado: redireciona para /
if ($isAppInstalled($basePath)) {
    header('Location: /');
    exit(302);
}

$step = max(1, min(4, (int) ($_GET['step'] ?? $_POST['step'] ?? 1)));
$logoUrl = 'https://cdn.getfy.cloud/collapsed-logo.png';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/install', PHP_URL_PATH) ?: '/install';
    $basePath = rtrim($reqPath, '/');
    if (!str_ends_with($basePath, '/install')) {
        $basePath = '/install';
    }
    $basePath = rtrim($basePath, '/') . '/';
    $baseUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;
    ?>
    <base href="<?= htmlspecialchars($baseUrl) ?>">
    <title>Instalar Getfy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#c8fa64',
                    }
                }
            }
        }
    </script>
    <style>
        .step-active { background-color: #c8fa64; color: #18181b; }
        .step-done { background-color: #22c55e; color: white; }
        input:focus, select:focus { outline: none; box-shadow: 0 0 0 2px rgba(200, 250, 100, 0.3); }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-white">
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-10">
        <div class="w-full max-w-xl">
            <div class="text-center mb-8">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Getfy" class="mx-auto mb-6 h-14 w-auto object-contain" />
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Instalação Getfy</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Configure sua plataforma em poucos passos</p>
            </div>

            <!-- Stepper -->
            <div class="flex justify-between mb-8">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="flex items-center flex-1">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-all
                        <?= $i < $step ? 'step-done' : ($i === $step ? 'step-active' : 'bg-zinc-300 dark:bg-zinc-600 text-zinc-500') ?>">
                        <?= $i < $step ? '✓' : $i ?>
                    </div>
                    <?php if ($i < 4): ?><div class="flex-1 h-0.5 mx-1 bg-zinc-300 dark:bg-zinc-600"></div><?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>

            <div id="step-content">
                <?php
                $stepFile = __DIR__ . '/steps/step' . $step . '.php';
                if (file_exists($stepFile)) {
                    include $stepFile;
                } else {
                    echo '<p>Etapa ' . $step . ' não encontrada.</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <script src="install.js"></script>
</body>
</html>
