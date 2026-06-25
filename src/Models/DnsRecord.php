<?php

namespace VEximweb\Plugin\DnsCore\Models;

use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    protected $table = 'vw_dns_records';
    
    protected $fillable = [
        'domain_id', 'provider_record_id', 'name', 'type',
        'content', 'ttl', 'priority', 'status', 'metadata'
    ];
    
    protected $casts = [
        'ttl' => 'integer',
        'priority' => 'integer',
        'metadata' => 'array',
    ];
    
    // Relationships
    public function domain()
    {
        return $this->belongsTo(DnsDomain::class, 'domain_id');
    }
    
    // Scopes
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
    
    // Sync this record to the DNS provider
    public function syncToProvider(): bool
    {
        $client = $this->domain->getClient();
        
        try {
            $result = match($this->status) {
                'pending' => $client->createRecord($this),
                'update' => $client->updateRecord($this),
                'delete' => $client->deleteRecord($this),
                default => true
            };
            
            if ($result) {
                $this->update(['status' => 'synced']);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->update(['status' => 'failed', 'metadata' => ['error' => $e->getMessage()]]);
            return false;
        }
    }
    
    // Helper to get full record name (including domain)
    public function getFullNameAttribute(): string
    {
        if ($this->name === '@' || $this->name === $this->domain->domain_name) {
            return $this->domain->domain_name;
        }
        
        if (str_ends_with($this->name, $this->domain->domain_name)) {
            return $this->name;
        }
        
        return $this->name . '.' . $this->domain->domain_name;
    }
}
