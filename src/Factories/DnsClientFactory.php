<?php
namespace VEximweb\Plugin\DnsCore\Factories;

use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Contracts\DnsClient;

class DnsClientFactory
{
    protected $clients = [];
    
    /**
     * Register a DNS client for a provider type
     */
    public function register(string $type, string $clientClass): void
    {
        $this->clients[$type] = $clientClass;
    }
    
    /**
     * Make a DNS client for the given provider
     */
    public function make(DnsProvider $provider, ?DnsDomain $domain = null): DnsClient
    {
        if (!isset($this->clients[$provider->type])) {
            throw new \Exception("No DNS client registered for provider type: {$provider->type}");
        }
        
        $clientClass = $this->clients[$provider->type];
        
        if (!class_exists($clientClass)) {
            throw new \Exception("DNS client class not found: {$clientClass}");
        }
        
        return new $clientClass($provider, $domain);
    }
}
