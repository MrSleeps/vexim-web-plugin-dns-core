<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Concerns\MutatesDnsProviderFormData;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\DnsProviderResource;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;

class EditDnsProvider extends EditRecord
{
    use MutatesDnsProviderFormData;

    protected static string $resource = DnsProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['settings']) && is_string($data['settings'])) {
            $data['settings'] = json_decode($data['settings'], true) ?? [];
        }

        if ($this->record instanceof DnsProvider) {
            $data['api_key'] = $this->record->api_key;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateData($data);
    }
}
