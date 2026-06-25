<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $domain_id
 * @property int $provider_id
 * @property string|null $zone_id
 * @property array|null $settings
 * @property bool $is_active
 * @property string|null $last_sync_at
 * @property-read DnsProvider $provider
 * @property-read Domain|null $ownerDomain
 * @property-read string $domain_name
 */

class DnsDomain extends Model
{
    protected $table = 'vw_dns_domains';
    
    protected $fillable = [
        'domain_id', 'provider_id', 'zone_id', 'settings',
        'is_active', 'last_sync_at'
    ];
    
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];
    
    // Relationships
    public function provider()
    {
        return $this->belongsTo(DnsProvider::class, 'provider_id');
    }
    
    public function records()
    {
        // Note: This doesn't store records locally, just a convenience method
        // to fetch records from the provider
        return $this->hasManyThrough(
            \VEximweb\Plugin\DnsCore\Contracts\DnsRecord::class,
            DnsProvider::class,
            'id',
            'provider_id',
            'provider_id',
            'id'
        );
    }
    
    // Get the main app's domain model
    public function ownerDomain()
    {
        return $this->belongsTo(\VEximweb\Core\Data\Models\Domain::class, 'domain_id');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    // Helpers
    public function getDomainNameAttribute()
    {
        return $this->ownerDomain?->domain ?? 'Unknown';
    }
    
    public function getClient()
    {
        return $this->provider->getClient($this);
    }
    
    // Check if zone exists in provider
    public function zoneExists(): bool
    {
        try {
            $client = $this->getClient();
            return $client->zoneExists($this->zone_id ?? $this->domain_name);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    // Create zone in provider
    public function createZone(array $options = []): bool
    {
        $client = $this->getClient();
        $zoneName = $this->zone_id ?? $this->domain_name;
        
        if ($client->createZone($zoneName, $options)) {
            if (!$this->zone_id) {
                $this->update(['zone_id' => $zoneName]);
            }
            return true;
        }
        
        return false;
    }
    
    // Delete zone from provider
    public function deleteZone(): bool
    {
        $client = $this->getClient();
        return $client->deleteZone($this->zone_id ?? $this->domain_name);
    }
    
    // Get records from provider
    public function getRecords(): array
    {
        $client = $this->getClient();
        return $client->getRecords($this->zone_id ?? $this->domain_name);
    }
    
    // Create a record in provider
    public function createRecord(string $name, string $type, string $content, int $ttl = 3600, ?int $priority = null): bool
    {
        $client = $this->getClient();
        return $client->createRecord(
            zone: $this->zone_id ?? $this->domain_name,
            name: $name,
            type: $type,
            content: $content,
            ttl: $ttl,
            priority: $priority
        );
    }
    
    // Delete a record from provider
    public function deleteRecord(string $recordId): bool
    {
        $client = $this->getClient();
        return $client->deleteRecord($this->zone_id ?? $this->domain_name, $recordId);
    }
}
