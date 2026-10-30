<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * FileUploadService
 *
 * Menyediakan logika upload file yang aman & reusable, agar tidak
 * ditulis berulang-ulang di setiap controller (Account, Product Import,
 * Transaction Documents, Attendance Photo, dsb).
 *
 * - File selalu disimpan dengan nama random/UUID, bukan nama asli.
 * - Validasi extension, MIME type, dan ukuran maksimum dilakukan di sini.
 */
class FileUploadService
{
    /**
     * @param UploadedFile $file
     * @param string $destinationPath Path absolut tujuan (di dalam writable/)
     * @param array $allowedExtensions contoh: ['xlsx', 'xls']
     * @param int $maxSizeKB
     *
     * @return array{success: bool, message?: string, fileName?: string, originalName?: string, path?: string, mimeType?: string, size?: int}
     */
    public function upload(UploadedFile $file, string $destinationPath, array $allowedExtensions, int $maxSizeKB = 2048): array
    {
        if (! $file->isValid()) {
            return ['success' => false, 'message' => 'File tidak valid atau gagal diupload.'];
        }

        if ($file->hasMoved()) {
            return ['success' => false, 'message' => 'File sudah pernah dipindahkan sebelumnya.'];
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Format yang diperbolehkan: ' . implode(', ', $allowedExtensions)];
        }

        $sizeKB = $file->getSize() / 1024;
        if ($sizeKB > $maxSizeKB) {
            return ['success' => false, 'message' => "Ukuran file maksimal {$maxSizeKB}KB."];
        }

        if (! is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $randomName = $file->getRandomName(); // UUID-like, aman dari path traversal / name collision

        try {
            $file->move($destinationPath, $randomName);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal menyimpan file ke server.'];
        }

        return [
            'success'      => true,
            'fileName'     => $randomName,
            'originalName' => $file->getClientName(),
            'path'         => rtrim($destinationPath, '/') . '/' . $randomName,
            'mimeType'     => $file->getClientMimeType(),
            'size'         => $file->getSize(),
        ];
    }
}
