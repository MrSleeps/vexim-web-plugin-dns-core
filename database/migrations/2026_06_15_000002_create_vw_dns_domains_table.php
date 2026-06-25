<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vw_dns_domains')) {
            Schema::create('vw_dns_domains', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('domain_id');           // Links to main app's domains table
                $table->foreignId('provider_id')->constrained('vw_dns_providers')->onDelete('cascade');
                $table->string('zone_id')->nullable();             // Provider's zone/domain ID
                $table->json('settings')->nullable();              // Per-domain settings
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();

                // Foreign key to main app's domains table (note: 'domains' not 'vw_domains')
                $table->foreign('domain_id')
                    ->references('domain_id')
                    ->on('domains')
                    ->onDelete('cascade');

                $table->unique('domain_id');
                $table->index('domain_id');
                $table->index('provider_id');
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vw_dns_domains');
    }
};
