<?php

namespace VEximweb\Plugin\DnsCore\Contracts;

use Filament\Forms\Components\Component;

interface DnsProviderPlugin
{
    /**
     * Get the unique identifier for this provider
     */
    public static function getType(): string;
    
    /**
     * Get the display name for this provider
     */
    public static function getName(): string;
    
    /**
     * Get the description of this provider
     */
    public static function getDescription(): string;
    
    /**
     * Get the form schema for this provider's specific settings
     */
    public static function getSettingsSchema(): array;
    
    /**
     * Get the icon for this provider
     */
    public static function getIcon(): string;
    
    /**
     * Validate and test the connection
     */
    public function testConnection(array $settings): bool;
    
    /**
     * Get the API URL base (can be dynamic based on settings)
     */
    public function getApiUrl(array $settings): string;
}
