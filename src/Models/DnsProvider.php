<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use VEximweb\Core\Data\Models\User;
use VEximweb\Plugin\DnsCore\Contracts\DnsClient;
use VEximweb\Plugin\DnsCore\Factories\DnsClientFactory;

/**
 * @property int $id
 * @property int|null $owner_user_id
 * @property string $name
 * @property string $type
 * @property string|null $api_url
 * @property string|null $api_key
 * @property array|null $settings
 * @property bool $is_default
 * @property bool $is_enabled
 * @property int $priority
 * @property-read User|null $owner
 */
class DnsProvider extends Model
{
    protected $table = 'vw_dns_providers';

    protected $fillable = [
        'owner_user_id', 'name', 'type', 'api_url', 'api_key', 'settings',
        'is_default', 'is_enabled', 'priority',
    ];

    protected $casts = [
        'owner_user_id' => 'integer',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];

    protected $hidden = ['api_key'];

    public function getApiKeyAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = empty($value)
            ? null
            : Crypt::encryptString($value);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(DnsDomain::class, 'provider_id');
    }

    public function isGlobal(): bool
    {
        return $this->owner_user_id === null;
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getClient(?DnsDomain $domain = null): DnsClient
    {
        return app(\VEximweb\Plugin\DnsCore\DnsClientResolver::class)->make($this, $domain);
    }

    public function testConnection(): bool
    {
        try {
            return $this->getClient()->testConnection();
        } catch (\Exception) {
            return false;
        }
    }
}
