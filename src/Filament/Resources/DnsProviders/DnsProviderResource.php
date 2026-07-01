<?php

namespace VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\ListDnsProviders;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\CreateDnsProvider;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Pages\EditDnsProvider;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Tables\DnsProvidersTable;
use VEximweb\Plugin\DnsCore\Filament\Resources\DnsProviders\Schemas\DnsProviderForm;

class DnsProviderResource extends Resource
{
    protected static ?string $model = DnsProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'DnsServiceProvider';
    
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPanel(): string
    {
        return 'vexim'; // Must match the panel ID above
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
        return $user && ($user->isSystemAdmin() || $user->isDomainAdmin());
    }    
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user && ($user->isSystemAdmin() || $user->isDomainAdmin());
    }
    
    public static function canEdit($record): bool
    {
        $user = auth()->user();
        
        if (!$user) return false;
        
        if ($user->isSystemAdmin()) return true;
        
        return false;
    }
    
    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user && $user->isSystemAdmin();
    }    

  
}
