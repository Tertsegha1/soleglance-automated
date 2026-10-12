<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    protected $table = 'post_media';

    protected $fillable = ['type', 'path', 'mime', 'size'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
