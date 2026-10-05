<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fronteira entre domínios (docs/arquitetura.md): de outro domínio, só o
 * contrato, os dados de troca e os eventos. CurrentOrganization é o serviço
 * público de Conta.
 */
class ArchitectureTest extends TestCase
{
    private const PUBLIC = ['Contracts', 'Data', 'Events'];

    private const ALLOWED = ['App\Domains\Accounts\CurrentOrganization'];

    public function test_um_dominio_so_usa_a_parte_publica_de_outro(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domains';
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $own = explode('/', substr($file->getPathname(), strlen($root) + 1))[0];
            preg_match_all('/^use (App\\\\Domains\\\\(\w+)\\\\(\w+)[^;]*);/m', (string) file_get_contents($file->getPathname()), $uses, PREG_SET_ORDER);

            foreach ($uses as [, $class, $domain, $part]) {
                if ($domain !== $own && ! in_array($part, self::PUBLIC, true) && ! in_array($class, self::ALLOWED, true)) {
                    $violations[] = "{$own} usa {$class}";
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
