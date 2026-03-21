<?php

namespace App\Http\Controllers;

use App\Http\Resources\VersionResource;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Services\ManifestService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VersionController extends Controller
{
    public function __construct(
        protected ManifestService $manifestService,
    ) {}

    public function index(Skill $skill): AnonymousResourceCollection
    {
        $versions = $skill->versions()->orderByDesc('version_number')->get();

        return VersionResource::collection($versions);
    }

    public function show(Skill $skill, int $versionNumber): VersionResource
    {
        $version = $skill->versions()->where('version_number', $versionNumber)->firstOrFail();

        return new VersionResource($version);
    }

    public function restore(Skill $skill, int $versionNumber): VersionResource
    {
        $version = $skill->versions()->where('version_number', $versionNumber)->firstOrFail();

        // Restore skill data from the version snapshot
        $frontmatter = $version->frontmatter ?? [];

        $skill->update([
            'name' => $frontmatter['name'] ?? $skill->name,
            'description' => $frontmatter['description'] ?? $skill->description,
            'model' => $frontmatter['model'] ?? $skill->model,
            'max_tokens' => $frontmatter['max_tokens'] ?? $skill->max_tokens,
            'tools' => $frontmatter['tools'] ?? $skill->tools,
            'body' => $version->body,
        ]);

        // Create a new version snapshot for the restore
        $nextVersion = ($skill->versions()->max('version_number') ?? 0) + 1;

        $newVersion = $skill->versions()->create([
            'version_number' => $nextVersion,
            'frontmatter' => $version->frontmatter,
            'body' => $version->body,
            'note' => "Restored from v{$versionNumber}",
            'saved_at' => now(),
        ]);

        // Write the restored content to disk
        $this->manifestService->writeSkillFile(
            $skill->project->resolved_path,
            array_merge($frontmatter, ['id' => $skill->slug]),
            $version->body ?? '',
        );

        return new VersionResource($newVersion);
    }
}
