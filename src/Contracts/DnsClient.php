<?php

namespace VEximweb\Plugin\DnsCore\Contracts;

use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;

interface DnsClient
{
    public function __construct(DnsProvider $provider, ?DnsDomain $domain = null);
    
    // Zone operations
    public function zoneExists(string $zone): bool;
    public function createZone(string $zone, array $options = []): bool;
    public function deleteZone(string $zone): bool;
    public function getZones(): array;
    
    // Record operations
    public function getRecords(string $zone): array;
    public function createRecord(string $zone, string $name, string $type, string $content, int $ttl = 3600, ?int $priority = null): bool;
    public function deleteRecord(string $zone, string $recordId): bool;
    
    // Connection
    public function testConnection(): bool;
    public function isEnabled(): bool;
}
