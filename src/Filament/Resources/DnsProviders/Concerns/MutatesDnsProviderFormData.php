<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Concerns;

use VEximweb\Core\Data\Models\User;

trait MutatesDnsProviderFormData
{
    protected function mutateData(array $data): array
    {
        $topLevelFields = ['owner_user_id', 'name', 'type', 'api_url', 'api_key', 'is_default', 'is_enabled', 'priority', 'settings'];

        $settings = [];

        // Pull anything that isn't a top-level column into settings
        foreach ($data as $key => $value) {
            if (!in_array($key, $topLevelFields)) {
                $settings[$key] = $value;
                unset($data[$key]);
            }
        }

        // Merge with anything Filament already nested under settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = array_merge($data['settings'], $settings);
        }

        // Defaults for known settings fields
        if (!isset($settings['server_id'])) {
            $settings['server_id'] = 'localhost';
        }
        if (!isset($settings['api_version'])) {
            $settings['api_version'] = 'v1';
        }
        /** @phpstan-ignore empty.variable */
        $data['settings'] = !empty($settings) ? $settings : null;

        $user = auth()->user();
        if ($user instanceof User && $user->isDomainAdmin() && ! $user->isSystemAdmin()) {
            $data['owner_user_id'] = $user->getKey();
        }

        $data['owner_user_id'] = filled($data['owner_user_id'] ?? null)
            ? (int) $data['owner_user_id']
            : null;
        $data['is_default'] = $data['owner_user_id'] === null
            ? (int) ($data['is_default'] ?? 0)
            : 0;
        $data['is_enabled'] = isset($data['is_enabled']) ? (int) $data['is_enabled'] : 1;
        $data['priority']   = isset($data['priority'])   ? (int) $data['priority']   : 0;

        return $data;
    }
}
