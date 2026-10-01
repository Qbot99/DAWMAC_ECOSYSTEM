<?php
/**
 * Zdjęcia ogłoszeń.
 *
 * Każde zdjęcie jest dekodowane i zapisywane od nowa jako WebP. Przy okazji
 * znikają WSZYSTKIE metadane (EXIF z lokalizacją GPS domu sprzedającego —
 * wymóg z analizy prawnej, RODO art. 25). Zanim EXIF zniknie, odczytujemy
 * z niego orientację, żeby zdjęcia z telefonu nie leżały na boku.
 *
 * Na dysku: UPLOAD_DIR/{listing_id}/{losowa}.webp  (do 1600 px)
 *           UPLOAD_DIR/{listing_id}/{losowa}_t.webp (miniatura 480 px)
 */

declare(strict_types=1);

const MAX_IMAGES = 8;
const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

function upload_dir(): string
{
    $dir = env('UPLOAD_DIR', GIELDA_ROOT . '/uploads');
    return rtrim($dir, '/');
}

function upload_url(string $path): string
{
    return rtrim(env('UPLOAD_URL', '/uploads'), '/') . '/' . $path;
}

/**
 * Normalizuje $_FILES['images'] do listy [tmp_name, name, size, error].
 */
function uploaded_images(string $field = 'images'): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['tmp_name'])) {
        $f = array_map(fn ($v) => [$v], $f);
    }
    $out = [];
    foreach ($f['tmp_name'] as $i => $tmp) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['tmp' => $tmp, 'name' => $f['name'][$i], 'size' => (int) $f['size'][$i], 'error' => $f['error'][$i]];
    }
    return $out;
}

/** Sprawdza pliki przed zapisem ogłoszenia, żeby nie zostawić pół-ogłoszenia. */
function validate_images(array $files): void
{
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            fail(400, 'Nie udało się wgrać pliku ' . $file['name'] . ' (może jest za duży).');
        }
        if ($file['size'] > MAX_IMAGE_BYTES) {
            fail(400, 'Zdjęcie ' . $file['name'] . ' jest większe niż 12 MB.');
        }
        $info = @getimagesize($file['tmp']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            fail(400, 'Plik ' . $file['name'] . ' nie jest zdjęciem JPG, PNG ani WebP.');
        }
    }
}

/**
 * Zapisuje zdjęcie; zwraca [file, width, height].
 */
function store_image(array $file, int $listingId): array
{
    $info = getimagesize($file['tmp']);
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp']),
        default        => false,
    };
    if (!$img) {
        fail(400, 'Nie da się odczytać zdjęcia ' . $file['name'] . '.');
    }

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp']);
        $img = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    $dir = upload_dir() . '/' . $listingId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail(500, 'Brak miejsca na zdjęcia na serwerze.');
    }

    $name = bin2hex(random_bytes(8));
    [$w, $h] = save_resized($img, $dir . '/' . $name . '.webp', 1600, 82);
    save_resized($img, $dir . '/' . $name . '_t.webp', 480, 75);
    imagedestroy($img);

    return [$name . '.webp', $w, $h];
}

function save_resized(GdImage $src, string $dest, int $maxEdge, int $quality): array
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxEdge / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    // Przezroczyste PNG na białym tle — WebP z alfą bywa źle pokazywany w podglądach.
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($dst, $dest, $quality);
    imagedestroy($dst);

    return [$nw, $nh];
}

function delete_image_files(int $listingId, string $file): void
{
    $dir = upload_dir() . '/' . $listingId;
    $base = basename($file, '.webp');
    @unlink($dir . '/' . $base . '.webp');
    @unlink($dir . '/' . $base . '_t.webp');
}

function delete_listing_files(int $listingId): void
{
    $dir = upload_dir() . '/' . $listingId;
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

function image_view(int $listingId, array $row): array
{
    $base = basename($row['file'], '.webp');
    return [
        'id'     => (int) $row['id'],
        'url'    => upload_url($listingId . '/' . $row['file']),
        'thumb'  => upload_url($listingId . '/' . $base . '_t.webp'),
        'width'  => (int) $row['width'],
        'height' => (int) $row['height'],
    ];
}
