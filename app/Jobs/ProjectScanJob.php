<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\ManifestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProjectScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Project $project,
    ) {}

    public function handle(ManifestService $manifestService): void
    {
        $result = $manifestService->scanProject($this->project->resolved_path);

        foreach ($result['skills'] as $parsedSkill) {
            $frontmatter = $parsedSkill['frontmatter'];
            $body = $parsedSkill['body'];
            $slug = $frontmatter['id'] ?? $parsedSkill['filename'];

            $skill = Skill::updateOrCreate(
                [
                    'project_id' => $this->project->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $frontmatter['name'] ?? Str::headline($slug),
                    'description' => $frontmatter['description'] ?? null,
                    'model' => $frontmatter['model'] ?? null,
                    'max_tokens' => $frontmatter['max_tokens'] ?? null,
                    'tools' => $frontmatter['tools'] ?? null,
                    'includes' => $frontmatter['includes'] ?? null,
                    'template_variables' => $frontmatter['template_variables'] ?? null,
                    'body' => $body,
                ],
            );

            // Sync tags if present in frontmatter
            if (! empty($frontmatter['tags']) && is_array($frontmatter['tags'])) {
                $tagIds = collect($frontmatter['tags'])->map(function (string $tagName) {
                    return Tag::firstOrCreate(['name' => trim($tagName)])->id;
                });

                $skill->tags()->sync($tagIds);
            }

            // Create v1 snapshot if skill was just created (no versions yet)
            if ($skill->versions()->count() === 0) {
                $skill->versions()->create([
                    'version_number' => 1,
                    'frontmatter' => $frontmatter,
                    'body' => $body,
                    'note' => 'Initial scan',
                    'saved_at' => now(),
                ]);
            }
        }

        $this->project->update(['synced_at' => now()]);
    }
}
