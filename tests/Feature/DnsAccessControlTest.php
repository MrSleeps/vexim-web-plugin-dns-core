<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Services\DnsAccessControl;
use VEximweb\Plugin\DnsCore\Tests\Support\TestUser;

function dnsTestUser(int $id, bool $systemAdmin = false, bool $domainAdmin = false): TestUser
{
    $user = new TestUser();
    $user->forceFill(['id' => $id]);
    $user->systemAdmin = $systemAdmin;
    $user->domainAdmin = $domainAdmin;

    return $user;
}

function setDnsPolicy(string $policy): void
{
    DB::table('vw_settings')->updateOrInsert(
        ['key' => 'domain_admin_dns_access'],
        [
            'value' => $policy,
            'type' => 'string',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    Cache::forget('settings.all');
}

function makeDnsProvider(string $name, ?int $ownerId = null, bool $enabled = true): DnsProvider
{
    return DnsProvider::query()->create([
        'owner_user_id' => $ownerId,
        'name' => $name,
        'type' => 'pdns',
        'is_enabled' => $enabled,
        'is_default' => false,
        'priority' => 0,
    ]);
}

function makeDomain(int $id = 100, string $name = 'example.test'): Domain
{
    DB::table('domains')->insert([
        'domain_id' => $id,
        'domain' => $name,
    ]);

    return Domain::query()->findOrFail($id);
}

it('fails closed for missing or invalid domain admin DNS policy', function () {
    expect(DnsAccessControl::domainAdminPolicy())->toBe(DnsAccessControl::POLICY_DISABLED);

    setDnsPolicy('unexpected-value');

    expect(DnsAccessControl::domainAdminPolicy())->toBe(DnsAccessControl::POLICY_DISABLED);
});

it('limits selectable providers to the configured domain admin policy', function () {
    $admin = dnsTestUser(10, domainAdmin: true);

    $global = makeDnsProvider('Global');
    $own = makeDnsProvider('Own', 10);
    makeDnsProvider('Other', 20);
    makeDnsProvider('Disabled global', null, false);

    setDnsPolicy(DnsAccessControl::POLICY_GLOBAL_ONLY);
    expect(DnsAccessControl::selectableProviders($admin)->pluck('id')->all())
        ->toBe([$global->id]);

    setDnsPolicy(DnsAccessControl::POLICY_GLOBAL_AND_OWN);
    expect(DnsAccessControl::selectableProviders($admin)->orderBy('id')->pluck('id')->all())
        ->toBe([$global->id, $own->id]);

    setDnsPolicy(DnsAccessControl::POLICY_DISABLED);
    expect(DnsAccessControl::selectableProviders($admin)->count())->toBe(0);
});

it('allows domain admins to manage only their own providers and blocks deletion while assigned', function () {
    setDnsPolicy(DnsAccessControl::POLICY_GLOBAL_AND_OWN);

    $admin = dnsTestUser(10, domainAdmin: true);
    $own = makeDnsProvider('Own', 10);
    $global = makeDnsProvider('Global');
    $other = makeDnsProvider('Other', 20);

    expect(DnsAccessControl::canManageProvider($admin, $own))->toBeTrue()
        ->and(DnsAccessControl::canManageProvider($admin, $global))->toBeFalse()
        ->and(DnsAccessControl::canManageProvider($admin, $other))->toBeFalse()
        ->and(DnsAccessControl::canDeleteProvider($admin, $own))->toBeTrue();

    DnsDomain::query()->create([
        'domain_id' => 100,
        'provider_id' => $own->id,
        'is_active' => true,
        'service_control' => DnsAccessControl::SERVICE_FULL,
        'record_control' => DnsAccessControl::RECORD_DOMAIN_ADMIN,
    ]);

    expect(DnsAccessControl::canDeleteProvider($admin, $own))->toBeFalse();
});

it('enforces service and record controls per administered domain', function () {
    setDnsPolicy(DnsAccessControl::POLICY_GLOBAL_ONLY);

    $admin = dnsTestUser(10, domainAdmin: true);
    $domain = makeDomain();

    DB::table('vw_domain_user')->insert([
        'user_id' => 10,
        'domain_id' => 100,
        'role' => 'domain_admin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $provider = makeDnsProvider('Global');
    $dnsDomain = DnsDomain::query()->create([
        'domain_id' => 100,
        'provider_id' => $provider->id,
        'is_active' => true,
        'service_control' => DnsAccessControl::SERVICE_SYSTEM_ONLY,
        'record_control' => DnsAccessControl::RECORD_SYSTEM_ONLY,
    ]);

    expect(DnsAccessControl::canChangeProvider($admin, $domain, $dnsDomain))->toBeFalse()
        ->and(DnsAccessControl::canToggleService($admin, $domain, $dnsDomain))->toBeFalse()
        ->and(DnsAccessControl::canEditRecords($admin, $domain, $dnsDomain))->toBeFalse();

    $dnsDomain->service_control = DnsAccessControl::SERVICE_TOGGLE;
    expect(DnsAccessControl::canChangeProvider($admin, $domain, $dnsDomain))->toBeFalse()
        ->and(DnsAccessControl::canToggleService($admin, $domain, $dnsDomain))->toBeTrue();

    $dnsDomain->service_control = DnsAccessControl::SERVICE_FULL;
    $dnsDomain->record_control = DnsAccessControl::RECORD_DOMAIN_ADMIN;
    expect(DnsAccessControl::canChangeProvider($admin, $domain, $dnsDomain))->toBeTrue()
        ->and(DnsAccessControl::canToggleService($admin, $domain, $dnsDomain))->toBeTrue()
        ->and(DnsAccessControl::canEditRecords($admin, $domain, $dnsDomain))->toBeTrue();

    setDnsPolicy(DnsAccessControl::POLICY_DISABLED);
    expect(DnsAccessControl::canChangeProvider($admin, $domain, $dnsDomain))->toBeFalse()
        ->and(DnsAccessControl::canToggleService($admin, $domain, $dnsDomain))->toBeFalse()
        ->and(DnsAccessControl::canEditRecords($admin, $domain, $dnsDomain))->toBeFalse();
});

it('always allows system administrators while keeping provider relationships nullable', function () {
    $systemAdmin = dnsTestUser(1, systemAdmin: true);
    $domain = makeDomain();

    $provider = makeDnsProvider('Global');
    $dnsDomain = DnsDomain::query()->create([
        'domain_id' => 100,
        'provider_id' => $provider->id,
        'is_active' => true,
        'service_control' => DnsAccessControl::SERVICE_SYSTEM_ONLY,
        'record_control' => DnsAccessControl::RECORD_SYSTEM_ONLY,
    ]);

    expect(DnsAccessControl::canChangeProvider($systemAdmin, $domain, $dnsDomain))->toBeTrue()
        ->and(DnsAccessControl::canToggleService($systemAdmin, $domain, $dnsDomain))->toBeTrue()
        ->and(DnsAccessControl::canEditRecords($systemAdmin, $domain, $dnsDomain))->toBeTrue()
        ->and($dnsDomain->provider()->getRelated())->toBeInstanceOf(DnsProvider::class);

    $dnsDomain->setRelation('provider', null);
    expect($dnsDomain->provider)->toBeNull();
});
