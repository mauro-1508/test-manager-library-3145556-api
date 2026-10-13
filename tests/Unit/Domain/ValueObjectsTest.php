<?php

declare(strict_types=1);

use App\Domain\DateRange;
use App\Domain\DocumentNumber;
use App\Domain\DomainValidationError;
use App\Domain\InvalidDateRange;
use App\Domain\Isbn;
use App\Domain\Quantity;
use App\Domain\Uuid;
use Tests\Unit\Domain\Support\DomainFixtures;

it('accepts a 13-digit ISBN', function () {
    expect((new Isbn('9780307474728'))->value)->toBe('9780307474728');
});

it('rejects an ISBN that is not exactly 13 digits', function (string $value) {
    new Isbn($value);
})->with(['12 digits' => '978030747472', '14 digits' => '97803074747280', 'letters' => '97803074747ab', 'empty' => ''])
    ->throws(DomainValidationError::class);

it('accepts a quantity greater than zero', function () {
    expect((new Quantity(1))->value)->toBe(1);
});

it('rejects a quantity of zero or less', function (int $value) {
    new Quantity($value);
})->with([0, -1])->throws(DomainValidationError::class);

it('accepts a range where end is after or equal to start', function () {
    $start = DomainFixtures::at('2026-10-01');

    expect((new DateRange($start, DomainFixtures::at('2026-10-31')))->end)->toEqual(DomainFixtures::at('2026-10-31'))
        ->and((new DateRange($start, $start))->start)->toEqual($start);
});

it('rejects a range where end is before start', function () {
    new DateRange(DomainFixtures::at('2026-10-31'), DomainFixtures::at('2026-10-01'));
})->throws(InvalidDateRange::class);

it('exposes the business code of an invalid range', function () {
    try {
        new DateRange(DomainFixtures::at('2026-10-31'), DomainFixtures::at('2026-10-01'));
    } catch (InvalidDateRange $error) {
        expect($error->errorCode())->toBe('INVALID_DATE_RANGE');
    }
});

it('requires a non-empty document number', function () {
    expect((new DocumentNumber(' 123 '))->value)->toBe('123');
    new DocumentNumber('   ');
})->throws(DomainValidationError::class);

it('generates UUID v4 identifiers', function () {
    expect(Uuid::v4())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});
