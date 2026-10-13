<?php

declare(strict_types=1);

// Constitution Art. I: the domain depends on nothing (plain PHP only).
arch('Art. I: domain does not depend on other project layers')
    ->expect('App\Domain')
    ->not->toUse(['App\Application', 'App\Adapters', 'App\Bootstrap']);

arch('Art. I: domain does not use framework or third-party code')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Firebase', 'Carbon']);

arch('Art. I: domain only uses itself and native PHP')
    ->expect('App\Domain')
    ->toOnlyUse(['App\Domain']);

// Constitution Art. II: the core declares its ports.
arch('Art. II: application does not depend on adapters or bootstrap')
    ->expect('App\Application')
    ->not->toUse(['App\Adapters', 'App\Bootstrap']);

arch('Art. II: application does not use framework or third-party code')
    ->expect('App\Application')
    ->not->toUse(['Illuminate', 'Firebase', 'Carbon']);

arch('Art. II: outbound ports are interfaces, except ConcurrencyConflict (exception)')
    ->expect('App\Application\Ports\Outbound')
    ->toBeInterfaces()
    ->ignoring('App\Application\Ports\Outbound\ConcurrencyConflict');

arch('Art. II: ConcurrencyConflict is a runtime exception')
    ->expect('App\Application\Ports\Outbound\ConcurrencyConflict')
    ->toExtend(RuntimeException::class);

// Constitution Art. III: single composition point.
arch('Art. III: outbound adapters are only used by the composition point (and themselves)')
    ->expect('App\Adapters\Outbound')
    ->toOnlyBeUsedIn([
        'App\Adapters\Outbound',
        'App\Bootstrap\Composition',
    ]);

arch('Art. III: inbound adapters do not use outbound adapters')
    ->expect('App\Adapters\Inbound')
    ->not->toUse('App\Adapters\Outbound');

arch('Art. III: application and domain are never aware of outbound adapters')
    ->expect(['App\Application', 'App\Domain'])
    ->not->toUse('App\Adapters\Outbound');
