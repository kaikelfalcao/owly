<?php

// PostToolUse do Claude Code: formata com o Pint o arquivo PHP que acabou de ser editado.
$input = json_decode((string) stream_get_contents(STDIN), true) ?: [];
$file = (string) ($input['tool_input']['file_path'] ?? '');
if (str_ends_with($file, '.php') && is_file($file) && is_file('vendor/bin/pint')) {
    exec('php vendor/bin/pint -q '.escapeshellarg($file));
}
exit(0);
