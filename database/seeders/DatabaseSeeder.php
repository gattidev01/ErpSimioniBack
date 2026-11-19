<?php

namespace Database\Seeders;

use App\Enums\Perfil;
use App\Models\OficinaConfig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Cria a configuração da oficina e o primeiro administrador.
     * A senha vem de ADMIN_PASSWORD no .env ou é gerada e exibida uma única vez.
     */
    public function run(): void
    {
        // só preenche na primeira vez: não sobrescreve o que a oficina já configurou na tela
        if (! OficinaConfig::query()->exists()) {
            OficinaConfig::atual()->update([
                'nome' => env('OFICINA_NOME', 'Simioni Performance'),
                'condicoes_orcamento' => 'Valores sujeitos a alteração caso sejam identificados outros defeitos durante a execução do serviço, '
                    .'mediante nova aprovação do cliente. Peças substituídas ficam à disposição do cliente.',
            ]);
        }

        $email = env('ADMIN_EMAIL', 'admin@oficina.local');
        if (! User::where('email', $email)->exists()) {
            $senha = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            User::create([
                'name' => 'Administrador',
                'email' => $email,
                'password' => $senha,
                'perfil' => Perfil::Admin,
                'ativo' => true,
            ]);
            $this->command?->warn("Administrador criado: {$email}");
            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn("Senha gerada (anote, não será mostrada de novo): {$senha}");
            }
        }

        if (app()->isLocal() && env('SEED_EXEMPLO', true)) {
            $this->call(ExemploSeeder::class);
        }
    }
}
