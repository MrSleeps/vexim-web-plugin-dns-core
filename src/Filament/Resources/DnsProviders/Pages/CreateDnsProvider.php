<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages;

use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\DnsProviderResource;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Concerns\MutatesDnsProviderFormData;
use Filament\Resources\Pages\CreateRecord;

class CreateDnsProvider extends CreateRecord
{
    use MutatesDnsProviderFormData;

    protected static string $resource = DnsProviderResource::class;    
    
    public ?array $data = [];
    
    public function mount(): void
    {
        parent::mount();
        
        // CRITICAL: Initialize the form state
        $this->form->fill();
    }    

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mutateData($data);
    }
}