<?php

namespace App\Plugins;

interface PluginManifest
{
    public function name(): string;

    public function version(): string;

    public function events(): array;
}
