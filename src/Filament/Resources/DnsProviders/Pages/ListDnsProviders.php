<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages;

use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\DnsProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDnsProviders extends ListRecords
{
    protected static string $resource = DnsProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
