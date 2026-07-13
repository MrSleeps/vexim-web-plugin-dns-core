<?php
namespace VEximweb\Plugin\DnsCore\Listeners;

use Illuminate\Support\Facades\Event;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Events\DnsRecordRequired;

class HandleMtaStsForDns
{
    public function handle($event): void
    {
        // Normalize zone name - ensure it has trailing dot for PowerDNS
        $zone = $event->zone;
        if (!str_ends_with($zone, '.')) {
            $zone = $zone . '.';
        }
        \Log::debug('MTASTS LISTENER CALLED');
        // Find DNS domain by zone name (with or without trailing dot)
        $dnsDomain = DnsDomain::whereHas('ownerDomain', function($query) use ($zone) {
            // Remove trailing dot for database lookup
            $domainName = rtrim($zone, '.');
            $query->where('domain', $domainName);
        })->first();
        
        if (!$dnsDomain) {
            \Illuminate\Support\Facades\Log::warning('No DNS domain found for MTASTS event', [
                'zone' => $event->zone
            ]);
            return;
        }
        \Log::debug("DNS ZONE UPDATE FOR DOMAIN: ".$dnsDomain);
        // Dispatch internal DNS event with normalized zone
        Event::dispatch(new DnsRecordRequired(
            domain: $dnsDomain,
            name: $event->name,
            type: $event->type,
            content: $event->content,
            ttl: $event->ttl,
            operation: $event->operation,
            recordId: $event->recordId
        ));
    }
}