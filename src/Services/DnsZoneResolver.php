<?php

namespace VEximweb\Plugin\DnsCore\Services;

use VEximweb\Plugin\DnsCore\Contracts\DnsClient;
use VEximweb\Plugin\DnsCore\ValueObjects\DnsZoneResolution;

class DnsZoneResolver
{
    /** @var array<int, array<int, string>> */
    protected array $zones = [];

    /** @var array<int, array<string, array<int, array<string, mixed>>>> */
    protected array $records = [];

    public function resolve(DnsClient $client, string $domain): DnsZoneResolution
    {
        $domain = $this->normalizeName($domain);

        if ($domain === '') {
            return DnsZoneResolution::missing();
        }

        $managedZone = $this->longestManagedZone($client, $domain);

        if ($managedZone === null) {
            return DnsZoneResolution::missing();
        }

        if ($managedZone === $domain) {
            return DnsZoneResolution::managed($managedZone);
        }

        $delegation = $this->delegationBetween($client, $managedZone, $domain);

        if ($delegation !== null) {
            return DnsZoneResolution::delegated($delegation);
        }

        return DnsZoneResolution::managed($managedZone);
    }

    protected function longestManagedZone(DnsClient $client, string $domain): ?string
    {
        $matches = array_filter(
            $this->zonesFor($client),
            fn (string $zone): bool => $this->isWithin($domain, $zone),
        );

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $matches[0];
    }

    protected function delegationBetween(DnsClient $client, string $zone, string $domain): ?string
    {
        $delegations = [];

        foreach ($this->recordsFor($client, $zone) as $record) {
            if (strtoupper((string) ($record['type'] ?? '')) !== 'NS') {
                continue;
            }

            $name = $this->normalizeName((string) ($record['name'] ?? ''));

            if ($name === '' || $name === $zone) {
                continue;
            }

            if (! $this->isWithin($name, $zone) || ! $this->isWithin($domain, $name)) {
                continue;
            }

            $delegations[] = $name;
        }

        if ($delegations === []) {
            return null;
        }

        usort($delegations, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $delegations[0];
    }

    /** @return array<int, string> */
    protected function zonesFor(DnsClient $client): array
    {
        $key = spl_object_id($client);

        if (! array_key_exists($key, $this->zones)) {
            $this->zones[$key] = collect($client->getZones())
                ->map(fn (array $zone): string => $this->normalizeName((string) ($zone['name'] ?? $zone['id'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->zones[$key];
    }

    /** @return array<int, array<string, mixed>> */
    protected function recordsFor(DnsClient $client, string $zone): array
    {
        $clientKey = spl_object_id($client);

        if (! isset($this->records[$clientKey][$zone])) {
            $this->records[$clientKey][$zone] = $client->getRecords($zone);
        }

        return $this->records[$clientKey][$zone];
    }

    protected function normalizeName(string $name): string
    {
        return strtolower(rtrim(trim($name), '.'));
    }

    protected function isWithin(string $name, string $zone): bool
    {
        return $name === $zone || str_ends_with($name, '.'.$zone);
    }
}
