<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vw_dns_providers')) {
            Schema::create('vw_dns_providers', function (Blueprint $table) {
                $table->id();
                $table->string('name');                    // 'Primary PowerDNS', 'Cloudflare Account'
                $table->string('type');                    // 'pdns', 'cloudflare', 'route53'
                $table->string('api_url')->nullable();     // API endpoint URL
                $table->text('api_key')->nullable();       // Encrypted API key/token
                $table->json('settings')->nullable();      // Provider-specific settings
                $table->boolean('is_default')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->integer('priority')->default(0);   // For failover ordering
                $table->timestamps();
                
                $table->index('type');
                $table->index('is_default');
                $table->index('is_enabled');
            });
        }
    }
    
    public function down(): void
    {
        // Only drop if no other DNS plugins are using it
        // For now, just drop - core plugin owns the table
        Schema::dropIfExists('vw_dns_providers');
    }
};
