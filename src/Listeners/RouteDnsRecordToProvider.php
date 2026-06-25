<?php
namespace VEximweb\Plugin\DnsCore\Listeners;

use VEximweb\Plugin\DnsCore\Events\DnsRecordRequired;
use VEximweb\Plugin\DnsCore\Factories\DnsClientFactory;

class RouteDnsRecordToProvider
{
    protected $factory;
    
    public function __construct(DnsClientFactory $factory)
    {
        $this->factory = $factory;
    }
    
    public function handle(DnsRecordRequired $event)
    {
        // Get the provider for this domain
        $provider = $event->domain->provider;
        
        if (!$provider || !$provider->is_enabled) {
            throw new \Exception("No enabled DNS provider found for domain");
        }
        
        // Create the appropriate client for this provider
        $client = $this->factory->make($provider, $event->domain);
        
        // Execute the operation
        switch ($event->operation) {
            case 'create':
                return $client->createRecord(
                    $event->zone,
                    $event->name,
                    $event->type,
                    $event->content,
                    $event->ttl
                );
            case 'delete':
                return $client->deleteRecord($event->zone, $event->recordId);
            default:
                throw new \Exception("Unknown operation: {$event->operation}");
        }
    }
}
