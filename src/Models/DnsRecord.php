<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $domain_id
 * @property string|null $provider_record_id
 * @property string $name
 * @property string $type
 * @property string $content
 * @property int $ttl
 * @property int|null $priority
 * @property string $status
 * @property array|null $metadata
 * @property-read DnsDomain|null $domain
 * @property-read string $full_name
 */
class DnsRecord extends Model
{
    protected $table = 'vw_dns_records';

    protected $fillable = [
        'domain_id', 'provider_record_id', 'name', 'type',
        'content', 'ttl', 'priority', 'status', 'metadata',
    ];

    protected $casts = [
        'ttl' => 'integer',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(DnsDomain::class, 'domain_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSynced($query)
    {
        return $query->where('status', 'synced');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function syncToProvider(): bool
    {
        $domain = $this->domain;

        if (! $domain) {
            throw new LogicException("DNS record {$this->id} has no DNS domain.");
        }

        $client = $domain->getClient();
        $zone = $domain->zone_id ?? $domain->domain_name;

        try {
            $result = match ($this->status) {
                'pending', 'update' => $client->createRecord(
                    $zone,
                    $this->name,
                    $this->type,
                    $this->content,
                    $this->ttl,
                    $this->priority,
                ),
                'delete' => $this->provider_record_id !== null
                    && $client->deleteRecord($zone, $this->provider_record_id),
                default => true,
            };

            if ($result) {
                $this->update(['status' => 'synced']);
            }

            return $result;
        } catch (\Exception $e) {
            $this->update([
                'status' => 'failed',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            return false;
        }
    }

    public function getFullNameAttribute(): string
    {
        $domain = $this->domain;

        if (! $domain) {
            return $this->name;
        }

        if ($this->name === '@' || $this->name === $domain->domain_name) {
            return $domain->domain_name;
        }

        if (str_ends_with($this->name, $domain->domain_name)) {
            return $this->name;
        }

        return $this->name.'.'.$domain->domain_name;
    }
}
