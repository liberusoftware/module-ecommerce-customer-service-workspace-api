<?php

declare(strict_types=1);

/*
 * Rules about this package's own code that the shipped boundary suite cannot
 * know. They name the host faults this surface exists to answer.
 */

it('imports nothing from the host application', function (): void {
    foreach (sourceFiles() as $path) {
        expect(php_strip_whitespace($path))->not->toMatch('/\buse\s+App\\\\/', $path);
    }
});

it('reaches for the facades rather than the framework helpers', function (): void {
    // `illuminate/support` carries neither `config()` nor `app()`; they live in
    // `laravel/framework`, which the testbench drags in and a host may not.
    foreach (sourceFiles() as $path) {
        $code = php_strip_whitespace($path);

        expect($code)->not->toMatch('/(?<![\w:>$])config\(/', $path)
            ->and($code)->not->toMatch('/(?<![\w:>$])app\(/', $path)
            ->and($code)->not->toMatch('/(?<![\w:>$])view\(/', $path)
            ->and($code)->not->toMatch('/(?<![\w:>$])session\(/', $path);
    }
});

it('derives standing from a merchant and never from a role name', function (): void {
    foreach (sourceFiles() as $path) {
        $code = php_strip_whitespace($path);

        expect($code)->not->toContain('hasRole', $path)
            ->and($code)->not->toContain('super_admin', $path)
            ->and($code)->not->toContain('hasPermission', $path);
    }
});

it('couples the transport to no storage', function (): void {
    foreach (sourceFiles() as $path) {
        expect(php_strip_whitespace($path))->not->toMatch('/use Liberu\\\\.+\\\\Models\\\\/', $path);
    }
});

it('makes no HTTP call of its own and binds no adapter', function (): void {
    foreach (sourceFiles() as $path) {
        $code = php_strip_whitespace($path);

        expect($code)->not->toContain('Http::', $path)
            ->and($code)->not->toContain('curl_', $path)
            ->and($code)->not->toContain('ActionGateway', $path)
            ->and($code)->not->toContain('TimelineSource', $path);
    }
});

it('names exactly one sibling module, the one it adapts', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    $siblings = array_values(array_filter(
        array_keys(($composer['require'] ?? []) + ($composer['require-dev'] ?? [])),
        static fn (string $package): bool => str_starts_with($package, 'liberusoftware/ecommerce-'),
    ));

    expect($siblings)->toBe(['liberusoftware/ecommerce-customer-service-workspace']);

    // Not on Packagist yet, so the repository entry carries that information.
    expect(array_column($composer['repositories'] ?? [], 'url'))
        ->toBe(['https://github.com/liberusoftware/module-ecommerce-customer-service-workspace']);
});

it('boots nothing on installation', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['extra']['laravel']['providers'] ?? null)->toBeNull();
});

it('agrees with module.json about the version and the provider', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
    $module = json_decode((string) file_get_contents($root.'/module.json'), true);

    expect($module['version'])->toBe($composer['version'])
        ->and(class_exists($module['provider']))->toBeTrue()
        ->and($module['name'])->toBe($composer['extra']['liberu']['name']);
});

it('creates no table of its own', function (): void {
    expect(is_dir(dirname(__DIR__, 2).'/database'))->toBeFalse();
});
