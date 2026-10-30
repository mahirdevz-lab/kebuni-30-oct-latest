<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['setting_key', 'setting_value'];
    protected $useTimestamps = true;

    public function value(string $key, ?string $default = null): ?string
    {
        $row = $this->where('setting_key', $key)->first();

        return $row ? ($row['setting_value'] ?? $default) : $default;
    }

    /** @return array<string,string|null> */
    public function allValues(): array
    {
        return array_column($this->findAll(), 'setting_value', 'setting_key');
    }

    public function put(string $key, ?string $value): void
    {
        $row = $this->where('setting_key', $key)->first();

        if ($row) {
            $this->update($row['id'], ['setting_value' => $value]);
        } else {
            $this->insert(['setting_key' => $key, 'setting_value' => $value]);
        }
    }
}
