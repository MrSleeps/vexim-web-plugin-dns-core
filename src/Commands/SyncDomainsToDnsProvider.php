<?php

namespace VEximweb\Plugin\DnsCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Services\DnsZoneResolver;

class SyncDomainsToDnsProvider extends Command
{
    protected $signature = 'vw:sync-domains-to-dns
                            {--provider= : The ID of the DNS provider to use (must be type pdns)}
                            {--force : Force overwrite existing DNS domain entries}';

    protected $description = 'Sync all domains to DNS provider (only providers of type pdns)';

    public function handle(): int
    {
        /** @var EloquentCollection<int, DnsProvider> $providers */
        $providers = DnsProvider::query()
            ->where('type', 'pdns')
            ->where('is_enabled', true)
            ->orderBy('priority', 'desc')
            ->get();

        if ($providers->isEmpty()) {
            $this->error('No enabled DNS providers of type "pdns" found.');

            return 1;
        }

        $provider = $this->selectProvider($providers);

        if (! $provider) {
            $this->error('No provider selected.');

            return 1;
        }

        $this->info("Selected provider: {$provider->name} (ID: {$provider->id})");

        /** @var EloquentCollection<int, Domain> $domains */
        $domains = Domain::query()->get();
        $this->info("Found {$domains->count()} domains to process.");

        if ($domains->isEmpty()) {
            $this->info('No domains found to sync.');

            return 0;
        }

        if (! $this->confirm("Do you want to sync all {$domains->count()} domains to '{$provider->name}'?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->syncDomains($domains, $provider);

        $this->info('Domain sync completed successfully!');

        return 0;
    }

    /**
     * @param EloquentCollection<int, DnsProvider> $providers
     */
    protected function selectProvider(EloquentCollection $providers): ?DnsProvider
    {
        if ($this->option('provider')) {
            $provider = $providers->firstWhere('id', (int) $this->option('provider'));

            if ($provider instanceof DnsProvider) {
                return $provider;
            }

            $this->warn("Provider with ID {$this->option('provider')} not found or not enabled.");
        }

        if ($providers->count() === 1) {
            $provider = $providers->first();

            if ($provider instanceof DnsProvider) {
                $this->info("Only one provider available, automatically selected: {$provider->name}");

                return $provider;
            }
        }

        $choices = $providers
            ->map(fn (DnsProvider $provider): string =>
                "{$provider->name} (ID: {$provider->id}) - Priority: {$provider->priority}" .
                ($provider->is_default ? ' [DEFAULT]' : ''))
            ->values()
            ->all();

        $defaultProvider = $providers->firstWhere('is_default', true);
        $defaultIndex = $defaultProvider instanceof DnsProvider
            ? $providers->search(fn (DnsProvider $provider): bool => $provider->is($defaultProvider))
            : 0;

        if ($defaultIndex === false) {
            $defaultIndex = 0;
        }

        $choice = $this->choice('Select a DNS provider to use:', $choices, $defaultIndex);

        preg_match('/\(ID: (\d+)\)/', $choice, $matches);
        $providerId = isset($matches[1]) ? (int) $matches[1] : null;

        $provider = $providerId ? $providers->firstWhere('id', $providerId) : null;

        return $provider instanceof DnsProvider ? $provider : null;
    }

    /**
     * @param EloquentCollection<int, Domain> $domains
     */
    protected function syncDomains(EloquentCollection $domains, DnsProvider $provider): void
    {
        $bar = $this->output->createProgressBar($domains->count());
        $bar->start();

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        $client = $provider->getClient();
        $resolver = new DnsZoneResolver;

        DB::beginTransaction();

        try {
            foreach ($domains as $domain) {
                $domainId = (int) $domain->getKey();
                $domainName = rtrim((string) $domain->getAttribute('domain'), '.');

                try {
                    $resolution = $resolver->resolve($client, $domainName);

                    if ($resolution->isDelegated()) {
                        $skipped++;
                        $this->line(
                            "\nSkipped: {$domainName} (delegated at {$resolution->delegatedAt}; no managed child zone exists on this provider)"
                        );
                        $bar->advance();

                        continue;
                    }

                    // Preserve the previous behaviour for domains that do not yet have
                    // any matching managed zone. Once a zone exists, the resolver will
                    // automatically prefer the exact or nearest authoritative parent.
                    $zoneName = $resolution->zone ?? $domainName;
                    $existing = DnsDomain::query()->where('domain_id', $domainId)->first();

                    if ($existing) {
                        if ($this->option('force')) {
                            $existing->update([
                                'provider_id' => $provider->id,
                                'zone_id' => $zoneName,
                                'is_active' => true,
                            ]);
                            $synced++;
                            $this->line("\nUpdated: {$domainName} -> {$zoneName}");
                        } else {
                            $skipped++;
                            $this->line("\nSkipped: {$domainName} (already exists, use --force to overwrite)");
                        }
                    } else {
                        DnsDomain::query()->create([
                            'domain_id' => $domainId,
                            'provider_id' => $provider->id,
                            'zone_id' => $zoneName,
                            'settings' => null,
                            'is_active' => true,
                            'last_sync_at' => now(),
                        ]);
                        $synced++;
                        $this->line("\nAdded: {$domainName} -> {$zoneName}");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("\nFailed to process domain: {$domainName} - {$e->getMessage()}");
                }

                $bar->advance();
            }

            DB::commit();

            $bar->finish();
            $this->newLine(2);
            $this->info('Summary:');
            $this->info("Synced: {$synced}");
            $this->info("Skipped: {$skipped}");
            $this->info("Failed: {$failed}");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred while syncing domains: '.$e->getMessage());

            throw $e;
        }
    }
}
