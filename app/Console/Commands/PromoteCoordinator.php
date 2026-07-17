<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteCoordinator extends Command
{
    protected $signature = 'reports:make-coordinator {email : Correo de la cuenta a promover} {--admin : Otorga el rol de administrador}';

    protected $description = 'Promueve una cuenta registrada para revisar reportes de respuesta.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $user->update(['role' => $this->option('admin') ? 'admin' : 'coordinator']);
        $this->info("{$user->email} ahora tiene rol {$user->role}.");

        return self::SUCCESS;
    }
}
