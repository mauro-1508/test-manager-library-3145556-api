<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class)->group('integration');

function schemaConnectionAvailable(): bool
{
    try {
        if (! extension_loaded('pdo_pgsql')) {
            return false;
        }
        DB::connection('pgsql')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

it('has no column with a default in schema library', function () {
    $rows = DB::select(
        "select table_name, column_name from information_schema.columns
         where table_schema = 'library' and column_default is not null"
    );

    expect($rows)->toBeEmpty();
})->skip(fn () => ! schemaConnectionAvailable(), 'No pgsql connection available');

it('seeds the five categories', function () {
    $rows = DB::select('select id, name from library.category order by id');

    expect(array_map(fn ($r) => [$r->id, $r->name], $rows))->toBe([
        ['11111111-1111-4111-8111-111111111111', 'Novela'],
        ['22222222-2222-4222-8222-222222222222', 'Ciencia'],
        ['33333333-3333-4333-8333-333333333333', 'Tecnología'],
        ['44444444-4444-4444-8444-444444444444', 'Historia'],
        ['55555555-5555-4555-8555-555555555555', 'Infantil'],
    ]);
})->skip(fn () => ! schemaConnectionAvailable(), 'No pgsql connection available');
