<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

/**
 * Zapis zdjęć i filmów przesłanych przez graczy.
 *  - typ pliku sprawdzany z ZAWARTOŚCI (finfo), nie z nazwy ani nagłówka,
 *  - losowa nazwa i rozszerzenie wynikające z typu,
 *  - katalog storage/uploads/submissions/RRRR/MM/ – poza public/,
 *    zablokowany dla przeglądarki (.htaccess).
 */
final class SubmissionUploadService
{
    private const TYPES = [
        'photo' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'video' => ['video/mp4' => 'mp4', 'video/3gpp' => '3gp', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
    ];

    /** @return array{path:string, mime:string, size:int, sha256:string} ścieżka względna wewnątrz storage/uploads */
    public function store(?array $file, string $kind): array
    {
        if ($file === null) {
            throw new HttpException(422, 'Nie przesłano pliku.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpException(413, 'Plik jest za duży.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new HttpException(422, 'Przesyłanie pliku nie powiodło się. Spróbuj ponownie.');
        }

        $size = (int) filesize((string) $file['tmp_name']);
        $max = (int) config('app.player.upload_max_bytes', 30 * 1024 * 1024);
        if ($size <= 0 || $size > $max) {
            throw new HttpException(413, sprintf('Plik jest za duży (najwyżej %d MB).', intdiv($max, 1024 * 1024)));
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::TYPES[$kind][$mime] ?? null;
        if ($extension === null) {
            throw new HttpException(422, $kind === 'photo'
                ? 'Dozwolone są zdjęcia JPEG, PNG lub WebP.'
                : 'Dozwolone są filmy MP4, 3GP, WebM lub MOV.');
        }

        $relative = 'submissions/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $target = self::root() . '/' . $relative;
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
            throw new \RuntimeException('Nie można utworzyć katalogu na pliki graczy.');
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new \RuntimeException('Nie można zapisać przesłanego pliku.');
        }

        return ['path' => $relative, 'mime' => $mime, 'size' => $size, 'sha256' => (string) hash_file('sha256', $target)];
    }

    public static function delete(string $relative): void
    {
        $path = self::root() . '/' . $relative;
        if (str_starts_with($relative, 'submissions/') && is_file($path)) {
            @unlink($path);
        }
    }

    private static function root(): string
    {
        return rtrim((string) config('app.uploads.path'), '/');
    }
}
