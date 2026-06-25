<?php
namespace VEximweb\Plugin\DnsCore\Events;

class RegisterDnsClients
{
    public $factory;
    
    public function __construct($factory)
    {
        $this->factory = $factory;
    }
}
