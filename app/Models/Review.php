<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'external_id',
        'author_name',
        'author_avatar_url',
        'rating',
        'text',
        'business_comment',
        'likes',
        'dislikes',
        'is_pinned',
        'source_updated_at',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'likes' => 'integer',
            'dislikes' => 'integer',
            'is_pinned' => 'boolean',
            'source_updated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * В аватарках Яндекса размер подставляется подстановкой {size}.
     */
    public function avatarUrl(int $size = 50): ?string
    {
        return $this->author_avatar_url === null
            ? null
            : str_replace('{size}', "islands-{$size}", $this->author_avatar_url);
    }
}
