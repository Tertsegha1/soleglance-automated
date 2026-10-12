<?php

namespace App\Models;

use App\Enums\Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'feed_url', 'category', 'country', 'language', 'active'];

    protected function casts(): array
    {
        return [
            'category' => Category::class,
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
