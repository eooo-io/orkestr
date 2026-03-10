<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Project $project,
    ) {}
}
