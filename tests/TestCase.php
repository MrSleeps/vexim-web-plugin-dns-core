<?php

namespace VEximweb\Plugin\DnsCore\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createHostSchema();
        Cache::flush();
    }

    private function createHostSchema(): void
    {
        Schema::create('users_web', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id('domain_id');
            $table->string('domain')->unique();
        });

        Schema::create('vw_domain_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('domain_id');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('vw_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vw_dns_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('name');
            $table->string('type');
            $table->string('api_url')->nullable();
            $table->text('api_key')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('vw_dns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->unsignedBigInteger('provider_id');
            $table->string('zone_id')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('service_control', 32)->default('system_only');
            $table->string('record_control', 32)->default('system_only');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('vw_dns_providers')->restrictOnDelete();
        });
    }
}
