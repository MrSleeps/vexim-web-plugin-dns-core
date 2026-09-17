<?php

use VEximweb\Plugin\DnsCore\Contracts\DnsClient;
use VEximweb\Plugin\DnsCore\Factories\DnsClientFactory;
use VEximweb\Plugin\DnsCore\Models\DnsProvider;

it('resolves provider clients through the singleton DNS client factory', function () {
    $provider = new DnsProvider([
        'name' => 'Test provider',
        'type' => 'pdns',
        'is_enabled' => true,
    ]);

    $client = Mockery::mock(DnsClient::class);
    $factory = Mockery::mock(DnsClientFactory::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($provider, null)
        ->andReturn($client);

    app()->instance(DnsClientFactory::class, $factory);

    expect($provider->getClient())->toBe($client);
});
