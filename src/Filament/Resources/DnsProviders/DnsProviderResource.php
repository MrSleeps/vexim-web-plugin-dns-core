<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use VEximweb\Core\Data\Models\User;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\CreateDnsProvider;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\EditDnsProvider;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\ListDnsProviders;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Schemas\DnsProviderForm;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Tables\DnsProvidersTable;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Services\DnsAccessControl;

class DnsProviderResource extends Resource
{
    protected static ?string $model = DnsProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?string $slug = 'dns-providers';
    
    protected static string|UnitEnum|null $navigationGroup = 'DNS';
    
    protected static ?string $navigationLabel = 'Providers';
    
    protected static ?int $navigationGroupSort = 1;
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return DnsProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DnsProvidersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return DnsAccessControl::providerResourceQuery($user instanceof User ? $user : null);
    }

    public static function getRelations(): array
    {
        return [];
    }
    
    public static function getPanel(): string
    {
        return 'vexim';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDnsProviders::route('/'),
            'create' => CreateDnsProvider::route('/create'),
            'edit' => EditDnsProvider::route('/{record}/edit'),
        ];
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && DnsAccessControl::canCreateOwnProviders($user);
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && DnsAccessControl::canCreateOwnProviders($user);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record instanceof DnsProvider
            && DnsAccessControl::canManageProvider($user, $record);
    }
    
    public static function canEdit($record): bool
    {
        return static::canView($record);
    }
    
    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record instanceof DnsProvider
            && DnsAccessControl::canDeleteProvider($user, $record);
    }
}
