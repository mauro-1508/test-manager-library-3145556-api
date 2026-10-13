<?php

namespace App\Bootstrap\Composition;

use Illuminate\Support\ServiceProvider;

/**
 * Single composition point: the only place where a port meets its adapter (constitution, Art. III).
 */
class PortBindings extends ServiceProvider
{
    public function register(): void
    {
        // Port-to-adapter bindings are added here as each adapter is built.
    }
}
