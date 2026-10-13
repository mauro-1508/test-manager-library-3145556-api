<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of every request: authorization is decided by the role middleware, so here only validation remains.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
