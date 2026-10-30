<?php

namespace App\Services;

use RuntimeException;

/** Kegagalan aturan bisnis stok (mis. stok tidak cukup). Pesannya aman ditampilkan ke kasir. */
class StockException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $productId = null, public readonly ?float $available = null, public readonly ?float $requested = null)
    {
        parent::__construct($message);
    }
}
