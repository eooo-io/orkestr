<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillEvalRun;
use App\Models\SkillPropagation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cross-project skill propagation. When a skill does well in one project
 * (high usage + positive review ratio + clean regression history),
 * Orkestr suggests it for compatible agents in other projects in the
 * same organization.
 *
 * Compatibility: same role OR overlapping tags OR shared tuned_for_model
 * family. Don't suggest claude-tuned skills to a gpt-only project.
 */
class SkillPropagationService
{
    public const MIN_SCORE = 0.4;
    public const MAX_PER_PROJECT = 5;

    /**
     * Scan all high-performing skills and propose propagations for every
     * target project in the same org that doesn't already have them.
     * Returns the count of new suggestions created.
     */
    public function suggestPropagations(): int
    {
        $sources = $this->highPerformingSkills();
        $created = 0;

        foreach ($sources as $sourceSkill) {
            $sourceProject = $sourceSkill->project;
            if (! $sourceProject?->organization_id) continue;

            $siblingProjects = Project::where('organization_id', $sourceProject->organization_id)
                ->where('id', '!=', $sourceProject->id)
                ->get();

            foreach ($siblingProjects as $targetProject) {
                if ($this->alreadyHas($targetProject, $sourceSkill)) continue;

                $match = $this->bestAgentMatch($targetProject, $sourceSkill);

                // If the source is tuned for a specific model, require a compatible target agent
                if ($sourceSkill->tuned_for_model && ! $match['agent']) continue;

                $score = $this->score($sourceSkill, $match['agent']);
                if ($score < self::MIN_SCORE) continue;

                $exists = SkillPropagation::where('source_skill_id', $sourceSkill->id)
                    ->where('target_project_id', $targetProject->id)
                    ->first();

                if ($exists) continue;

                SkillPropagation::create([
                    'source_skill_id' => $sourceSkill->id,
                    'target_project_id' => $targetProject->id,
                    'target_agent_id' => $match['agent']?->id,
                    'status' => SkillPropagation::STATUS_SUGGESTED,
                    'suggestion_score' => $score,
                    'suggested_at' => now(),
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Accept a suggestion by cloning the source skill into the target project.
     * Returns the new Skill row.
     */
    public function accept(SkillPropagation $propagation, ?string $bodyOverride = null): Skill
    {
        $source = $propagation->sourceSkill;
        $target = $propagation->targetProject;

        $slug = $this->uniqueSlugFor($target, $source->slug);

        $newSkill = Skill::create([
            'project_id' => $target->id,
            'slug' => $slug,
            'name' => $source->name,
            'description' => $source->description,
            'summary' => $source->summary,
            'model' => $source->model,
            'tuned_for_model' => null, // target project needs to tune for its own default
            'max_tokens' => $source->max_tokens,
            'tools' => $source->tools,
            'includes' => $source->includes,
            'body' => $bodyOverride ?? $source->body,
            'template_variables' => $source->template_variables,
            'skill_type' => $source->skill_type,
        ]);

        $propagation->update([
            'status' => $bodyOverride !== null
                ? SkillPropagation::STATUS_MODIFIED
                : SkillPropagation::STATUS_ACCEPTED,
            'modified_skill_id' => $newSkill->id,
            'resolved_at' => now(),
        ]);

        return $newSkill;
    }

    public function dismiss(SkillPropagation $propagation): void
    {
        $propagation->update([
            'status' => SkillPropagation::STATUS_DISMISSED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Skill>
     */
    protected function highPerformingSkills(): \Illuminate\Support\Collection
    {
        $query = Skill::query()->with('project');

        // Require at least one completed eval run for the skill, representing real usage.
        $skillIdsWithRuns = DB::table('skill_eval_suites')
            ->join('skill_eval_runs', 'skill_eval_runs.eval_suite_id', '=', 'skill_eval_suites.id')
            ->where('skill_eval_runs.status', 'completed')
            ->pluck('skill_eval_suites.skill_id')
            ->unique()
            ->all();

        if (empty($skillIdsWithRuns)) return collect();

        return $query->whereIn('id', $skillIdsWithRuns)->get();
    }

    protected function alreadyHas(Project $target, Skill $source): bool
    {
        // Slug collisions on the target are treated as "already has it" — don't propose.
        return $target->skills()
            ->where('slug', $source->slug)
            ->exists();
    }

    protected function bestAgentMatch(Project $target, Skill $source): array
    {
        $sourceTagIds = DB::table('skill_tag')
            ->where('skill_id', $source->id)
            ->pluck('tag_id');

        $candidates = DB::table('project_agent')
            ->join('agents', 'agents.id', '=', 'project_agent.agent_id')
            ->where('project_agent.project_id', $target->id)
            ->where('project_agent.is_enabled', true)
            ->select('agents.*')
            ->get();

        $best = null;
        foreach ($candidates as $row) {
            $agent = Agent::find($row->id);
            if (! $agent) continue;

            if ($source->tuned_for_model && $agent->model
                && $this->modelFamily($source->tuned_for_model) !== $this->modelFamily($agent->model)
            ) {
                continue;
            }

            $best = $agent;
            break;
        }

        return ['agent' => $best, 'source_tag_ids' => $sourceTagIds];
    }

    protected function score(Skill $source, ?Agent $agent): float
    {
        $eval = DB::table('skill_eval_suites')
            ->join('skill_eval_runs', 'skill_eval_runs.eval_suite_id', '=', 'skill_eval_suites.id')
            ->where('skill_eval_suites.skill_id', $source->id)
            ->where('skill_eval_runs.status', 'completed')
            ->avg('skill_eval_runs.overall_score') ?? 0;

        $evalComponent = min(1.0, ((float) $eval) / 100.0);

        $agentComponent = $agent ? 0.3 : 0.0;

        return round($evalComponent * 0.7 + $agentComponent, 2);
    }

    protected function modelFamily(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gpt-'), str_starts_with($model, 'o3') => 'openai',
            str_starts_with($model, 'gemini-') => 'gemini',
            str_starts_with($model, 'grok-') => 'grok',
            default => 'other',
        };
    }

    protected function uniqueSlugFor(Project $project, string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;
        while ($project->skills()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
            if ($counter > 20) {
                $slug = "{$baseSlug}-" . Str::random(4);
                break;
            }
        }

        return $slug;
    }
}
