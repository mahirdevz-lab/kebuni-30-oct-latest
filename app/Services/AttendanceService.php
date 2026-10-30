<?php

namespace App\Services;

use App\Models\AttendanceModel;
use App\Models\CompanyLocationModel;
use App\Models\SettingModel;

/**
 * Absensi publik (tanpa login): tanggal & jam SELALU ditentukan server,
 * lokasi divalidasi terhadap company_locations aktif (radius geofence).
 */
class AttendanceService
{
    protected const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

    protected AttendanceModel $model;
    protected CompanyLocationModel $locationModel;

    public function __construct()
    {
        $this->model         = new AttendanceModel();
        $this->locationModel = new CompanyLocationModel();
    }

    public function submit(array $input, string $ip, string $userAgent): array
    {
        $nama = trim((string) ($input['nama'] ?? ''));
        $type = $input['attendance_type'] ?? '';

        if (mb_strlen($nama) < 3 || mb_strlen($nama) > 150) {
            return ['success' => false, 'message' => 'Nama wajib diisi (minimal 3 karakter).'];
        }
        if (! in_array($type, ['CHECK_IN', 'CHECK_OUT'], true)) {
            return ['success' => false, 'message' => 'Jenis absensi tidak valid.'];
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');

        if ($this->model->existsFor($nama, $date, $type)) {
            $label = $type === 'CHECK_IN' ? 'masuk' : 'pulang';

            return ['success' => false, 'message' => "Anda sudah absen {$label} hari ini."];
        }

        if ($type === 'CHECK_OUT' && ! $this->model->existsFor($nama, $date, 'CHECK_IN')) {
            return ['success' => false, 'message' => 'Anda belum absen masuk hari ini.'];
        }

        $photo = $this->savePhoto((string) ($input['photo'] ?? ''));
        if (! $photo['success']) {
            return $photo;
        }

        $lat      = $this->numberOrNull($input['latitude'] ?? null);
        $lng      = $this->numberOrNull($input['longitude'] ?? null);
        $accuracy = $this->numberOrNull($input['accuracy'] ?? null);
        $geo      = $this->evaluateLocation($lat, $lng);

        $this->model->insert([
            'nama'                 => $nama,
            'attendance_type'      => $type,
            'attendance_date'      => $date,
            'attendance_time'      => $time,
            'photo_path'           => $photo['path'],
            'latitude'             => $lat,
            'longitude'            => $lng,
            'accuracy'             => $accuracy,
            'company_location_id'  => $geo['location_id'],
            'distance_from_office' => $geo['distance'],
            'location_status'      => $geo['status'],
            'is_late'              => ($type === 'CHECK_IN' && $time > $this->lateAfter()) ? 1 : 0,
            'device_info'          => mb_substr((string) ($input['device_info'] ?? ''), 0, 255) ?: null,
            'ip_address'           => $ip,
            'user_agent'           => mb_substr($userAgent, 0, 255),
        ]);

        return [
            'success'         => true,
            'message'         => 'Absensi ' . ($type === 'CHECK_IN' ? 'masuk' : 'pulang') . ' berhasil dicatat pukul ' . substr($time, 0, 5) . '.',
            'location_status' => $geo['status'],
        ];
    }

    /** Batas jam masuk dari Setting (format HH:MM), check-in setelahnya dianggap terlambat. */
    protected function lateAfter(): string
    {
        $value = (new SettingModel())->value('attendance_late_after', '08:00') ?: '08:00';

        return substr($value, 0, 5) . ':00';
    }

    protected function numberOrNull($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** Cari lokasi aktif terdekat; INSIDE jika dalam radius. */
    protected function evaluateLocation(?float $lat, ?float $lng): array
    {
        $none = ['status' => 'UNKNOWN', 'location_id' => null, 'distance' => null];

        if ($lat === null || $lng === null) {
            return $none;
        }

        $best = null;
        foreach ($this->locationModel->activeLocations() as $loc) {
            $distance = $this->haversineMeters($lat, $lng, (float) $loc['latitude'], (float) $loc['longitude']);
            if ($best === null || $distance < $best['distance']) {
                $best = ['location' => $loc, 'distance' => $distance];
            }
        }

        if ($best === null) {
            return $none;
        }

        return [
            'status'      => $best['distance'] <= (float) $best['location']['radius_meter'] ? 'INSIDE_AREA' : 'OUTSIDE_AREA',
            'location_id' => (int) $best['location']['id'],
            'distance'    => round($best['distance'], 2),
        ];
    }

    protected function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** Simpan foto dari data URL (JPEG/PNG) ke writable/uploads/attendance. */
    protected function savePhoto(string $dataUrl): array
    {
        if (! preg_match('#^data:image/(jpeg|png);base64,(.+)$#', $dataUrl, $m)) {
            return ['success' => false, 'message' => 'Foto selfie wajib diambil.'];
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) > self::MAX_PHOTO_BYTES) {
            return ['success' => false, 'message' => 'Foto tidak valid atau lebih dari 2MB.'];
        }

        if (@getimagesizefromstring($binary) === false) {
            return ['success' => false, 'message' => 'File foto bukan gambar yang valid.'];
        }

        $dir = WRITEPATH . 'uploads/attendance/' . date('Y/m');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return ['success' => false, 'message' => 'Gagal menyiapkan penyimpanan foto.'];
        }

        $fileName = bin2hex(random_bytes(16)) . ($m[1] === 'png' ? '.png' : '.jpg');
        if (file_put_contents($dir . '/' . $fileName, $binary) === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan foto.'];
        }

        return ['success' => true, 'path' => 'attendance/' . date('Y/m') . '/' . $fileName];
    }

    // ---------------- ADMIN ----------------

    // perPage disamakan ke 10 mengikuti Config\Pager (audit pagination §3.3 / BUG-2).
    public function paginate(?string $search, ?string $from, ?string $to, ?string $type, ?string $locationStatus, int $perPage = 10)
    {
        return $this->model->scopeList($search, $from, $to, $type, $locationStatus)->paginate($perPage);
    }

    public function pager()
    {
        return $this->model->pager;
    }

    public function recap(string $from, string $to, ?string $search = null): array
    {
        return $this->model->recap($from, $to, $search);
    }

    public function summaryForDate(?string $date = null): array
    {
        return $this->model->summaryForDate($date ?: date('Y-m-d'));
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function locations(): array
    {
        return $this->locationModel->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Daftar lokasi kantor berpaginasi + pencarian (BUG-1/BUG-3 PRD-BACKOFFICE-UI T6).
     */
    public function paginateLocations(?string $search, int $perPage = 10)
    {
        return $this->locationModel->scopeList($search)->paginate($perPage);
    }

    public function locationsPager()
    {
        return $this->locationModel->pager;
    }

    public function saveLocation(?int $id, array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';
        $ok             = $id ? $this->locationModel->update($id, $data) : $this->locationModel->insert($data);

        if (! $ok) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->locationModel->errors()];
        }

        return ['success' => true, 'message' => 'Lokasi kantor berhasil disimpan.'];
    }

    public function deleteLocation(int $id): array
    {
        $this->locationModel->delete($id); // FK attendances -> SET NULL

        return ['success' => true, 'message' => 'Lokasi kantor berhasil dihapus.'];
    }

    public function toggleLocation(int $id): array
    {
        $loc = $this->locationModel->find($id);
        if (! $loc) {
            return ['success' => false, 'message' => 'Lokasi tidak ditemukan.'];
        }
        $this->locationModel->update($id, ['status' => $loc['status'] === 'aktif' ? 'nonaktif' : 'aktif']);

        return ['success' => true, 'message' => 'Status lokasi diperbarui.'];
    }
}
