<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    protected $table         = 'attendances';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nama', 'attendance_type', 'attendance_date', 'attendance_time', 'photo_path',
        'latitude', 'longitude', 'accuracy', 'company_location_id', 'distance_from_office',
        'location_status', 'is_late', 'device_info', 'ip_address', 'user_agent', 'notes',
    ];
    protected $useTimestamps = true;

    public function existsFor(string $nama, string $date, string $type): bool
    {
        return $this->where('nama', $nama)
            ->where('attendance_date', $date)
            ->where('attendance_type', $type)
            ->countAllResults() > 0;
    }

    private function applyFilters($builder, ?string $search, ?string $from, ?string $to, ?string $type, ?string $locationStatus)
    {
        if ($search) {
            $builder->like('attendances.nama', $search);
        }
        if ($from) {
            $builder->where('attendances.attendance_date >=', $from);
        }
        if ($to) {
            $builder->where('attendances.attendance_date <=', $to);
        }
        if ($type) {
            $builder->where('attendances.attendance_type', $type);
        }
        if ($locationStatus) {
            $builder->where('attendances.location_status', $locationStatus);
        }

        return $builder;
    }

    public function scopeList(?string $search = null, ?string $from = null, ?string $to = null, ?string $type = null, ?string $locationStatus = null)
    {
        $builder = $this->select('attendances.*, company_locations.name as location_name')
            ->join('company_locations', 'company_locations.id = attendances.company_location_id', 'left')
            ->orderBy('attendances.attendance_date', 'DESC')
            ->orderBy('attendances.attendance_time', 'DESC');

        return $this->applyFilters($builder, $search, $from, $to, $type, $locationStatus);
    }

    /** Per orang dalam rentang tanggal: hari hadir, telat, di luar area. */
    public function recap(string $from, string $to, ?string $search = null): array
    {
        $builder = $this->select("
                nama,
                COUNT(DISTINCT CASE WHEN attendance_type = 'CHECK_IN' THEN attendance_date END) as days_present,
                SUM(CASE WHEN attendance_type = 'CHECK_IN' AND is_late = 1 THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN location_status = 'OUTSIDE_AREA' THEN 1 ELSE 0 END) as outside_count,
                SUM(CASE WHEN attendance_type = 'CHECK_OUT' THEN 1 ELSE 0 END) as checkout_count
            ")
            ->where('attendance_date >=', $from)
            ->where('attendance_date <=', $to)
            ->groupBy('nama')
            ->orderBy('nama', 'ASC');

        if ($search) {
            $builder->like('nama', $search);
        }

        return $builder->findAll();
    }

    public function summaryForDate(string $date): array
    {
        $row = $this->select("
                SUM(CASE WHEN attendance_type = 'CHECK_IN' THEN 1 ELSE 0 END) as check_in,
                SUM(CASE WHEN attendance_type = 'CHECK_OUT' THEN 1 ELSE 0 END) as check_out,
                SUM(CASE WHEN attendance_type = 'CHECK_IN' AND is_late = 1 THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN location_status = 'OUTSIDE_AREA' THEN 1 ELSE 0 END) as outside
            ")
            ->where('attendance_date', $date)
            ->first();

        return [
            'date'      => $date,
            'check_in'  => (int) ($row['check_in'] ?? 0),
            'check_out' => (int) ($row['check_out'] ?? 0),
            'late'      => (int) ($row['late'] ?? 0),
            'outside'   => (int) ($row['outside'] ?? 0),
        ];
    }
}
