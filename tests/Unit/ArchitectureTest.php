<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fronteira entre domínios (docs/arquitetura.md): de outro domínio, só o
 * contrato, os dados de troca e os eventos. De Conta, também o que faz o
 * isolamento por empresa: CurrentOrganization, o trait dos modelos e o
 * middleware dos jobs.
 */
class ArchitectureTest extends TestCase
{
    private const PUBLIC = ['Contracts', 'Data', 'Events'];

    private const ALLOWED = [
        'App\Domains\Accounts\CurrentOrganization',
        'App\Domains\Accounts\Concerns\BelongsToOrganization',
        'App\Domains\Accounts\Jobs\ForOrganization',
    ];

    /** Modelos que não pertencem a uma empresa: a própria empresa. */
    private const WITHOUT_ORGANIZATION = ['Accounts/Models/Organization.php'];

    /**
     * Consultas que passam por cima do escopo da empresa (docs/arquitetura.md,
     * "Empresa desde o primeiro dia"). DB::transaction e DB::raw dentro de uma
     * consulta Eloquent continuam liberados.
     */
    private const BYPASS = [
        '/DB::(table|select|insert|update|delete|statement|unprepared|affectingStatement|cursor|scalar)\(/' => 'consulta pelo DB, sem o escopo da empresa',
        '/->(getQuery|toBase)\(/' => 'consulta base, sem o escopo da empresa',
        '/withoutGlobalScopes?\(/' => 'tira o escopo da empresa',
        '/->(join|leftJoin|rightJoin|crossJoin)\(/' => 'join: a tabela juntada não recebe o escopo; use whereHas ou subconsulta',
    ];

    public function test_um_dominio_so_usa_a_parte_publica_de_outro(): void
    {
        $root = $this->root().'/app/Domains';
        $violations = [];

        foreach ($this->files($root) as $path) {
            $own = explode('/', substr($path, strlen($root) + 1))[0];
            preg_match_all('/^use (App\\\\Domains\\\\(\w+)\\\\(\w+)[^;]*);/m', (string) file_get_contents($path), $uses, PREG_SET_ORDER);

            foreach ($uses as [, $class, $domain, $part]) {
                if ($domain !== $own && ! in_array($part, self::PUBLIC, true) && ! in_array($class, self::ALLOWED, true)) {
                    $violations[] = "{$own} usa {$class}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_todo_modelo_de_dominio_pertence_a_uma_empresa(): void
    {
        $root = $this->root().'/app/Domains';
        $violations = [];

        foreach ($this->files($root) as $path) {
            $relative = substr($path, strlen($root) + 1);

            if (! preg_match('#^\w+/Models/#', $relative) || in_array($relative, self::WITHOUT_ORGANIZATION, true)) {
                continue;
            }

            if (! preg_match('/^\s+use BelongsToOrganization;/m', (string) file_get_contents($path))) {
                $violations[] = "{$relative} sem BelongsToOrganization";
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_nenhuma_consulta_passa_por_cima_do_escopo_da_empresa(): void
    {
        $root = $this->root().'/app';
        $violations = [];

        foreach ($this->files($root) as $path) {
            $code = (string) file_get_contents($path);

            foreach (self::BYPASS as $pattern => $reason) {
                if (preg_match($pattern, $code)) {
                    $violations[] = substr($path, strlen($root) + 1).": {$reason}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_consulta_entre_empresas_so_em_comando_ou_administracao(): void
    {
        $root = $this->root().'/app';
        $violations = [];

        foreach ($this->files($root) as $path) {
            $relative = substr($path, strlen($root) + 1);

            if (str_contains($relative, '/Console/') || str_starts_with($relative, 'Platform/Admin/') || $relative === 'Domains/Accounts/CurrentOrganization.php') {
                continue;
            }

            if (preg_match('/->across\(/', (string) file_get_contents($path))) {
                $violations[] = $relative;
            }
        }

        $this->assertSame([], $violations);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private function files(string $root): array
    {
        $paths = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }
}
