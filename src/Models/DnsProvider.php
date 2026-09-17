<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use VEximweb\Core\Data\Models\User;

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
 */

class DnsProvider extends Model
{
    protected $table = 'vw_dns_providers';
    
    
    protected $fillable = [
        'owner_user_id', 'name', 'type', 'api_url', 'api_key', 'settings',
        'is_default', 'is_enabled', 'priority'
    ];
    
    protected $casts = [
        'owner_user_id' => 'integer',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];
    
    protected $hidden = ['api_key'];
    
    // Automatically decrypt API key when accessed
    public function getApiKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    // Automatically encrypt API key when set
    public function setApiKeyAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['api_key'] = null;
        } else {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        }
    }
    
    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function domains()
    {
        return $this->hasMany(DnsDomain::class, 'provider_id');
    }

    public function isGlobal(): bool
    {
        return $this->owner_user_id === null;
    }
    
    // Scopes
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
    
    // Get the appropriate client for this provider
    public function getClient(?DnsDomain $domain = null)
    {
        return app(\VEximweb\Plugin\DnsCore\DnsClientResolver::class)->make($this, $domain);
    }
    
    // Test connection to provider
    public function testConnection(): bool
    {
        try {
            $client = $this->getClient();
            return $client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }
}
