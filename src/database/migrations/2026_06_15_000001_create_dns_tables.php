<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only create tables if they don't exist
        if (!Schema::hasTable('vw_dns_providers')) {
            Schema::create('vw_dns_providers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // 'pdns', 'cloudflare', 'route53'
                $table->string('api_url')->nullable();
                $table->text('api_key')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
        
        if (!Schema::hasTable('vw_dns_domains')) {
            Schema::create('vw_dns_domains', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('domain_id');
                $table->foreignId('provider_id')->constrained('vw_dns_providers');
                $table->string('zone_id')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->foreign('domain_id')
                    ->references('id')
                    ->on('domains');
                $table->unique('domain_id');
            });
        }
        
        if (!Schema::hasTable('vw_dns_records')) {
            Schema::create('vw_dns_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('domain_id')->constrained('vw_dns_domains');
                $table->string('provider_record_id')->nullable();
                $table->string('name');
                $table->string('type');
                $table->text('content');
                $table->integer('ttl')->default(3600);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
        
        // Add a default PowerDNS provider if none exist
        if (\DB::table('vw_dns_providers')->where('type', 'pdns')->count() === 0) {
            \DB::table('vw_dns_providers')->insert([
                'name' => 'Default PowerDNS',
                'type' => 'pdns',
                'is_default' => true,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    public function down()
    {
        // Only remove PowerDNS providers
        $pdnsProviders = \DB::table('vw_dns_providers')
            ->where('type', 'pdns')
            ->pluck('id');
        
        if ($pdnsProviders->isNotEmpty()) {
            // Delete domains linked to PowerDNS providers
            \DB::table('vw_dns_domains')
                ->whereIn('provider_id', $pdnsProviders)
                ->delete();
            
            // Delete the providers themselves
            \DB::table('vw_dns_providers')
                ->whereIn('id', $pdnsProviders)
                ->delete();
            
            // If no providers left, clean up empty tables
            if (\DB::table('vw_dns_providers')->count() === 0) {
                \DB::table('vw_dns_records')->delete();
                // Keep the tables though - other plugins might need them
            }
        }
    }
};
