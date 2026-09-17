<?php

namespace VEximweb\Plugin\DnsCore\ValueObjects;

final class DnsZoneResolution
{
    public function __construct(
        public readonly ?string $zone = null,
        public readonly ?string $delegatedAt = null,
    ) {}

    public static function managed(string $zone): self
    {
        return new self(zone: $zone);
    }

    public static function delegated(string $delegatedAt): self
    {
        return new self(delegatedAt: $delegatedAt);
    }

    public static function missing(): self
    {
        return new self;
    }

    public function isManaged(): bool
    {
        return $this->zone !== null;
    }

    public function isDelegated(): bool
    {
        return $this->delegatedAt !== null;
    }
}
