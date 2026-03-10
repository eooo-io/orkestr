<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LibrarySkill extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'category',
        'tags',
        'frontmatter',
        'body',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'frontmatter' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LibrarySkill $skill) {
            if (empty($skill->uuid)) {
                $skill->uuid = (string) Str::uuid();
            }
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }
}
