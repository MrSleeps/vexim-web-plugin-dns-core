<?php

namespace VEximweb\Plugin\DnsCore;

use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Contracts\DnsClient;

class DnsClientResolver
{
    protected array $clients = [];
    protected array $bindings = [];
    
    public function register(string $type, string $clientClass): void
    {
        if (!is_subclass_of($clientClass, DnsClient::class)) {
            throw new \Exception("Client must implement DnsClient interface");
        }
        
        $this->bindings[$type] = $clientClass;
    }
    
    public function make(DnsProvider $provider, ?DnsDomain $domain = null): DnsClient
    {
        if (!isset($this->bindings[$provider->type])) {
            throw new \Exception("No client registered for provider type: {$provider->type}");
        }
        
        $key = $provider->id . ($domain->id ?? '');
        
        if (!isset($this->clients[$key])) {
            $this->clients[$key] = new $this->bindings[$provider->type]($provider, $domain);
        }
        
        return $this->clients[$key];
    }
    
    public function getRegisteredTypes(): array
    {
        return array_keys($this->bindings);
    }
}
