<?php
namespace VEximweb\Plugin\DnsCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;

class DnsRecordRequired
{
    use Dispatchable, SerializesModels;
    
    public DnsDomain $domain;
    public string $zone;
    public string $name;
    public string $type;
    public string $content;
    public int $ttl;
    public string $operation;
    public ?string $recordId;
    
    public function __construct(
        DnsDomain $domain,
        string $name,
        string $type,
        string $content,
        int $ttl = 3600,
        string $operation = 'create',
        ?string $recordId = null
    ) {
        $this->domain = $domain;
        
        // Get the domain name and add trailing dot for PowerDNS
        $domainName = $domain->ownerDomain->domain ?? (string) $domain->domain_id;
        $this->zone = rtrim($domainName, '.') . '.';
        
        $this->name = $name;
        $this->type = $type;
        $this->content = $content;
        $this->ttl = $ttl;
        $this->operation = $operation;
        $this->recordId = $recordId;
    }
}