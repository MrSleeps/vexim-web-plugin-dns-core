<?php

namespace VEximweb\Plugin\DnsCore;

use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use VEximweb\Plugin\DnsCore\Commands\SyncDomainsToDnsProvider;
use VEximweb\Plugin\DnsCore\Events\RegisterDnsClients;
use VEximweb\Plugin\DnsCore\Factories\DnsClientFactory;
use VEximweb\Plugin\DnsCore\Services\DnsProviderDiscoveryService;

class DnsCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dns.php', 'dns');

        $this->commands($this->getCommands());

        $this->app->singleton(DnsProviderDiscoveryService::class, function () {
            return new DnsProviderDiscoveryService;
        });

        Panel::configureUsing(function (Panel $panel) {
            $panel->plugin(DnsCorePlugin::make());
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app->singleton(DnsClientFactory::class, function () {
            $factory = new DnsClientFactory;

            if (class_exists(RegisterDnsClients::class)) {
                Event::dispatch(new RegisterDnsClients($factory));
            }

            return $factory;
        });

        $this->registerDnsEventListeners();

        $this->app->booted(function () {
            $discoveryService = $this->app->make(DnsProviderDiscoveryService::class);
            $discoveryService->boot();

            $domainFormClass = 'VEximweb\\Core\\Domain\\Filament\\Resources\\Schemas\\DomainForm';

            if (! class_exists($domainFormClass)) {
                Log::debug('DomainForm class not found, skipping extension');

                return;
            }

            $extend = [$domainFormClass, 'extend'];

            if (! is_callable($extend)) {
                Log::error('DomainForm::extend() method not found');

                return;
            }

            $extensions = $discoveryService->getFormExtensions();

            foreach ($extensions as $extension) {
                call_user_func($extend, $extension['components'], $extension['onSave']);
            }

            Log::info('DNS form extensions applied', ['count' => count($extensions)]);
        });
    }

    protected function registerDnsEventListeners(): void
    {
        if (! class_exists(\App\Events\DkimKeyGenerated::class)) {
            return;
        }

        if (class_exists(Events\DnsRecordRequired::class)) {
            Event::listen(
                Events\DnsRecordRequired::class,
                [Listeners\RouteDnsRecordToProvider::class, 'handle'],
            );
        }
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            SyncDomainsToDnsProvider::class,
        ];
    }
}
