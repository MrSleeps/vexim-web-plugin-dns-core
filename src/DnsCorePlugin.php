<?php

namespace VEximweb\Plugin\DnsCore;

use Filament\Contracts\Plugin;
use Filament\Panel;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\DnsProviderResource;

class DnsCorePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'dns-core';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DnsProviderResource::class,
        ]);        
        
    }

    public function boot(Panel $panel): void {}
}
