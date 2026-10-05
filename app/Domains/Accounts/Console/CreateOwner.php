<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Models\Organization;
use App\Platform\Audit\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Cria a empresa e o dono. É a única forma de ganhar acesso: não existe
 * cadastro aberto (ele vai morar no site de vendas).
 */
class CreateOwner extends Command
{
    protected $signature = 'owly:owner
        {email? : E-mail de entrada do dono}
        {--name= : Nome do dono}
        {--company= : Nome da empresa}';

    protected $description = 'Cria uma empresa e o dono dela (o login da Owly)';

    public function handle(Audit $audit): int
    {
        $email = $this->argument('email') ?? text('E-mail do dono', required: true);
        $name = $this->option('name') ?? text('Nome do dono', required: true);
        $company = $this->option('company') ?? text('Nome da empresa', required: true);
        // A senha só é digitada, nunca passada como argumento: assim ela não
        // fica no histórico do terminal.
        $password = password('Senha', required: true);

        $validator = Validator::make(
            compact('email', 'name', 'company', 'password'),
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'company' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::defaults()],
            ],
            ['email.unique' => 'Já existe um login com este e-mail. Use "Esqueceu a senha?" na tela de entrar.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $name, $company, $password, $audit) {
            $organization = Organization::create(['name' => $company]);

            $user = $organization->users()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $audit->record('accounts.owner_created', $user, userId: $user->id, organizationId: $organization->id);

            return $user;
        });

        $this->components->info("Pronto. {$user->email} já pode entrar na Owly.");

        return self::SUCCESS;
    }
}
