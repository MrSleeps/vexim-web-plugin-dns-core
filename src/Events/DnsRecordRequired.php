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

        $domainName = rtrim((string) ($domain->ownerDomain?->domain ?? $domain->domain_name), '.');
        $zoneName = $domain->authoritativeZoneName();

        $this->zone = rtrim($zoneName, '.').'.';
        $this->name = $this->qualifyRecordName($name, $domainName);
        $this->type = $type;
        $this->content = $content;
        $this->ttl = $ttl;
        $this->operation = $operation;
        $this->recordId = $recordId;
    }

    protected function qualifyRecordName(string $name, string $domainName): string
    {
        $name = rtrim(trim($name), '.');

        if ($name === '' || $name === '@') {
            return $domainName;
        }

        $normalizedName = strtolower($name);
        $normalizedDomain = strtolower($domainName);

        if ($normalizedName === $normalizedDomain || str_ends_with($normalizedName, '.'.$normalizedDomain)) {
            return $name;
        }

        return $name.'.'.$domainName;
    }
}
