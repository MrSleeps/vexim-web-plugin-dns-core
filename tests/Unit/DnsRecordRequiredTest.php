<?php

use LogicException;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Plugin\DnsCore\Events\DnsRecordRequired;
use VEximweb\Plugin\DnsCore\Models\DnsDomain;

function mappedDnsDomain(string $domainName, string $zoneName): DnsDomain
{
    $owner = new Domain;
    $owner->setAttribute('domain', $domainName);

    $dnsDomain = new DnsDomain([
        'zone_id' => $zoneName,
    ]);
    $dnsDomain->setRelation('ownerDomain', $owner);

    return $dnsDomain;
}

it('targets the parent zone while qualifying a relative record against the mail domain', function () {
    $event = new DnsRecordRequired(
        domain: mappedDnsDomain('mail.example.com', 'example.com'),
        name: '_dmarc',
        type: 'TXT',
        content: 'v=DMARC1; p=none',
    );

    expect($event->zone)->toBe('example.com.')
        ->and($event->name)->toBe('_dmarc.mail.example.com');
});

it('keeps an already qualified record name unchanged', function () {
    $event = new DnsRecordRequired(
        domain: mappedDnsDomain('mail.example.com', 'example.com'),
        name: 'selector._domainkey.mail.example.com.',
        type: 'TXT',
        content: 'v=DKIM1; p=test',
    );

    expect($event->zone)->toBe('example.com.')
        ->and($event->name)->toBe('selector._domainkey.mail.example.com');
});

it('uses the mail domain itself for apex records inside a parent zone', function () {
    $event = new DnsRecordRequired(
        domain: mappedDnsDomain('mail.example.com', 'example.com'),
        name: '@',
        type: 'TXT',
        content: 'v=spf1 -all',
    );

    expect($event->name)->toBe('mail.example.com');
});

it('refuses to delete a shared parent zone through a child domain mapping', function () {
    $dnsDomain = mappedDnsDomain('mail.example.com', 'example.com');

    expect(fn () => $dnsDomain->deleteZone())
        ->toThrow(LogicException::class, 'refusing to delete the shared zone');
});
