<?php

namespace App\Platform\Console;

use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;

/**
 * O `php artisan serve` (e o `composer dev`, que usa ele) com o limite de
 * envio do PHP no tamanho do zip aceito. O padrão do PHP é 2 MB, e o zip de
 * um mês de conversas já passa disso.
 */
class ServeCommand extends LaravelServeCommand
{
    /**
     * @return list<string>
     */
    protected function serverCommand()
    {
        $command = parent::serverCommand();
        array_splice($command, 1, 0, self::uploadLimits((int) config('owly.imports.max_kb')));

        return $command;
    }

    /**
     * O corpo do envio leva o arquivo e o resto do formulário: uma folga de 1 MB.
     *
     * @return list<string>
     */
    public static function uploadLimits(int $maxKb): array
    {
        $file = (int) ceil($maxKb / 1024);

        return [
            '-d', "upload_max_filesize={$file}M",
            '-d', 'post_max_size='.($file + 1).'M',
        ];
    }
}
