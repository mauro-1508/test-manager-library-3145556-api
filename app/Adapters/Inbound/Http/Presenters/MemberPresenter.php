<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Presenters;

use App\Application\Dto\MemberView;
use App\Domain\Member;

final class MemberPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Member $member): array
    {
        return [
            'id' => $member->id,
            'full_name' => $member->fullName,
            'document_number' => $member->document->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentDetail(MemberView $view): array
    {
        return self::present($view->member) + ['active_loans' => $view->activeLoans];
    }
}
