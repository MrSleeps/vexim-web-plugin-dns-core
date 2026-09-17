<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use VEximweb\Core\Data\Models\User;
use VEximweb\Plugin\DnsCore\Services\DnsProviderDiscoveryService;

class DnsProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        $discovery = app(DnsProviderDiscoveryService::class);
                        $providerClass = $discovery->getProvider($state);

                        return $providerClass ? $providerClass::getName() : strtoupper($state);
                    })
                    ->badge()
                    ->color(function ($state) {
                        $discovery = app(DnsProviderDiscoveryService::class);
                        $providerClass = $discovery->getProvider($state);

                        if ($providerClass && method_exists($providerClass, 'getColor')) {
                            return $providerClass::getColor();
                        }

                        return 'gray';
                    }),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_user_id')
                    ->label('Scope')
                    ->formatStateUsing(fn ($state) => $state ? 'Private' : 'Global')
                    ->badge(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('System')
                    ->visible(function (): bool {
                        $user = auth()->user();

                        return $user instanceof User && $user->isSystemAdmin();
                    }),
                TextColumn::make('domains_count')
                    ->label('Domains')
                    ->counts('domains'),
                TextColumn::make('api_url')
                    ->label('URL')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
