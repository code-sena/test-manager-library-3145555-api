<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Único punto de composición; los puertos se vincularán cuando existan sus casos de uso.
    }
}
