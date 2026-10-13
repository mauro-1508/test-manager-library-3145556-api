<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Console;

use App\Application\Services\EnsureInitialAdminService;
use App\Domain\DomainError;
use App\Domain\DomainValidationError;
use Illuminate\Console\Command;

/**
 * Container start-up step: creates the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD (plan D-10).
 */
final class EnsureAdminCommand extends Command
{
    protected $signature = 'library:ensure-admin';

    protected $description = 'Create the initial admin user from the environment if it does not exist';

    public function handle(EnsureInitialAdminService $service): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');
        if (trim($username) === '' || $password === '') {
            $this->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set.');

            return self::FAILURE;
        }

        try {
            $created = $service->execute($username, $password);
        } catch (DomainError $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info($created ? "Admin '{$username}' created." : "Admin '{$username}' already exists.");

        return self::SUCCESS;
    }
}
