<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Komentar / notes review pada konten sosmed (thread dua arah).
 */
class SosmedCommentModel extends Model
{
    protected $table            = 'sosmed_comments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['content_id', 'user_id', 'message'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = false;

    /**
     * Thread komentar satu konten + nama penulis.
     */
    public function thread(int $contentId): array
    {
        return $this->select('sosmed_comments.*, users.full_name AS author_name')
            ->join('users', 'users.id = sosmed_comments.user_id', 'left')
            ->where('sosmed_comments.content_id', $contentId)
            ->orderBy('sosmed_comments.created_at', 'ASC')
            ->findAll();
    }
}
