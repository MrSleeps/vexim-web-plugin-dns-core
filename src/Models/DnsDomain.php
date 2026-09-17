<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use VEximweb\Plugin\DnsCore\Contracts\DnsClient;

/**
 * @property int $id
 * @property int $domain_id
 * @property int $provider_id
 * @property string|null $zone_id
 * @property array|null $settings
 * @property bool $is_active
 * @property string $service_control
 * @property string $record_control
 * @property string|null $last_sync_at
 * @property-read DnsProvider|null $provider
 * @property-read \VEximweb\Core\Data\Models\Domain|null $ownerDomain
 * @property-read string $domain_name
 */
class DnsDomain extends Model
{
    protected $table = 'vw_dns_domains';

    protected $fillable = [
        'domain_id', 'provider_id', 'zone_id', 'settings',
        'is_active', 'service_control', 'record_control', 'last_sync_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class, 'provider_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class, 'domain_id');
    }

    public function ownerDomain(): BelongsTo
    {
        return $this->belongsTo(\VEximweb\Core\Data\Models\Domain::class, 'domain_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDomainNameAttribute(): string
    {
        $domain = $this->ownerDomain;

        return $domain ? (string) $domain->getAttribute('domain') : 'Unknown';
    }

    public function getClient(): DnsClient
    {
        $provider = $this->provider;

        if (! $provider) {
            throw new LogicException("DNS domain {$this->id} has no provider.");
        }

        return $provider->getClient($this);
    }

    public function authoritativeZoneName(): string
    {
        return rtrim((string) ($this->zone_id ?: $this->domain_name), '.');
    }

    public function usesSharedParentZone(): bool
    {
        return strtolower($this->authoritativeZoneName()) !== strtolower(rtrim($this->domain_name, '.'));
    }

    public function zoneExists(): bool
    {
        try {
            return $this->getClient()->zoneExists($this->authoritativeZoneName());
        } catch (\Exception) {
            return false;
        }
    }

    public function createZone(array $options = []): bool
    {
        if ($this->usesSharedParentZone()) {
            throw new LogicException(
                "DNS domain {$this->domain_name} is managed inside shared parent zone {$this->authoritativeZoneName()}; refusing to create or replace that zone."
            );
        }

        $client = $this->getClient();
        $zoneName = rtrim($this->domain_name, '.');

        if ($client->createZone($zoneName, $options)) {
            if ($this->zone_id !== $zoneName) {
                $this->update(['zone_id' => $zoneName]);
            }

            return true;
        }

        return false;
    }

    public function deleteZone(): bool
    {
        if ($this->usesSharedParentZone()) {
            throw new LogicException(
                "DNS domain {$this->domain_name} is managed inside shared parent zone {$this->authoritativeZoneName()}; refusing to delete the shared zone."
            );
        }

        return $this->getClient()->deleteZone($this->authoritativeZoneName());
    }

    public function getRecords(): array
    {
        return $this->getClient()->getRecords($this->authoritativeZoneName());
    }

    public function createRecord(string $name, string $type, string $content, int $ttl = 3600, ?int $priority = null): bool
    {
        return $this->getClient()->createRecord(
            zone: $this->authoritativeZoneName(),
            name: $name,
            type: $type,
            content: $content,
            ttl: $ttl,
            priority: $priority,
        );
    }

    public function deleteRecord(string $recordId): bool
    {
        return $this->getClient()->deleteRecord($this->authoritativeZoneName(), $recordId);
    }
}
