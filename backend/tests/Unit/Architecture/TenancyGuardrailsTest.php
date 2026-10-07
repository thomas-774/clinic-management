<?php

/*
 * NFR-T.1 (T11-15): guardrails that keep the app ready for more than one
 * clinic (docs/adr/0001-multi-clinic-tenancy.md). The rules read the PHP
 * tokens of the source, so comments and docblocks never count.
 */

/**
 * @param  list<string>  $dirs  folders under backend/
 * @return array<string, string> relative path => source
 */
function tenancySources(array $dirs): array
{
    $root = dirname(__DIR__, 3);
    $sources = [];
    foreach ($dirs as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $sources[$path] = file_get_contents($file->getPathname());
            }
        }
    }
    ksort($sources);

    return $sources;
}

/**
 * The tokens that carry meaning: no whitespace, comments or docblocks.
 *
 * @return list<array{0: int|string, 1: string, 2: int}>
 */
function tenancyTokens(string $code): array
{
    $tokens = [];
    foreach (token_get_all($code) as $token) {
        $token = is_array($token) ? $token : [$token, $token, 0];
        if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Lines that call `clinicDoctor` (`X::clinicDoctor()`, `$x->clinicDoctor()`)
 * or name it as a callable (`[User::class, 'clinicDoctor']`).
 *
 * @return list<int>
 */
function clinicDoctorCalls(string $code): array
{
    $tokens = tenancyTokens($code);
    $lines = [];
    foreach ($tokens as $i => [$id, $text, $line]) {
        $called = $id === T_STRING && strcasecmp($text, 'clinicDoctor') === 0
            && in_array($tokens[$i - 1][0] ?? null, [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
        $named = $id === T_CONSTANT_ENCAPSED_STRING && strcasecmp(trim($text, '\'"'), 'clinicDoctor') === 0;
        if ($called || $named) {
            $lines[] = $line;
        }
    }

    return $lines;
}

/**
 * Lines with `DB::table(...)`: a query builder skips the Eloquent global
 * scope that will add `clinic_id` (ADR 0001), so controllers go through the
 * models.
 *
 * @return list<int>
 */
function dbTableCalls(string $code): array
{
    $tokens = tenancyTokens($code);
    $lines = [];
    foreach ($tokens as $i => [$id, $text, $line]) {
        $facade = in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
            && preg_match('/(^|\\\\)DB$/', $text);
        if ($facade && ($tokens[$i + 1][0] ?? null) === T_DOUBLE_COLON
            && strcasecmp($tokens[$i + 2][1] ?? '', 'table') === 0) {
            $lines[] = $line;
        }
    }

    return $lines;
}

/**
 * Lines with a static property or static variable that could cache the
 * doctor or the clinic: its type or name mentions User, doctor, clinic or
 * settings. Such a cache would outlive the request (Octane, queue workers)
 * and leak one clinic's doctor into another clinic's request.
 *
 * @return list<int>
 */
function doctorStaticCaches(string $code): array
{
    $tokens = tenancyTokens($code);
    $between = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY, T_CALLABLE, '?', '|', '&'];
    $lines = [];
    foreach ($tokens as $i => [$id, , $line]) {
        if ($id !== T_STATIC) {
            continue;
        }
        $declaration = '';
        for ($j = $i + 1; isset($tokens[$j]) && in_array($tokens[$j][0], $between, true); $j++) {
            $declaration .= $tokens[$j][1].' ';
        }
        if (($tokens[$j][0] ?? null) === T_VARIABLE
            && preg_match('/user|doctor|clinic|setting/i', $declaration.$tokens[$j][1])) {
            $lines[] = $line;
        }
    }

    return $lines;
}

/**
 * @param  array<string, string>  $sources
 * @return list<string> "path:line" of every finding
 */
function tenancyFindings(array $sources, callable $rule): array
{
    $found = [];
    foreach ($sources as $path => $code) {
        foreach ($rule($code) as $line) {
            $found[] = "{$path}:{$line}";
        }
    }

    return $found;
}

it('calls User::clinicDoctor() only from ClinicContext', function () {
    $sources = tenancySources(['app', 'database', 'routes', 'tests']);
    unset($sources['app/Support/ClinicContext.php'], $sources['tests/Unit/Architecture/TenancyGuardrailsTest.php']);

    expect(tenancyFindings($sources, clinicDoctorCalls(...)))->toBe([])
        ->and(clinicDoctorCalls(tenancySources(['app'])['app/Support/ClinicContext.php']))->not->toBe([]);
});

it('keeps DB::table() out of controllers', function () {
    expect(tenancyFindings(tenancySources(['app/Http/Controllers']), dbTableCalls(...)))->toBe([]);
});

it('has no static cache of the doctor or the clinic', function () {
    expect(tenancyFindings(tenancySources(['app']), doctorStaticCaches(...)))->toBe([]);
});

// The rules themselves: each one catches what it is there for, and leaves
// alone the code that looks like it but is fine.
it('catches a direct caller added back', function () {
    $code = <<<'PHP'
        <?php
        // User::clinicDoctor() in a comment is fine
        $a = User::clinicDoctor();
        $b = \App\Models\User::clinicDoctor()->id;
        $c = $user->clinicDoctor();
        $d = app()->call([User::class, 'clinicDoctor']);
        function clinicDoctor() {}
        $e = clinicDoctor();
        PHP;

    expect(clinicDoctorCalls($code))->toBe([3, 4, 5, 6]);
});

it('catches DB::table() however the facade is written', function () {
    $code = <<<'PHP'
        <?php
        DB::table('patients')->get();
        \Illuminate\Support\Facades\DB::table('visits')->count();
        DB::transaction(fn () => null);
        $q->table('x');
        PHP;

    expect(dbTableCalls($code))->toBe([2, 3]);
});

it('catches a static cache of the doctor but not static methods or calls', function () {
    $code = <<<'PHP'
        <?php
        class A {
            private static ?User $doctor = null;
            protected static $clinicSettings = [];
            public static int $count = 0;
            public static function make(): static { return new static; }
            public function f() {
                static $cachedDoctor;
                static $n = 0;
                return static::make() ?? static fn () => self::$count;
            }
        }
        PHP;

    expect(doctorStaticCaches($code))->toBe([3, 4, 8]);
});
