<?php
declare(strict_types=1);

/**
 * Return the URL mount path of this Sokna installation for the current request.
 *
 * The application may live at the web root or under a path such as /cafe, and
 * entrypoints may live at any depth (/admin/update/, /menu/, future modules).
 * Derive the mount from the filesystem-to-URL relationship instead of keeping
 * a fragile allow-list of route directory names.
 */
function app_request_mount_path(
    ?string $scriptName = null,
    ?string $scriptFilename = null,
    ?string $documentRoot = null,
    ?string $appRoot = null
): string {
    $scriptName = str_replace('\\', '/', $scriptName ?? (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptName = '/' . ltrim((string)(parse_url($scriptName, PHP_URL_PATH) ?? $scriptName), '/');
    $scriptFilename = str_replace('\\', '/', $scriptFilename ?? (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $documentRoot = str_replace('\\', '/', $documentRoot ?? (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', $appRoot ?? dirname(__DIR__));

    $normalizeFs = static function (string $path): string {
        if ($path === '') return '';
        $real = realpath($path);
        $path = str_replace('\\', '/', $real !== false ? $real : $path);
        return rtrim($path, '/');
    };
    $appFs = $normalizeFs($appRoot);
    $docFs = $normalizeFs($documentRoot);
    $scriptFs = $normalizeFs($scriptFilename);

    // Preferred path: document root maps directly to URL root.
    if ($appFs !== '' && $docFs !== '' && ($appFs === $docFs || str_starts_with($appFs . '/', $docFs . '/'))) {
        $relative = ltrim(substr($appFs, strlen($docFs)), '/');
        return $relative === '' ? '' : '/' . trim($relative, '/');
    }

    // Alias/symlink/custom-vhost fallback: subtract the known route path from SCRIPT_NAME.
    if ($appFs !== '' && $scriptFs !== '' && ($scriptFs === $appFs || str_starts_with($scriptFs . '/', $appFs . '/'))) {
        $relativeFile = ltrim(substr($scriptFs, strlen($appFs)), '/');
        if ($relativeFile !== '') {
            $suffix = '/' . $relativeFile;
            if (str_ends_with($scriptName, $suffix)) {
                $mount = substr($scriptName, 0, -strlen($suffix));
                return $mount === '' || $mount === '/' ? '' : '/' . trim($mount, '/');
            }
        }
    }

    // Last-resort root entrypoint fallback. Nested routes without a filesystem mapping
    // intentionally do not guess their parent directories as the application root.
    $base = basename($scriptName);
    if (in_array($base, ['index.php','install.php','login.php'], true)) {
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($dir === '.' || $dir === '/') return '';
        return '/' . trim($dir, '/');
    }
    return '';
}
