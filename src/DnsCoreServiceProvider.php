<?php
namespace VEximweb\Plugin\DnsCore;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Filament\Panel;
use VEximweb\Plugin\DnsCore\Services\DnsProviderDiscoveryService;
use VEximweb\Plugin\DnsCore\Factories\DnsClientFactory;
use VEximweb\Plugin\DnsCore\Events\RegisterDnsClients;

class DnsCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dns.php', 'dns');

        $this->app->singleton(DnsProviderDiscoveryService::class, function ($app) {
            return new DnsProviderDiscoveryService();
        });

        Panel::configureUsing(function (Panel $panel) {
            $panel->plugin(DnsCorePlugin::make());
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app->singleton(DnsClientFactory::class, function ($app) {
            $factory = new DnsClientFactory();

            if (class_exists(RegisterDnsClients::class)) {
                Event::dispatch(new RegisterDnsClients($factory));
            }

            return $factory;
        });

        $this->registerDnsEventListeners();

        $this->app->booted(function () {
            $discoveryService = $this->app->make(DnsProviderDiscoveryService::class);
            $discoveryService->boot();

            if (! class_exists(\VEximweb\Core\Domain\Filament\Resources\Schemas\DomainForm::class)) {
                Log::debug('DomainForm class not found, skipping extension');
                return;
            }

            if (! method_exists(\VEximweb\Core\Domain\Filament\Resources\Schemas\DomainForm::class, 'extend')) {
                Log::error('DomainForm::extend() method not found');
                return;
            }

            $extensions = $discoveryService->getFormExtensions();

            foreach ($extensions as $extension) {
                \VEximweb\Core\Domain\Filament\Resources\Schemas\DomainForm::extend(
                    components: $extension['components'],
                    onSave: $extension['onSave'],
                );
            }

            Log::info('DNS form extensions applied', ['count' => count($extensions)]);
        });
    }

    protected function registerDnsEventListeners(): void
    {
        if (!class_exists(\App\Events\DkimKeyGenerated::class)) {
            return;
        }
        
        if (class_exists(Events\DnsRecordRequired::class)) {
            Event::listen(
                Events\DnsRecordRequired::class,
                [Listeners\RouteDnsRecordToProvider::class, 'handle']
            );
        }
    }
}