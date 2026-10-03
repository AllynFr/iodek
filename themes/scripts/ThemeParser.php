<?php

namespace App\Services\Dashboard;

/**
 * Thèmes de /bord : lecture d'un fichier CSS de thème et contrôle de ses valeurs, par liste blanche.
 *
 * Un thème ne contient que des déclarations de variables `--b-…` connues, dans des blocs `.bord { … }` ou `:root { … }`,
 * et des commentaires (`/* ioDek theme: Nom *\/` donne le nom). Tout le reste est refusé, ligne à l'appui : règles @
 * (@import, @media…), autres sélecteurs, propriétés ordinaires, chaînes, échappements, url(), expression(), var(), !important.
 * Valeurs : couleurs (#rgb, #rrggbb, #rrggbbaa, rgb(), rgba(), hsl(), hsla(), transparent, black, white) ;
 * longueurs en px ou rem bornées (rayon, espacement). Le résultat est un tableau nom => valeur normalisée,
 * jamais du CSS : le tableau de bord le réinjecte en variables dans un style en ligne.
 *
 * PHP pur, sans Laravel : le même fichier sert au script docs/themes/scripts/check-theme.php (dépôt AllynFr/iodek, dossier themes/),
 * avec la même liste (resources/js/bord/themes.json, copiée dans docs/themes/scripts/).
 */
final class ThemeParser
{
    public const MAX_BYTES = 16384;

    public const MAX_NAME = 40;

    public const MAX_ERRORS = 20;

    public const KEYWORDS = ['transparent', 'black', 'white'];

    /** Messages en anglais (script de vérification) ; l'application traduit les codes (app.dashboard.theme.errors.*). */
    public const MESSAGES = [
        'too_big' => 'File too large (:max bytes at most).',
        'encoding' => 'The file must be UTF-8 text.',
        'empty' => 'No --b-… variable found.',
        'comment' => 'Comment not closed.',
        'escape' => 'Backslash escapes are not allowed.',
        'string' => 'Quoted strings are not allowed.',
        'import' => '@import is not allowed.',
        'at_rule' => 'At-rules (:rule) are not allowed.',
        'selector' => 'Selector “:selector” is not allowed: only .bord or :root.',
        'outside' => 'Text outside a .bord { … } block.',
        'nested' => 'Nested blocks are not allowed.',
        'brace' => 'Closing brace without an opening one.',
        'unclosed' => 'Block not closed.',
        'syntax' => 'Expected “--b-name: value;”.',
        'not_variable' => 'Property “:property” is not allowed: only --b-… variables.',
        'unknown' => 'Unknown variable “:property”.',
        'url' => 'url() is not allowed (:property).',
        'function' => 'Function not allowed in :property: only rgb(), rgba(), hsl(), hsla().',
        'important' => '!important is not allowed (:property).',
        'color' => 'Invalid color for :property: “:value” (#rrggbb, rgb(), rgba(), hsl(), hsla(), transparent, black, white).',
        'length' => 'Invalid length for :property: “:value” (px or rem, :min to :max px).',
    ];

    /** @var array<string, array{name: string, type: string, min?: int, max?: int}> */
    private array $vars = [];

    /** @var array<string, array<string, string>> */
    private array $presets = [];

    /** @var list<array{line: int, code: string, params: array<string, string|int>}> */
    private array $errors = [];

    public function __construct(array $spec)
    {
        foreach ($spec['vars'] ?? [] as $v) {
            $this->vars[$v['name']] = $v;
        }
        $this->presets = $spec['presets'] ?? [];
    }

    public static function fromFile(string $path): self
    {
        return new self(json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{name: string, type: string, min?: int, max?: int}> */
    public function variables(): array
    {
        return $this->vars;
    }

    /** @return array<string, array<string, string>> */
    public function presets(): array
    {
        return $this->presets;
    }

    /**
     * Lit un fichier de thème.
     *
     * @return array{name: ?string, vars: array<string, string>, errors: list<array{line: int, code: string, params: array<string, string|int>}>}
     */
    public function parse(string $css): array
    {
        $this->errors = [];
        $out = ['name' => null, 'vars' => []];
        if (strlen($css) > self::MAX_BYTES) {
            $this->error(0, 'too_big', ['max' => self::MAX_BYTES]);

            return $out + ['errors' => $this->errors];
        }
        if (! mb_check_encoding($css, 'UTF-8')) {
            $this->error(0, 'encoding');

            return $out + ['errors' => $this->errors];
        }
        $css = preg_replace('/^\xEF\xBB\xBF/', '', $css);

        $line = 1;
        $inBlock = false;
        $blockLine = 1;
        $buf = '';
        $bufLine = 1;
        $len = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $c = $css[$i];
            if ($c === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    $this->error($line, 'comment');
                    break;
                }
                $comment = substr($css, $i + 2, $end - $i - 2);
                if ($out['name'] === null && preg_match('/ioDek theme\s*:\s*(.+)/i', $comment, $m)) {
                    $out['name'] = self::cleanName($m[1]);
                }
                $line += substr_count($comment, "\n");
                $i = $end + 1;

                continue;
            }
            if ($c === '\\') {
                $this->error($line, 'escape');

                continue;
            }
            if ($c === '"' || $c === "'") {
                // Chaîne refusée une fois, sautée jusqu'au guillemet fermant de la même ligne.
                $this->error($line, 'string');
                $eol = strpos($css, "\n", $i + 1);
                $close = strpos($css, $c, $i + 1);
                if ($close !== false && ($eol === false || $close < $eol)) {
                    $i = $close;
                }

                continue;
            }
            if ($c === '{') {
                if ($inBlock) {
                    $this->error($line, 'nested');
                } else {
                    $this->selector(trim($buf), $bufLine);
                    $inBlock = true;
                    $blockLine = $line;
                }
                $buf = '';

                continue;
            }
            if ($c === '}') {
                if (! $inBlock) {
                    $this->error($line, 'brace');
                } else {
                    $this->declaration($buf, $bufLine, $out['vars']);
                    $inBlock = false;
                }
                $buf = '';

                continue;
            }
            if ($c === ';') {
                $inBlock ? $this->declaration($buf, $bufLine, $out['vars']) : $this->outside(trim($buf), $bufLine);
                $buf = '';

                continue;
            }
            if (trim($buf) === '' && ! ctype_space($c)) {
                $bufLine = $line;
            }
            if ($c === "\n") {
                $line++;
            }
            $buf .= $c;
        }
        if ($inBlock) {
            $this->error($blockLine, 'unclosed');
        } elseif (trim($buf) !== '') {
            $this->outside(trim($buf), $bufLine);
        }
        if (! $this->errors && ! $out['vars']) {
            $this->error(0, 'empty');
        }

        return $out + ['errors' => $this->errors];
    }

    /** Valeur normalisée d'une variable connue, ou null si elle est refusée. */
    public function value(string $name, mixed $value): ?string
    {
        $spec = $this->vars[$name] ?? null;
        if ($spec === null || ! is_string($value) || strlen($value) > 64) {
            return null;
        }
        $v = strtolower(trim(preg_replace('/\s+/', ' ', $value)));

        return $spec['type'] === 'length' ? self::length($v, $spec['min'] ?? 0, $spec['max'] ?? 32) : self::color($v);
    }

    /**
     * Fichier CSS d'un thème (export), au format accepté par parse().
     *
     * @param  array<string, string>  $vars
     */
    public function toCss(array $vars, ?string $name): string
    {
        $name = self::cleanName((string) $name) ?? 'ioDek';
        $css = "/* ioDek theme: {$name} */\n.bord {\n";
        foreach ($this->vars as $key => $spec) {
            if (isset($vars[$key]) && ($v = $this->value($key, $vars[$key])) !== null) {
                $css .= "    {$key}: {$v};\n";
            }
        }

        return $css."}\n";
    }

    /** Nom de thème : texte simple, 40 caractères au plus, sans balise ni fin de commentaire. */
    public static function cleanName(string $name): ?string
    {
        $name = preg_replace('/[\x00-\x1F\x7F<>{}\\\\*\/]+/u', ' ', strip_tags($name)) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = mb_substr($name, 0, self::MAX_NAME);

        return $name === '' ? null : $name;
    }

    /** Message anglais d'une erreur (script de vérification). */
    public static function message(array $e): string
    {
        $text = self::MESSAGES[$e['code']] ?? $e['code'];
        foreach ($e['params'] as $k => $v) {
            $text = str_replace(':'.$k, (string) $v, $text);
        }

        return ($e['line'] > 0 ? "Line {$e['line']}: " : '').$text;
    }

    private function error(int $line, string $code, array $params = []): void
    {
        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['line' => $line, 'code' => $code, 'params' => $params];
        }
    }

    private function selector(string $sel, int $line): void
    {
        if (str_starts_with($sel, '@')) {
            $this->atRule($sel, $line);

            return;
        }
        $parts = array_map('trim', explode(',', $sel));
        foreach ($parts as $p) {
            if (! in_array($p, ['.bord', ':root'], true)) {
                $this->error($line, 'selector', ['selector' => self::short($sel)]);

                return;
            }
        }
    }

    private function outside(string $text, int $line): void
    {
        if ($text === '') {
            return;
        }
        str_starts_with($text, '@') ? $this->atRule($text, $line) : $this->error($line, 'outside');
    }

    private function atRule(string $text, int $line): void
    {
        preg_match('/^@[a-z-]*/i', $text, $m);
        strtolower($m[0]) === '@import' ? $this->error($line, 'import') : $this->error($line, 'at_rule', ['rule' => self::short($m[0])]);
    }

    /** @param array<string, string> $vars */
    private function declaration(string $text, int $line, array &$vars): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $colon = strpos($text, ':');
        if ($colon === false) {
            $this->error($line, 'syntax');

            return;
        }
        $prop = strtolower(trim(substr($text, 0, $colon)));
        $value = trim(substr($text, $colon + 1));
        $p = ['property' => self::short($prop)];
        if (! str_starts_with($prop, '--')) {
            $this->error($line, 'not_variable', $p);

            return;
        }
        if (! isset($this->vars[$prop])) {
            $this->error($line, 'unknown', $p);

            return;
        }
        if (preg_match('/!\s*important/i', $value)) {
            $this->error($line, 'important', $p);

            return;
        }
        if (preg_match('/\burl\s*\(/i', $value)) {
            $this->error($line, 'url', $p);

            return;
        }
        if (preg_match('/([a-z-]+)\s*\(/i', $value, $m) && ! in_array(strtolower($m[1]), ['rgb', 'rgba', 'hsl', 'hsla'], true)) {
            $this->error($line, 'function', $p);

            return;
        }
        $v = $this->value($prop, $value);
        if ($v === null) {
            $spec = $this->vars[$prop];
            $spec['type'] === 'length'
                ? $this->error($line, 'length', $p + ['value' => self::short($value), 'min' => $spec['min'] ?? 0, 'max' => $spec['max'] ?? 32])
                : $this->error($line, 'color', $p + ['value' => self::short($value)]);

            return;
        }
        $vars[$prop] = $v;
    }

    private static function short(string $s): string
    {
        $s = preg_replace('/\s+/', ' ', $s) ?? '';

        return mb_strlen($s) > 40 ? mb_substr($s, 0, 39).'…' : $s;
    }

    private static function color(string $v): ?string
    {
        if (in_array($v, self::KEYWORDS, true) || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $v)) {
            return $v;
        }
        if (! preg_match('/^(rgba?|hsla?)\((.*)\)$/', $v, $m)) {
            return null;
        }
        $fn = $m[1];
        $inner = trim($m[2]);
        $alpha = null;
        if (str_contains($inner, ',')) {
            $args = array_map('trim', explode(',', $inner));
            if (count($args) === 4) {
                $alpha = array_pop($args);
            }
        } else {
            $slash = array_map('trim', explode('/', $inner));
            if (count($slash) > 2) {
                return null;
            }
            $args = preg_split('/\s+/', $slash[0]);
            $alpha = $slash[1] ?? null;
        }
        if (count($args) !== 3) {
            return null;
        }
        $num = '(\d{1,3}(?:\.\d{1,4})?|\.\d{1,4})';
        if ($fn[0] === 'r') {
            foreach ($args as $a) {
                if (preg_match("/^{$num}%$/", $a, $x) ? (float) $x[1] > 100 : (! preg_match("/^{$num}$/", $a, $x) || (float) $x[1] > 255)) {
                    return null;
                }
            }
        } else {
            if (! preg_match("/^{$num}(deg)?$/", $args[0], $x) || (float) $x[1] > 360) {
                return null;
            }
            foreach ([$args[1], $args[2]] as $a) {
                if (! preg_match("/^{$num}%$/", $a, $x) || (float) $x[1] > 100) {
                    return null;
                }
            }
        }
        if ($alpha !== null && (preg_match("/^{$num}%$/", $alpha, $x) ? (float) $x[1] > 100 : (! preg_match("/^{$num}$/", $alpha, $x) || (float) $x[1] > 1))) {
            return null;
        }
        $base = rtrim($fn, 'a');

        return $alpha === null ? $base.'('.implode(', ', $args).')' : $base.'a('.implode(', ', $args).', '.$alpha.')';
    }

    private static function length(string $v, int $min, int $max): ?string
    {
        if ($v === '0') {
            $v = '0px';
        }
        if (! preg_match('/^(\d{1,3}(?:\.\d{1,2})?)(px|rem)$/', $v, $m)) {
            return null;
        }
        $px = (float) $m[1] * ($m[2] === 'rem' ? 16 : 1);

        return $px < $min || $px > $max ? null : $v;
    }
}
