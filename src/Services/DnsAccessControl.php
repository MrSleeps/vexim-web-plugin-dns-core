<?php

namespace VEximweb\Plugin\DnsCore\Services;

use Illuminate\Database\Eloquent\Builder;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Core\Data\Models\Setting;
use VEximweb\Core\Data\Models\User;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;

final class DnsAccessControl
{
    public const POLICY_DISABLED = 'disabled';
    public const POLICY_GLOBAL_ONLY = 'global_only';
    public const POLICY_GLOBAL_AND_OWN = 'global_and_own';

    public const SERVICE_SYSTEM_ONLY = 'system_only';
    public const SERVICE_TOGGLE = 'toggle';
    public const SERVICE_FULL = 'full';

    public const RECORD_SYSTEM_ONLY = 'system_only';
    public const RECORD_DOMAIN_ADMIN = 'domain_admin';

    public static function domainAdminPolicy(): string
    {
        $policy = (string) Setting::get('domain_admin_dns_access', self::POLICY_DISABLED);

        return in_array($policy, [
            self::POLICY_DISABLED,
            self::POLICY_GLOBAL_ONLY,
            self::POLICY_GLOBAL_AND_OWN,
        ], true) ? $policy : self::POLICY_DISABLED;
    }

    public static function canCreateOwnProviders(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return $user->isDomainAdmin()
            && self::domainAdminPolicy() === self::POLICY_GLOBAL_AND_OWN;
    }

    public static function providerResourceQuery(?User $user): Builder
    {
        $query = DnsProvider::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSystemAdmin()) {
            return $query;
        }

        if (self::canCreateOwnProviders($user)) {
            return $query->where('owner_user_id', $user->getKey());
        }

        return $query->whereRaw('1 = 0');
    }

    public static function selectableProviders(?User $user): Builder
    {
        $query = DnsProvider::query()->enabled();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSystemAdmin()) {
            return $query;
        }

        if (! $user->isDomainAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        return match (self::domainAdminPolicy()) {
            self::POLICY_GLOBAL_ONLY => $query->whereNull('owner_user_id'),
            self::POLICY_GLOBAL_AND_OWN => $query->where(function (Builder $query) use ($user) {
                $query->whereNull('owner_user_id')
                    ->orWhere('owner_user_id', $user->getKey());
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function canManageProvider(?User $user, DnsProvider $provider): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return self::canCreateOwnProviders($user)
            && (int) $provider->owner_user_id === (int) $user->getKey();
    }

    public static function canDeleteProvider(?User $user, DnsProvider $provider): bool
    {
        return self::canManageProvider($user, $provider)
            && ! $provider->domains()->exists();
    }

    public static function canUseProvider(?User $user, DnsProvider $provider): bool
    {
        if (! $user || ! $provider->is_enabled) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        if (! $user->isDomainAdmin() || self::domainAdminPolicy() === self::POLICY_DISABLED) {
            return false;
        }

        if ($provider->owner_user_id === null) {
            return true;
        }

        return self::domainAdminPolicy() === self::POLICY_GLOBAL_AND_OWN
            && (int) $provider->owner_user_id === (int) $user->getKey();
    }

    public static function canAdministerDomain(?User $user, Domain $domain): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return $user->isDomainAdmin()
            && $user->domains()->where('domains.domain_id', $domain->getKey())->exists();
    }

    public static function canChangeProvider(?User $user, Domain $domain, ?DnsDomain $dnsDomain): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return self::domainAdminPolicy() !== self::POLICY_DISABLED
            && self::canAdministerDomain($user, $domain)
            && $dnsDomain?->service_control === self::SERVICE_FULL;
    }

    public static function canToggleService(?User $user, Domain $domain, ?DnsDomain $dnsDomain): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return self::domainAdminPolicy() !== self::POLICY_DISABLED
            && self::canAdministerDomain($user, $domain)
            && in_array($dnsDomain?->service_control, [self::SERVICE_TOGGLE, self::SERVICE_FULL], true);
    }

    public static function canEditRecords(?User $user, Domain $domain, ?DnsDomain $dnsDomain): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return self::domainAdminPolicy() !== self::POLICY_DISABLED
            && self::canAdministerDomain($user, $domain)
            && $dnsDomain?->record_control === self::RECORD_DOMAIN_ADMIN;
    }
}
