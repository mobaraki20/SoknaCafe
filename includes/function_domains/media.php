<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function upload_image(array $file, ?string $oldPath = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('بارگذاری تصویر ناموفق بود.');
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException('حجم تصویر نباید بیشتر از ۸ مگابایت باشد.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('فرمت تصویر مجاز نیست.');
    }

    $size = @getimagesize($tmp);
    if (!$size || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
        throw new RuntimeException('فایل تصویر معتبر نیست.');
    }
    if ((int)$size[0] * (int)$size[1] > 20_000_000) {
        throw new RuntimeException('ابعاد تصویر بیش از حد بزرگ است.');
    }

    $targetDir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('پوشه تصاویر قابل ساخت نیست.');
    }

    $base = date('YmdHis') . '-' . bin2hex(random_bytes(6));
    $sourceName = $base . '-source.' . $allowed[$mime];
    $sourceTarget = $targetDir . '/' . $sourceName;
    if (!move_uploaded_file($tmp, $sourceTarget)) {
        throw new RuntimeException('ذخیره تصویر ناموفق بود.');
    }

    // Animated GIFs and servers without GD retain the original file safely.
    if ($mime === 'image/gif' || !extension_loaded('gd') || !function_exists('imagewebp')) {
        return 'uploads/' . $sourceName;
    }

    try {
        $image = image_resource_from_file($sourceTarget, $mime);
        if (!$image) {
            return 'uploads/' . $sourceName;
        }
        if ($mime === 'image/jpeg') {
            $image = orient_jpeg_resource($image, $sourceTarget);
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $targets = array_values(array_unique(array_filter([
            min(320, $sourceWidth),
            min(640, $sourceWidth),
            min(1280, $sourceWidth),
        ], static fn(int $width): bool => $width > 0)));
        sort($targets);

        $created = [];
        foreach ($targets as $width) {
            $height = max(1, (int)round($sourceHeight * ($width / $sourceWidth)));
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
            $variantName = $base . '-w' . $width . '.webp';
            $variantTarget = $targetDir . '/' . $variantName;
            if (!imagewebp($canvas, $variantTarget, 82)) {
                imagedestroy($canvas);
                throw new RuntimeException('ساخت نسخه بهینه تصویر ناموفق بود.');
            }
            imagedestroy($canvas);
            $created[$width] = 'uploads/' . $variantName;
        }
        imagedestroy($image);

        if (!$created) {
            return 'uploads/' . $sourceName;
        }
        return $created[max(array_keys($created))];
    } catch (Throwable $e) {
        error_log('Cafe image processing fallback: ' . $e->getMessage());
        foreach (glob($targetDir . '/' . $base . '-w*.webp') ?: [] as $variant) {
            @unlink($variant);
        }
        return 'uploads/' . $sourceName;
    }
}

function image_resource_from_file(string $path, string $mime): GdImage|false
{
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function orient_jpeg_resource(GdImage $image, string $path): GdImage
{
    if (!function_exists('exif_read_data')) return $image;
    $orientation = (int)(@exif_read_data($path)['Orientation'] ?? 1);
    $rotated = match ($orientation) {
        3 => imagerotate($image, 180, 0),
        6 => imagerotate($image, -90, 0),
        8 => imagerotate($image, 90, 0),
        default => false,
    };
    if ($rotated instanceof GdImage) {
        imagedestroy($image);
        return $rotated;
    }
    return $image;
}

/** @return array{src:string,srcset:string,width:int,height:int} */
function responsive_image_data(?string $path): array
{
    $fallback = ['src' => $path ? asset($path) : '', 'srcset' => '', 'width' => 1, 'height' => 1];
    if (!$path || !str_starts_with($path, 'uploads/')) return $fallback;

    $absolute = dirname(__DIR__, 2) . '/' . $path;
    if (!is_file($absolute)) return $fallback;
    $dimensions = @getimagesize($absolute);
    if ($dimensions) {
        $fallback['width'] = (int)$dimensions[0];
        $fallback['height'] = (int)$dimensions[1];
    }

    if (!preg_match('#^(uploads/[A-Za-z0-9._-]+)-w\d+\.webp$#', $path, $match)) {
        return $fallback;
    }

    $baseRelative = $match[1];
    $baseAbsolute = dirname(__DIR__, 2) . '/' . $baseRelative;
    $variants = [];
    foreach (glob($baseAbsolute . '-w*.webp') ?: [] as $file) {
        if (!preg_match('/-w(\d+)\.webp$/', $file, $widthMatch)) continue;
        $width = (int)$widthMatch[1];
        $relative = 'uploads/' . basename($file);
        $variants[$width] = asset($relative);
    }
    if (!$variants) return $fallback;
    ksort($variants);
    $largestWidth = max(array_keys($variants));
    $fallback['src'] = $variants[$largestWidth];
    $fallback['srcset'] = implode(', ', array_map(
        static fn(int $width, string $url): string => $url . ' ' . $width . 'w',
        array_keys($variants),
        array_values($variants)
    ));
    return $fallback;
}

function upload_path_is_referenced(string $path): bool
{
    try {
        $pdo = db();
        foreach ([['items','image_path'],['events','image_path'],['campaigns','image_path'],['categories','image_path']] as [$table,$column]) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column}=? LIMIT 1");
            $stmt->execute([$path]);
            if ($stmt->fetchColumn()) return true;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key='logo_path' AND setting_value=? LIMIT 1");
        $stmt->execute([$path]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // On an incomplete installation, prefer leaving an orphan over deleting a shared asset.
        error_log('Cafe upload reference check: ' . $e->getMessage());
        return true;
    }
}

function delete_upload_path(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) return;
    if (!preg_match('#^uploads/[A-Za-z0-9._-]+$#', $path)) return;
    if (upload_path_is_referenced($path)) return;

    $targetDir = dirname(__DIR__, 2) . '/uploads';
    $basename = basename($path);
    $candidates = [$targetDir . '/' . $basename];
    if (preg_match('/^(.*?)-(?:w\d+\.webp|source\.[A-Za-z0-9]+)$/', $basename, $match)) {
        $candidates = array_merge(
            $candidates,
            glob($targetDir . '/' . $match[1] . '-w*.webp') ?: [],
            glob($targetDir . '/' . $match[1] . '-source.*') ?: []
        );
    }
    foreach (array_unique($candidates) as $file) {
        if (is_file($file)) @unlink($file);
    }
}


/** @return list<array{path:string,label:string,group:string,mtime:int,width:int,height:int}> */
function image_library_entries(int $limit = 240): array
{
    $root = dirname(__DIR__, 2);
    $entries = [];
    $uploadDir = $root . '/uploads';
    $grouped = [];

    foreach (glob($uploadDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [] as $file) {
        if (!is_file($file)) continue;
        $name = basename($file);
        $key = $name;
        $width = 0;
        if (preg_match('/^(.*)-w(\d+)\.webp$/', $name, $match)) {
            $key = $match[1];
            $width = (int)$match[2];
        } elseif (preg_match('/^(.*)-source\.[A-Za-z0-9]+$/', $name, $match)) {
            $key = $match[1];
            $width = -1;
        }
        $candidate = ['path' => 'uploads/' . $name, 'width' => $width, 'mtime' => (int)@filemtime($file)];
        if (!isset($grouped[$key]) || $candidate['width'] > $grouped[$key]['width']) $grouped[$key] = $candidate;
    }
    foreach ($grouped as $candidate) {
        $entries[] = [
            'path' => $candidate['path'],
            'label' => 'تصویر بارگذاری‌شده',
            'group' => 'uploads',
            'mtime' => $candidate['mtime'],
            'width' => (int)((@getimagesize($root . '/' . $candidate['path'])[0] ?? 0)),
            'height' => (int)((@getimagesize($root . '/' . $candidate['path'])[1] ?? 0)),
        ];
    }

    foreach (glob($root . '/assets/menu/default/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [] as $file) {
        if (!is_file($file)) continue;
        $entries[] = [
            'path' => 'assets/menu/default/' . basename($file),
            'label' => pathinfo($file, PATHINFO_FILENAME),
            'group' => 'default',
            'mtime' => (int)@filemtime($file),
            'width' => (int)((@getimagesize($file)[0] ?? 0)),
            'height' => (int)((@getimagesize($file)[1] ?? 0)),
        ];
    }

    usort($entries, static fn(array $a, array $b): int => [$b['mtime'], $a['path']] <=> [$a['mtime'], $b['path']]);
    return array_slice($entries, 0, max(1, min(300, $limit)));
}


/** @return array{width:int,height:int}|null */
function image_path_dimensions(?string $path): ?array
{
    $path = trim((string)$path);
    if ($path === '' || !image_library_path_valid($path)) return null;
    $size = @getimagesize(dirname(__DIR__, 2) . '/' . $path);
    if (!$size) return null;
    return ['width' => (int)$size[0], 'height' => (int)$size[1]];
}

function image_path_is_square(?string $path): bool
{
    $size = image_path_dimensions($path);
    return $size !== null && $size['width'] === $size['height'];
}

function assert_square_uploaded_image(array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری تصویر ناموفق بود.');
    $size = @getimagesize((string)($file['tmp_name'] ?? ''));
    if (!$size) throw new RuntimeException('فایل تصویر معتبر نیست.');
    if ((int)$size[0] !== (int)$size[1]) {
        throw new RuntimeException('تصویر آیتم باید دقیقاً مربع با نسبت ۱:۱ باشد؛ پیشنهاد ما ۱۲۰۰×۱۲۰۰ پیکسل است.');
    }
}

function assert_square_library_image(?string $path): void
{
    if (!image_path_is_square($path)) {
        throw new RuntimeException('تصویر انتخاب‌شده برای آیتم مربع نیست. لطفاً یک تصویر ۱:۱ انتخاب کنید.');
    }
}
function image_library_path_valid(?string $path): bool
{
    $path = trim((string)$path);
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) return false;
    if (!preg_match('#^(uploads|assets/menu/default)/[A-Za-z0-9._-]+$#', $path)) return false;
    if (!preg_match('/\.(?:jpe?g|png|webp|gif)$/i', $path)) return false;
    $root = realpath(dirname(__DIR__, 2));
    $file = realpath(dirname(__DIR__, 2) . '/' . $path);
    if (!$root || !$file || !is_file($file)) return false;
    return str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/');
}

/** @return array{path:?string,uploaded:?string,changed:bool} */
function resolve_image_input(array $file, ?string $oldPath, array $input, bool $allowRemove = true): array
{
    $hasUpload = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $mode = trim((string)($input['image_mode'] ?? ($hasUpload ? 'upload' : 'keep')));
    $path = $oldPath;
    $uploaded = null;

    if ($mode === 'upload') {
        if (!$hasUpload) throw new RuntimeException('برای بارگذاری جدید، اول فایل تصویر را انتخاب کن.');
        $path = upload_image($file, $oldPath);
        if ($path !== $oldPath) $uploaded = $path;
    } elseif ($mode === 'library') {
        $selected = trim((string)($input['image_library'] ?? ''));
        if (!image_library_path_valid($selected)) throw new RuntimeException('تصویر انتخاب‌شده از آلبوم معتبر نیست.');
        $path = $selected;
    } elseif ($mode === 'remove' && $allowRemove) {
        $path = null;
    } elseif ($mode !== 'keep') {
        throw new RuntimeException('روش انتخاب تصویر معتبر نیست.');
    }

    return ['path' => $path, 'uploaded' => $uploaded, 'changed' => $path !== $oldPath];
}

function image_picker_html(?string $currentPath, string $label, string $hint = '', string $accept = 'image/jpeg,image/png,image/webp,image/gif', bool $allowRemove = true, ?string $requiredAspect = null): string
{
    $entries = image_library_entries();
    $currentValid = $currentPath && image_library_path_valid($currentPath);
    ob_start();
    ?>
    <div class="form-group full image-source-picker" data-image-picker data-required-aspect="<?= e((string)$requiredAspect) ?>">
      <label><?= e($label) ?></label>
      <?php if ($currentPath): ?><div class="image-picker-current<?= $requiredAspect === 'square' && !image_path_is_square($currentPath) ? ' is-incompatible' : '' ?>"><img src="<?= e(asset($currentPath)) ?>" alt="تصویر فعلی"><span>تصویر فعلی<?= $requiredAspect === 'square' && !image_path_is_square($currentPath) ? '؛ غیرمربع و نیازمند جایگزینی' : '' ?></span></div><?php endif; ?>
      <div class="image-source-options" role="radiogroup" aria-label="روش انتخاب تصویر">
        <label data-image-mode-trigger="keep"><input type="radio" name="image_mode" value="keep" checked> <?= $currentPath ? 'نگه‌داشتن فعلی' : 'بدون تصویر' ?></label>
        <label data-image-mode-trigger="library"><input type="radio" name="image_mode" value="library"> انتخاب از آلبوم سایت</label>
        <label data-image-mode-trigger="upload"><input type="radio" name="image_mode" value="upload"> بارگذاری از دستگاه</label>
        <?php if ($allowRemove && $currentPath): ?><label data-image-mode-trigger="remove"><input type="radio" name="image_mode" value="remove"> حذف تصویر</label><?php endif; ?>
      </div>
      <div class="image-picker-panel image-library-dialog" data-image-panel="library" role="dialog" aria-modal="true" aria-label="انتخاب تصویر از آلبوم سایت" aria-hidden="true">
        <div class="image-library-backdrop" data-image-library-cancel aria-hidden="true"></div>
        <section class="image-library-surface" role="document">
          <header class="image-library-dialog-head"><div><strong>انتخاب از آلبوم سایت</strong><small>یک تصویر را انتخاب و تأیید کن.</small></div><button class="icon-btn" type="button" data-image-library-cancel aria-label="بستن"><?= ui_icon('close') ?></button></header>
        <?php if ($entries): ?>
          <input class="form-control image-library-search" type="search" inputmode="search" enterkeyhint="search" data-keyboard-dismiss-on-enter placeholder="جست‌وجوی نام تصویر" data-image-library-search>
          <div class="image-library-grid">
            <?php foreach ($entries as $entry): ?>
              <?php $aspectOk = $requiredAspect !== 'square' || ((int)$entry['width'] > 0 && (int)$entry['width'] === (int)$entry['height']); ?>
              <label class="image-library-card<?= $aspectOk ? '' : ' is-incompatible' ?>" data-image-library-card="<?= e(text_lower($entry['label'] . ' ' . basename($entry['path']))) ?>">
                <input type="radio" name="image_library" value="<?= e($entry['path']) ?>" <?= $currentValid && $currentPath === $entry['path'] ? 'checked' : '' ?> <?= $aspectOk ? '' : 'disabled' ?>>
                <img src="<?= e(asset($entry['path'])) ?>" loading="lazy" alt="">
                <span><?= e($entry['group'] === 'uploads' ? 'آلبوم سایت' : $entry['label']) ?><?= $aspectOk ? '' : ' · غیرمربع' ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="empty-state">هنوز تصویری در آلبوم سایت نیست؛ از دستگاه بارگذاری کن.</p><?php endif; ?>
          <footer class="image-library-dialog-actions"><button class="btn btn-light" type="button" data-image-library-cancel>انصراف</button><button class="btn btn-primary" type="button" data-image-library-apply>استفاده از تصویر</button></footer>
        </section>
      </div>
      <div class="image-picker-panel" data-image-panel="upload">
        <input class="form-control" type="file" name="image" accept="<?= e($accept) ?>" <?= $requiredAspect ? 'data-required-aspect="' . e($requiredAspect) . '"' : '' ?>>
        <div class="image-upload-preview hidden" data-image-upload-preview><img alt="پیش‌نمایش تصویر انتخاب‌شده"><div><strong data-image-upload-name></strong><small data-image-upload-meta></small><button class="btn btn-sm btn-light" type="button" data-image-upload-clear>تغییر یا حذف انتخاب</button></div></div>
      </div>
      <?php if ($hint !== ''): ?><small class="muted"><?= e($hint) ?></small><?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

