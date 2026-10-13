<?php

declare(strict_types=1);

use App\Domain\LendingPolicy;
use App\Domain\MemberHasOverdue;
use App\Domain\MemberLimitExceeded;

it('allows borrowing up to three unreturned books in total', function () {
    (new LendingPolicy())->assertCanBorrow(1, false, 2);

    expect(true)->toBeTrue();
});

it('rejects exceeding three unreturned books and reports the current count', function () {
    try {
        (new LendingPolicy())->assertCanBorrow(2, false, 2);
        $this->fail('Expected MemberLimitExceeded');
    } catch (MemberLimitExceeded $error) {
        expect($error->currentCount)->toBe(2)->and($error->errorCode())->toBe('MEMBER_LIMIT_EXCEEDED');
    }
});

it('rejects a member with overdue items', function () {
    (new LendingPolicy())->assertCanBorrow(1, true, 1);
})->throws(MemberHasOverdue::class);
