<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketplaceSkill extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'category',
        'tags',
        'frontmatter',
        'body',
        'author',
        'source',
        'downloads',
        'upvotes',
        'downvotes',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'frontmatter' => 'array',
            'downloads' => 'integer',
            'upvotes' => 'integer',
            'downvotes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceSkill $skill) {
            if (empty($skill->uuid)) {
                $skill->uuid = (string) Str::uuid();
            }
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    public function getRatingScoreAttribute(): int
    {
        return $this->upvotes - $this->downvotes;
    }
}
