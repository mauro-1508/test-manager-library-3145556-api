<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Errors\UsernameTaken;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PgUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        try {
            DB::table('library.user')->upsert(
                [[
                    'id' => $user->id,
                    'username' => $user->username,
                    'password_hash' => $user->passwordHash,
                    'role' => $user->role->value,
                ]],
                ['id'],
                ['username', 'password_hash', 'role'],
            );
        } catch (QueryException $error) {
            // The original exception carries the bindings (password hash), so it is never rethrown.
            throw PgErrors::isUniqueViolation($error)
                ? new UsernameTaken()
                : new RuntimeException('The user could not be saved.');
        }
    }

    public function findByUsername(string $normalizedUsername): ?User
    {
        $row = DB::table('library.user')->where('username', $normalizedUsername)->first();

        return $row === null
            ? null
            : new User($row->id, $row->username, $row->password_hash, Role::from($row->role));
    }
}
