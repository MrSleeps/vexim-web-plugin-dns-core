<?php

namespace VEximweb\Plugin\DnsCore\Services;

use Illuminate\Support\Collection;
use VEximweb\Plugin\DnsCore\Contracts\DnsProviderPlugin;
use Illuminate\Support\Facades\Log;

class DnsProviderDiscoveryService
{
    protected Collection $providers;
    protected array $pendingRegistrations = [];
    protected bool $isBooted = false;

    /**
     * @var array<int, array{components: \Closure, onSave: \Closure}>
     */
    protected array $formExtensions = [];

    public function __construct()
    {
        $this->providers = collect();
        
        // Don't auto-discover in constructor - wait until service provider booted
        Log::debug('DnsProviderDiscoveryService constructed');
    }
    
    /**
     * Call this after all service providers have booted
     */
    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }
        
        $this->isBooted = true;
        
        // Process any pending registrations
        foreach ($this->pendingRegistrations as $registration) {
            $this->registerPlugin($registration['class'], $registration['metadata']);
        }
        $this->pendingRegistrations = [];
        
        // Load from config
        $this->loadFromConfig();
        
        Log::info('DnsProviderDiscoveryService booted', [
            'total_providers' => $this->providers->count(),
            'providers' => $this->getProviderOptions()
        ]);
    }
    
    /**
     * Queue a plugin for registration (for early registrations)
     */
    public function queueRegistration(string $providerClass, array $metadata = []): void
    {
        if ($this->isBooted) {
            // Already booted, register immediately
            $this->registerPlugin($providerClass, $metadata);
        } else {
            // Queue for later
            $this->pendingRegistrations[] = [
                'class' => $providerClass,
                'metadata' => $metadata,
            ];
            Log::debug('Queued DNS provider registration', ['class' => $providerClass]);
        }
    }
    
    /**
     * Register a DNS provider plugin
     */
    public function registerPlugin(string $providerClass, array $metadata = []): void
    {
        // Validate the class implements the required interface
        if (!is_subclass_of($providerClass, DnsProviderPlugin::class)) {
            Log::error('Invalid DNS provider plugin', [
                'class' => $providerClass,
                'error' => 'Does not implement DnsProviderPlugin'
            ]);
            throw new \InvalidArgumentException(
                sprintf(
                    'DNS Provider %s must implement %s',
                    $providerClass,
                    DnsProviderPlugin::class
                )
            );
        }
        
        $type = $providerClass::getType();
        
        // Store the provider
        if (!$this->providers->has($type)) {
            $this->providers[$type] = [
                'class' => $providerClass,
                'metadata' => $metadata,
                'registered_at' => now(),
            ];
            
            Log::info("DNS Provider registered: {$type} - {$providerClass::getName()}");
        }
    }

    /**
     * Allow a DNS provider plugin to register extra DomainForm fields
     * and save logic, without dns-core knowing the plugin's name.
     */
    public function registerFormExtension(\Closure $components, \Closure $onSave): void
    {
        $this->formExtensions[] = [
            'components' => $components,
            'onSave' => $onSave,
        ];

        Log::debug('DNS form extension registered', [
            'total_extensions' => count($this->formExtensions),
        ]);
    }

    /**
     * @return array<int, array{components: \Closure, onSave: \Closure}>
     */
    public function getFormExtensions(): array
    {
        return $this->formExtensions;
    }
    
    /**
     * Load plugins from config file

     */
    protected function loadFromConfig(): void
    {
        $configPlugins = config('dns-providers.plugins', []);
        
        foreach ($configPlugins as $providerClass) {
            if (is_string($providerClass) && class_exists($providerClass)) {
                $this->registerPlugin($providerClass, ['source' => 'config']);
            }
        }
    }
    
    /**
     * Get all registered provider classes
     */
    public function getAllProviders(): Collection
    {
        return $this->providers->map(fn($provider) => $provider['class']);
    }
    
    /**
     * Get provider options for select dropdown
     */
    public function getProviderOptions(): array
    {
        $options = [];
        
        foreach ($this->providers as $type => $provider) {
            $class = $provider['class'];
            $options[$type] = $class::getName();
        }
        
        return $options;
    }
    
    /**
     * Get a specific provider class by type
     */
    public function getProvider(string $type): ?string
    {
        $provider = $this->providers[$type] ?? null;
        return $provider ? $provider['class'] : null;
    }
    
    /**
     * Check if a provider is registered
     */
    public function hasProvider(string $type): bool
    {
        return isset($this->providers[$type]);
    }
    
    /**
     * Debug method
     */
    public function debug(): array
    {
        return [
            'is_booted' => $this->isBooted,
            'provider_count' => $this->providers->count(),
            'providers' => $this->providers->map(function ($provider, $type) {
                return [
                    'type' => $type,
                    'class' => $provider['class'],
                    'name' => $provider['class']::getName(),
                ];
            })->values()->toArray(),
            'options' => $this->getProviderOptions(),
            'pending_registrations' => count($this->pendingRegistrations),
            'form_extensions' => count($this->formExtensions),
        ];
    }
}