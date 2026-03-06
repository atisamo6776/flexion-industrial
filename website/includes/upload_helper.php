<?php

/**
 * Güvenli dosya yükleme yardımcısı.
 *
 * @param  array  $file        $_FILES['field'] dizisi
 * @param  string $uploadDir   Yüklenecek dizin (gerçek path, slash ile bitmeli)
 * @param  array  $allowedMime İzin verilen MIME tiplerinin listesi
 * @param  int    $maxBytes    Maksimum dosya boyutu (byte)
 * @return string|null         Dosya adı (sadece adı, path değil) veya hata durumunda null
 */
function upload_file(
    array $file,
    string $uploadDir,
    array $allowedMime = ['image/jpeg', 'image/png', 'image/webp'],
    int $maxBytes = 5 * 1024 * 1024
): ?string {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($file['size'] > $maxBytes) {
        return null;
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        return null;
    }

    // Uzantıyı orijinal isimden değil, MIME'dan al
    $mimeExtMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'image/svg+xml'   => 'svg',
        'application/pdf' => 'pdf',
        'application/msword'                                                          => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'    => 'docx',
        'application/vnd.ms-excel'                                                   => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'          => 'xlsx',
    ];
    $ext = $mimeExtMap[$mime] ?? 'bin';

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = rtrim($uploadDir, '/') . '/' . $name;

    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $name;
}
