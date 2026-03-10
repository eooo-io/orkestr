<?php

namespace App\Events;

use App\Models\Skill;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SkillUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Skill $skill,
        public string $action = 'updated',
    ) {}
}
