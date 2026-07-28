<?php

/**
 * Static analysis helpers for project_audit_and_test.cmd.
 *
 * Kept as a PHP file (rather than inline `php -r` in the batch script) because
 * CMD delayed expansion eats "!" and mangles quoting.
 *
 * Usage: php diagnostics/audit_checks.php <check>
 *
 * Checks: ini, ext, env, routes, lint, blade, forms, booleans, coverage, fields, fillable
 * Exit code 1 means the check found a problem worth failing the audit for.
 */

$root = dirname(__DIR__);
chdir($root);

$check = $argv[1] ?? '';

/** @return list<string> */
function phpFiles(string $dir, string $extension = 'php'): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** @return list<string> */
function bladeFiles(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function relative(string $path): string
{
    return str_replace([dirname(__DIR__).DIRECTORY_SEPARATOR, '\\'], ['', '/'], $path);
}

function bootLaravel(): void
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $app = require_once dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

exit(match ($check) {
    'ini' => checkIni(),
    'ext' => checkExtensions(),
    'env' => checkEnv(),
    'routes' => checkRoutes(),
    'lint' => checkLint(),
    'blade' => checkBlade(),
    'forms' => checkForms(),
    'booleans' => checkBooleans(),
    'coverage' => checkCoverage(),
    'fields' => checkFields(),
    'fillable' => checkFillable(),
    default => usage(),
});

function usage(): int
{
    echo 'Usage: php diagnostics/audit_checks.php ',
        '[ini|ext|env|routes|lint|blade|forms|booleans|coverage|fields|fillable]', PHP_EOL;

    return 2;
}

function checkIni(): int
{
    $keys = [
        'post_max_size',
        'upload_max_filesize',
        'memory_limit',
        'max_execution_time',
        'max_file_uploads',
        'max_input_vars',
    ];

    foreach ($keys as $key) {
        echo str_pad($key, 22), '= ', (string) ini_get($key), PHP_EOL;
    }

    // Admin forms with many gallery rows post hundreds of inputs.
    $inputVars = (int) ini_get('max_input_vars');
    if ($inputVars > 0 && $inputVars < 3000) {
        echo 'NOTE: max_input_vars=', $inputVars,
            ' may truncate large admin forms (landing pages, hero slides). 3000+ recommended.', PHP_EOL;
    }

    $postMax = ini_get('post_max_size');
    $uploadMax = ini_get('upload_max_filesize');
    echo 'NOTE: single-image admin uploads must stay under ', $uploadMax,
        ' and the whole form under ', $postMax, '.', PHP_EOL;

    return 0;
}

function checkExtensions(): int
{
    $required = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl'];
    $recommended = ['gd', 'imagick', 'exif', 'zip', 'intl'];

    $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded($ext)));
    $missingOptional = array_values(array_filter($recommended, fn ($ext) => ! extension_loaded($ext)));

    echo $missing === []
        ? 'All required extensions loaded.'.PHP_EOL
        : 'MISSING REQUIRED: '.implode(', ', $missing).PHP_EOL;

    if ($missingOptional !== []) {
        echo 'Missing optional: ', implode(', ', $missingOptional), PHP_EOL;
    }

    return $missing === [] ? 0 : 1;
}

function checkEnv(): int
{
    if (! is_file('.env')) {
        echo 'FAIL: .env not found', PHP_EOL;

        return 1;
    }

    $safe = [
        'APP_ENV', 'APP_DEBUG', 'APP_URL', 'LOG_CHANNEL', 'LOG_LEVEL',
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE',
        'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'FILESYSTEM_DISK', 'MAIL_MAILER',
    ];

    $values = [];
    foreach (file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), '"\'');
    }

    foreach ($safe as $key) {
        if (array_key_exists($key, $values)) {
            echo str_pad($key, 20), '= ', $values[$key], PHP_EOL;
        }
    }

    $problems = 0;

    if (($values['APP_KEY'] ?? '') === '') {
        echo 'FAIL: APP_KEY is empty - run php artisan key:generate', PHP_EOL;
        $problems++;
    }

    if (($values['APP_ENV'] ?? '') === 'production'
        && in_array(strtolower($values['APP_DEBUG'] ?? ''), ['true', '1', 'on'], true)) {
        echo 'FAIL: APP_DEBUG is enabled while APP_ENV=production', PHP_EOL;
        $problems++;
    }

    return $problems === 0 ? 0 : 1;
}

function checkRoutes(): int
{
    $file = getenv('ROUTEJSON') ?: (sys_get_temp_dir().'/vyomika-routes.json');
    if (! is_file($file)) {
        echo 'SKIP: route json not available', PHP_EOL;

        return 0;
    }

    $routes = json_decode((string) file_get_contents($file), true) ?: [];
    $admin = array_values(array_filter($routes, fn ($r) => str_starts_with($r['uri'] ?? '', 'admin')));
    $writes = array_values(array_filter(
        $admin,
        fn ($r) => (bool) preg_match('/POST|PUT|PATCH|DELETE/', (string) ($r['method'] ?? ''))
    ));

    echo 'total routes       : ', count($routes), PHP_EOL;
    echo 'admin routes       : ', count($admin), PHP_EOL;
    echo 'admin write routes : ', count($writes), PHP_EOL;
    echo PHP_EOL;

    $problems = 0;

    foreach ($admin as $route) {
        if (($route['action'] ?? '') === 'Closure') {
            echo 'CLOSURE ACTION: ', $route['method'], ' ', $route['uri'], PHP_EOL;
            $problems++;
        }
    }

    // Every admin write route must sit behind the admin guard. Login and logout
    // are the exceptions: both have to work while the session is not (or no
    // longer) an authenticated admin one.
    $guardExempt = ['admin/login', 'admin/logout'];

    foreach ($writes as $route) {
        $middleware = (array) ($route['middleware'] ?? []);
        $flat = implode(',', array_map('strval', $middleware));
        if (! str_contains($flat, 'admin') && ! in_array((string) ($route['uri'] ?? ''), $guardExempt, true)) {
            echo 'UNGUARDED ADMIN WRITE: ', $route['method'], ' ', $route['uri'], PHP_EOL;
            $problems++;
        }
    }

    echo PHP_EOL, 'Admin write routes:', PHP_EOL;
    foreach ($writes as $route) {
        echo '  ', str_pad((string) $route['method'], 18), str_pad((string) $route['uri'], 52),
            (string) ($route['name'] ?? ''), PHP_EOL;
    }

    return $problems === 0 ? 0 : 1;
}

function checkLint(): int
{
    $failures = 0;
    $scanned = 0;

    foreach (['app', 'routes', 'config', 'database', 'tests'] as $dir) {
        foreach (phpFiles($dir) as $file) {
            $scanned++;
            $output = [];
            $status = 0;
            exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);
            if ($status !== 0) {
                echo 'SYNTAX ERROR: ', relative($file), PHP_EOL, implode(PHP_EOL, $output), PHP_EOL;
                $failures++;
            }
        }
    }

    echo 'php files scanned: ', $scanned, PHP_EOL;
    echo $failures === 0 ? 'PHP lint clean.'.PHP_EOL : ('Lint failures: '.$failures.PHP_EOL);

    return $failures === 0 ? 0 : 1;
}

/**
 * Compile every Blade template and PHP-lint the result. `view:cache` writes the
 * compiled file without validating it, so broken templates only surface when a
 * real request (or a queued mail) renders them.
 */
function checkBlade(): int
{
    bootLaravel();

    /** @var \Illuminate\View\Compilers\BladeCompiler $compiler */
    $compiler = app('blade.compiler');
    $tmp = sys_get_temp_dir().'/vyomika-blade-check.php';
    $failures = 0;
    $scanned = 0;

    foreach (bladeFiles('resources/views') as $file) {
        $scanned++;
        $compiled = $compiler->compileString((string) file_get_contents($file));
        file_put_contents($tmp, $compiled);

        $output = [];
        $status = 0;
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $output, $status);

        if ($status !== 0) {
            echo 'BROKEN TEMPLATE: ', relative($file), PHP_EOL;
            foreach ($output as $line) {
                if (str_contains($line, 'No syntax errors')) {
                    continue;
                }
                echo '    ', trim($line), PHP_EOL;
            }
            $failures++;
        }
    }

    @unlink($tmp);

    echo 'blade files scanned: ', $scanned, PHP_EOL;
    echo $failures === 0 ? 'All Blade templates compile.'.PHP_EOL : ('Broken templates: '.$failures.PHP_EOL);

    return $failures === 0 ? 0 : 1;
}

function checkForms(): int
{
    $files = bladeFiles('resources/views/admin');
    if ($files === []) {
        echo 'no admin views', PHP_EOL;

        return 0;
    }

    $problems = 0;

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);

        if (! preg_match('/<form/i', $contents)) {
            continue;
        }
        if (! preg_match('/method\s*=\s*.(post|POST)/', $contents)) {
            continue;
        }
        if (str_contains($contents, '@error')
            || str_contains($contents, 'errors->any()')
            || str_contains($contents, 'errors->all()')
            || str_contains($contents, 'errors->has(')) {
            continue;
        }

        // Layouts commonly render a shared error banner; treat that as covered.
        if (str_contains($contents, "@extends('layouts.admin')")
            && str_contains((string) @file_get_contents('resources/views/layouts/admin.blade.php'), 'errors->any()')) {
            continue;
        }

        echo 'NO ERROR DISPLAY: ', relative($file), PHP_EOL;
        $problems++;
    }

    echo $problems === 0
        ? 'All admin POST forms surface validation errors.'.PHP_EOL
        : ('Forms hiding validation errors: '.$problems.PHP_EOL);

    return 0;
}

function checkBooleans(): int
{
    $problems = 0;

    foreach (phpFiles('app/Http/Controllers') as $file) {
        foreach (file($file) as $index => $line) {
            // ->boolean('flag', true) silently re-enables an unchecked checkbox.
            if (preg_match('/->boolean\(\s*.[A-Za-z0-9_]+.\s*,\s*true\s*\)/', $line)) {
                echo 'DEFAULT-TRUE CHECKBOX: ', relative($file), ':', $index + 1, ' ', trim($line), PHP_EOL;
                $problems++;
            }
        }
    }

    echo $problems === 0
        ? 'No default-true checkbox reads found.'.PHP_EOL
        : ('Default-true checkbox reads: '.$problems.PHP_EOL);

    return $problems === 0 ? 0 : 1;
}

/**
 * A controller counts as covered when a test names one of its own routes, which
 * is how the feature tests actually reach it. Matching on the class name alone
 * reported false gaps for controllers whose route names differ from the class.
 */
function checkCoverage(): int
{
    $controllers = glob('app/Http/Controllers/Admin/*.php') ?: [];
    if ($controllers === []) {
        return 0;
    }

    $routeFile = getenv('ROUTEJSON') ?: (sys_get_temp_dir().'/vyomika-routes.json');
    $routes = is_file($routeFile)
        ? (json_decode((string) file_get_contents($routeFile), true) ?: [])
        : [];

    /** @var array<string, list<string>> $routeNames */
    $routeNames = [];
    foreach ($routes as $route) {
        $action = (string) ($route['action'] ?? '');
        $name = (string) ($route['name'] ?? '');
        if ($name === '' || ! str_contains($action, 'Controllers\Admin\\')) {
            continue;
        }
        $class = basename(str_replace('\\', '/', explode('@', $action)[0]));
        $routeNames[$class][] = $name;
    }

    $tests = '';
    foreach (phpFiles('tests') as $file) {
        $tests .= (string) file_get_contents($file);
    }

    $uncovered = 0;

    foreach ($controllers as $controller) {
        $base = basename($controller, '.php');

        $needles = [$base];
        foreach ($routeNames[$base] ?? [] as $name) {
            $needles[] = "'".$name."'";
        }

        $covered = false;
        foreach ($needles as $needle) {
            if (stripos($tests, $needle) !== false) {
                $covered = true;

                break;
            }
        }

        echo str_pad($base, 48), $covered ? 'covered' : 'NO TEST REFERENCE', PHP_EOL;
        $uncovered += $covered ? 0 : 1;
    }

    if ($uncovered > 0) {
        echo 'Admin controllers without any test reference: ', $uncovered, PHP_EOL;
    }

    return 0;
}

/**
 * Mass-assignment drops are a silent "admin saved but nothing changed" cause:
 * Eloquent quietly ignores keys that are absent from $fillable. For every model
 * an admin controller mass-assigns, compare the keys the controller writes with
 * the real table columns, and report columns that exist but are not fillable.
 */
function checkFillable(): int
{
    bootLaravel();

    $problems = 0;
    $checked = 0;

    foreach (phpFiles('app/Http/Controllers/Admin') as $file) {
        $source = (string) file_get_contents($file);

        if (! preg_match_all(
            '/\b([A-Z][A-Za-z0-9_]*)::(?:query\(\)->)?(?:create|updateOrCreate|firstOrCreate)\s*\(/',
            $source,
            $matches
        )) {
            continue;
        }

        // Keys the controller assigns, e.g. 'display_order' => ...
        preg_match_all("/'([a-z0-9_]+)'\s*=>/i", $source, $assigned);
        $assignedKeys = array_unique($assigned[1]);

        foreach (array_unique($matches[1]) as $model) {
            $class = 'App\\Models\\'.$model;
            if (! class_exists($class)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $class;

            // An empty $guarded means every attribute is mass assignable.
            if ($instance->getGuarded() === []) {
                continue;
            }

            $fillable = $instance->getFillable();
            if ($fillable === []) {
                continue;
            }

            try {
                $columns = Illuminate\Support\Facades\Schema::getColumnListing($instance->getTable());
            } catch (Throwable $e) {
                echo 'SKIP ', $model, ': ', $e->getMessage(), PHP_EOL;

                continue;
            }

            if ($columns === []) {
                continue;
            }

            $checked++;

            $skip = array_merge(
                [$instance->getKeyName(), 'created_at', 'updated_at', 'deleted_at'],
                $fillable
            );

            $unfillable = array_values(array_intersect(
                array_diff($assignedKeys, $skip),
                $columns
            ));

            if ($unfillable !== []) {
                sort($unfillable);
                echo 'NOT FILLABLE: ', relative($file), ' -> ', $model,
                    ' [', implode(', ', $unfillable), ']', PHP_EOL;
                $problems += count($unfillable);
            }
        }
    }

    echo 'controller/model write pairs checked: ', $checked, PHP_EOL;
    echo $problems === 0
        ? 'No mass-assignment gaps found.'.PHP_EOL
        : ('Mass-assignment gaps: '.$problems.PHP_EOL);

    return $problems === 0 ? 0 : 1;
}

/**
 * Flag admin form inputs that no controller (or its form request) ever reads,
 * i.e. fields the UI offers but that silently never persist.
 */
function checkFields(): int
{
    $map = [
        'products' => 'ProductAdminController',
        'categories' => 'CategoryAdminController',
        'projects' => 'ProjectAdminController',
        'blog' => 'BlogAdminController',
        'exhibitions' => 'ExhibitionAdminController',
        'services' => 'ServiceAdminController',
        'collection-pages' => 'CollectionPageAdminController',
        'page-heroes' => 'PageHeroAdminController',
        'independent-pages' => 'IndependentLandingAdminController',
        'static-pages' => 'StaticPageSeoAdminController',
        'redirects' => 'UrlRedirectAdminController',
        'customers' => 'CustomerAdminController',
        'orders' => 'OrderAdminController',
        'leads' => 'LeadAdminController',
        'settings' => 'SiteSettingAdminController',
        'legal' => 'LegalPageAdminController',
        'media' => 'MediaAdminController',
        'professional-applications' => 'ProfessionalApplicationAdminController',
        'railing-quotes' => 'RailingQuoteAdminController',
    ];

    $ignore = ['_token', '_method', 'q', 'page', 'filter', 'search', '_page_save', '_landing_save'];
    $problems = 0;

    foreach ($map as $viewDir => $controller) {
        $viewPath = 'resources/views/admin/'.$viewDir;
        $controllerPath = 'app/Http/Controllers/Admin/'.$controller.'.php';

        if (! is_dir($viewPath) || ! is_file($controllerPath)) {
            echo str_pad($viewDir, 30), 'SKIP (missing view dir or controller)', PHP_EOL;

            continue;
        }

        $source = (string) file_get_contents($controllerPath);

        // Form request classes and traits widen the set of handled fields.
        if (preg_match_all('/use App\\\\Http\\\\(Requests|Controllers\\\\Admin\\\\Concerns)\\\\([A-Za-z0-9_]+);/', $source, $matches)) {
            foreach ($matches[0] as $index => $ignored) {
                $group = $matches[1][$index] === 'Requests' ? 'Requests' : 'Controllers/Admin/Concerns';
                $extra = 'app/Http/'.$group.'/'.$matches[2][$index].'.php';
                if (is_file($extra)) {
                    $source .= (string) file_get_contents($extra);
                }
            }
        }

        $names = [];
        foreach (bladeFiles($viewPath) as $file) {
            if (preg_match_all('/name\s*=\s*"([^"{}]+)"/', (string) file_get_contents($file), $matches)) {
                foreach ($matches[1] as $name) {
                    $names[$name] = true;
                }
            }
        }

        $missing = [];
        foreach (array_keys($names) as $name) {
            $base = preg_replace('/\[[^\]]*\]/', '', $name);
            if ($base === '' || in_array($base, $ignore, true)) {
                continue;
            }
            if (! preg_match('/[\'"]'.preg_quote((string) $base, '/').'(\.|\[|[\'"])/', $source)) {
                $missing[$name] = true;
            }
        }

        if ($missing === []) {
            echo str_pad($viewDir, 30), 'all form fields handled', PHP_EOL;

            continue;
        }

        $missing = array_keys($missing);
        sort($missing);
        echo str_pad($viewDir, 30), 'UNHANDLED: ', implode(', ', $missing), PHP_EOL;
        $problems += count($missing);
    }

    echo PHP_EOL, $problems === 0
        ? 'No unhandled admin form fields.'.PHP_EOL
        : ('Unhandled admin form fields: '.$problems.PHP_EOL);

    return 0;
}
