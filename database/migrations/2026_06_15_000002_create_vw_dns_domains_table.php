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
                // Use unsignedInteger to match the domains table's int(10) unsigned
                $table->unsignedInteger('domain_id');  // Changed from unsignedBigInteger
                $table->foreignId('provider_id')->constrained('vw_dns_providers')->onDelete('cascade');
                $table->string('zone_id')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();

                // Foreign key to domains table - this should work now
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