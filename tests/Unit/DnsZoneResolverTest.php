<?php

use VEximweb\Plugin\DnsCore\Contracts\DnsClient;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Services\DnsZoneResolver;

final class ResolverTestDnsClient implements DnsClient
{
    /** @var array<int, array<string, mixed>> */
    public array $zones = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $records = [];

    public function __construct(DnsProvider $provider, ?DnsDomain $domain = null) {}

    public function zoneExists(string $zone): bool
    {
        return collect($this->zones)->contains(
            fn (array $candidate): bool => rtrim((string) ($candidate['name'] ?? ''), '.') === rtrim($zone, '.'),
        );
    }

    public function createZone(string $zone, array $options = []): bool
    {
        return true;
    }

    public function deleteZone(string $zone): bool
    {
        return true;
    }

    public function getZones(): array
    {
        return $this->zones;
    }

    public function getRecords(string $zone): array
    {
        return $this->records[rtrim($zone, '.')] ?? [];
    }

    public function createRecord(string $zone, string $name, string $type, string $content, int $ttl = 3600, ?int $priority = null): bool
    {
        return true;
    }

    public function deleteRecord(string $zone, string $recordId): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }
}

function resolverClient(array $zones, array $records = []): ResolverTestDnsClient
{
    $client = new ResolverTestDnsClient(new DnsProvider);
    $client->zones = array_map(fn (string $zone): array => ['name' => rtrim($zone, '.').'.'], $zones);
    $client->records = $records;

    return $client;
}

it('uses the exact zone when the subdomain is hosted as its own zone', function () {
    $client = resolverClient(['example.com', 'mail.example.com']);

    $resolution = (new DnsZoneResolver)->resolve($client, 'mail.example.com');

    expect($resolution->zone)->toBe('mail.example.com')
        ->and($resolution->delegatedAt)->toBeNull();
});

it('uses the nearest managed parent zone for an undelegated host', function () {
    $client = resolverClient(['example.com']);

    $resolution = (new DnsZoneResolver)->resolve($client, 'mail.example.com');

    expect($resolution->zone)->toBe('example.com')
        ->and($resolution->delegatedAt)->toBeNull();
});

it('does not cross an external delegation when falling back to a parent zone', function () {
    $client = resolverClient(
        ['example.com'],
        [
            'example.com' => [
                ['name' => 'example.com', 'type' => 'NS', 'content' => 'ns1.example.net.'],
                ['name' => 'mail.example.com', 'type' => 'NS', 'content' => 'ns1.external.test.'],
                ['name' => 'mail.example.com', 'type' => 'NS', 'content' => 'ns2.external.test.'],
            ],
        ],
    );

    $resolution = (new DnsZoneResolver)->resolve($client, 'mx.mail.example.com');

    expect($resolution->zone)->toBeNull()
        ->and($resolution->delegatedAt)->toBe('mail.example.com');
});

it('allows a locally hosted child zone even when the parent delegates it', function () {
    $client = resolverClient(
        ['example.com', 'mail.example.com'],
        [
            'example.com' => [
                ['name' => 'mail.example.com', 'type' => 'NS', 'content' => 'ns1.example.net.'],
            ],
        ],
    );

    $resolution = (new DnsZoneResolver)->resolve($client, 'host.mail.example.com');

    expect($resolution->zone)->toBe('mail.example.com')
        ->and($resolution->delegatedAt)->toBeNull();
});

it('returns no managed zone when the provider has no matching authoritative zone', function () {
    $client = resolverClient(['other.test']);

    $resolution = (new DnsZoneResolver)->resolve($client, 'mail.example.com');

    expect($resolution->zone)->toBeNull()
        ->and($resolution->delegatedAt)->toBeNull();
});
