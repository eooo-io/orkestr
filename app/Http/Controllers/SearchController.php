<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $query = Skill::with('tags', 'project');

        if ($q = $request->input('q')) {
            $connection = DB::getDriverName();

            if (in_array($connection, ['mysql', 'mariadb'])) {
                $query->whereRaw('MATCH(name, description, body) AGAINST(? IN BOOLEAN MODE)', [$q]);
            } else {
                $query->where(function ($qb) use ($q) {
                    $qb->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%");
                });
            }
        }

        if ($tags = $request->input('tags')) {
            $tagNames = is_array($tags) ? $tags : explode(',', $tags);
            $query->whereHas('tags', fn ($qb) => $qb->whereIn('name', $tagNames));
        }

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($model = $request->input('model')) {
            $query->where('model', $model);
        }

        $skills = $query->orderBy('name')->get();

        return SkillResource::collection($skills);
    }
}
