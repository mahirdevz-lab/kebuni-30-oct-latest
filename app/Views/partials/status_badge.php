<?php
/**
 * Badge status reusable — satu sumber warna semantik untuk seluruh modul.
 *
 * Dipakai via:
 *   <?= $this->include('partials/status_badge', ['status' => $row['status']]) ?>
 * Opsi:
 *   ['status' => ..., 'label' => 'Teks custom', 'map' => ['custom_status' => 'bg-primary']]
 */
$status = strtolower(trim((string) ($status ?? '')));

$defaults = [
    'aktif'      => 'bg-success',
    'nonaktif'   => 'bg-secondary',
    'draft'      => 'bg-secondary',
    'published'  => 'bg-primary',
    'closed'     => 'bg-dark',
    'pending'    => 'bg-warning',
    'submitted'  => 'bg-info',
    'approved'   => 'bg-info',
    'rejected'   => 'bg-danger',
    'processing' => 'bg-primary',
    'completed'  => 'bg-success',
    'complete'   => 'bg-success',
    'cancelled'  => 'bg-secondary',
    'canceled'   => 'bg-secondary',
    'success'    => 'bg-success',
    'failed'     => 'bg-danger',
    'expired'    => 'bg-dark',
];

$map   = $map ?? [];
$class = $map[$status] ?? $defaults[$status] ?? 'bg-secondary';
$label = $label ?? ucfirst($status);
?>
<span class="badge <?= $class ?>"><?= esc($label) ?></span>
