<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Actions\Action as FormAction;
use VEximweb\Plugin\DnsCore\Services\DnsProviderDiscoveryService;
use Illuminate\Support\Facades\Log;

class DnsProviderForm
{
    protected static ?DnsProviderDiscoveryService $discoveryService = null;
    
    protected static function getDiscoveryService(): DnsProviderDiscoveryService
    {
        if (!static::$discoveryService) {
            static::$discoveryService = app(DnsProviderDiscoveryService::class);

            // Ensure booted - method definitely exists
            static::$discoveryService->boot();

            $options = static::$discoveryService->getProviderOptions();
            Log::info('DnsProviderForm - Available providers:', ['options' => $options]);
        }

        return static::$discoveryService;
    }
    
    public static function configure(Schema $schema): Schema
    {
        $discoveryService = static::getDiscoveryService();
        $providerOptions = $discoveryService->getProviderOptions();
        
        Log::info('DnsProviderForm - Provider options for select:', ['options' => $providerOptions]);
        
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->description('General provider settings')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Provider Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., My PowerDNS Server')
                                    ->helperText('A descriptive name to identify this provider')
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label('Provider Type')
                                    ->required()
                                    ->options($providerOptions)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($set, $state) use ($discoveryService) {
                                        $set('api_url',  null);
                                        $set('api_key',  null);
                                        $set('settings', null);

                                        if (!$state) {
                                            return;
                                        }

                                        $providerClass = $discoveryService->getProvider($state);
                                        if ($providerClass && method_exists($providerClass, 'getDefaultApiUrl')) {
                                            $set('api_url', $providerClass::getDefaultApiUrl());
                                        }
                                    })
                                    ->helperText(function ($get) use ($discoveryService) {
                                        $type = $get('type');
                                        if (!$type) {
                                            return 'Select a provider type';
                                        }
                                        $providerClass = $discoveryService->getProvider($type);
                                        if ($providerClass && method_exists($providerClass, 'getDescription')) {
                                            return $providerClass::getDescription();
                                        }
                                        return 'Select a provider type';
                                    })
                                    ->placeholder(count($providerOptions) === 0 ? 'No providers available' : 'Select a provider'),
                            ]),
                    ]),

                Group::make()
                    ->visible(fn ($get) => (bool) $get('type'))
                    ->schema(function ($get) use ($discoveryService) {
                        $type = $get('type');

                        if (!$type) {
                            return [
                                Section::make('Provider Settings')
                                    ->description('Select a provider type to configure its settings')
                                    ->icon('heroicon-o-cog')
                                    ->schema([
                                        TextInput::make('settings_placeholder')
                                            ->label('  ')
                                            ->hiddenLabel()
                                            ->disabled()
                                            ->default('Please select a provider type first')
                                            ->helperText('Provider-specific configuration will appear here'),
                                    ]),
                            ];
                        }

                        $providerClass = $discoveryService->getProvider($type);

                        if (!$providerClass) {
                            return [
                                Section::make('Provider Settings')
                                    ->description('Provider configuration')
                                    ->icon('heroicon-o-cog')
                                    ->schema([
                                        Textarea::make('settings')
                                            ->label('Raw Settings (JSON)')
                                            ->rows(5)
                                            ->placeholder('{"api_key": "your-key", "api_url": "https://..."}')
                                            ->helperText('Enter provider settings as JSON')
                                            ->columnSpanFull(),
                                    ]),
                            ];
                        }

                        $settingsSchema = $providerClass::getSettingsSchema();

                        if (empty($settingsSchema)) {
                            return [
                                Section::make('Provider Settings')
                                    ->description('Configuration for ' . $providerClass::getName())
                                    ->icon('heroicon-o-cog')
                                    ->schema([
                                        TextInput::make('api_url')
                                            ->statePath('api_url')
                                            ->label('API URL')
                                            ->url()
                                            ->maxLength(255)
                                            ->placeholder('https://api.example.com/v1')
                                            ->extraInputAttributes(['autocomplete' => 'off']),

                                    TextInput::make('api_key')
                                        ->label('API Key')
                                        ->required()
                                        ->helperText('X-API-Key for authentication'),
                                    ]),
                            ];
                        }
  
                        return [
                            Section::make($providerClass::getName() . ' Settings')
                                ->description($providerClass::getDescription())
                                ->icon($providerClass::getIcon())
                                ->collapsible()
                                ->schema($settingsSchema),
                        ];
                    })
                    ->columnSpanFull(),

                Section::make('Advanced Configuration')
                    ->visible(fn ($get) => (bool) $get('type'))
                    ->description('Provider behavior and integration settings')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->label('Provider Enabled')
                                    ->default(true)
                                    ->helperText('Enable this DNS provider for use')
                                    ->onColor('success')
                                    ->offColor('danger'),

                                Toggle::make('is_default')
                                    ->label('Default Provider')
                                    ->default(false)
                                    ->helperText('Use this as the default DNS provider')
                                    ->onColor('warning')
                                    ->offColor('gray'),
                            ]),
                    ]),


            ]);
    }
}