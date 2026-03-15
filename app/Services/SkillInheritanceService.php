<?php

namespace App\Services;

use App\Models\Skill;

class SkillInheritanceService
{
    private const MAX_DEPTH = 5;

    /**
     * Resolve a skill's full configuration by walking up the inheritance chain.
     * Child overrides parent for frontmatter; bodies are concatenated.
     */
    public function resolve(Skill $skill): array
    {
        $chain = $this->getInheritanceChain($skill);

        // Start with the root ancestor (last in chain) and merge downward
        $mergedFrontmatter = [];
        $mergedBody = '';

        foreach (array_reverse($chain) as $ancestor) {
            // Merge frontmatter — child values override parent
            $frontmatter = [
                'name' => $ancestor->name,
                'description' => $ancestor->description,
                'model' => $ancestor->model,
                'max_tokens' => $ancestor->max_tokens,
                'tools' => $ancestor->tools,
                'includes' => $ancestor->includes,
                'tags' => $ancestor->tags->pluck('name')->toArray(),
            ];

            foreach ($frontmatter as $key => $value) {
                if ($value !== null) {
                    $mergedFrontmatter[$key] = $value;
                }
            }

            // Handle body: check override_sections
            if ($ancestor->override_sections) {
                foreach ($ancestor->override_sections as $section => $content) {
                    $mergedBody = $this->replaceSection($mergedBody, $section, $content);
                }
            }

            if ($ancestor->body) {
                if ($ancestor->id === $skill->id) {
                    // The child's body appends/replaces
                    $mergedBody = $mergedBody
                        ? $mergedBody . "\n\n<!-- inherited from: {$ancestor->name} -->\n\n" . $ancestor->body
                        : $ancestor->body;
                } else {
                    // Ancestor body is the base
                    $mergedBody = $ancestor->body . ($mergedBody ? "\n\n" . $mergedBody : '');
                }
            }
        }

        return [
            'frontmatter' => $mergedFrontmatter,
            'body' => trim($mergedBody),
            'inheritance_chain' => collect($chain)->map(fn (Skill $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
            ])->toArray(),
        ];
    }

    /**
     * Get the full inheritance chain from child to root ancestor.
     */
    public function getInheritanceChain(Skill $skill): array
    {
        $chain = [$skill];
        $current = $skill;
        $visited = [$skill->id];
        $depth = 0;

        while ($current->extends_skill_id && $depth < self::MAX_DEPTH) {
            $parent = Skill::find($current->extends_skill_id);

            if (! $parent || in_array($parent->id, $visited)) {
                break; // Circular dependency or missing parent
            }

            $chain[] = $parent;
            $visited[] = $parent->id;
            $current = $parent;
            $depth++;
        }

        return $chain;
    }

    /**
     * Get all children that extend a given skill.
     */
    public function getChildren(Skill $skill): array
    {
        return $skill->childSkills()->get()->map(fn (Skill $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
        ])->toArray();
    }

    /**
     * Replace a named section in the body text.
     */
    private function replaceSection(string $body, string $section, string $content): string
    {
        $pattern = '/<!-- section: ' . preg_quote($section, '/') . ' -->.*?<!-- \/section: ' . preg_quote($section, '/') . ' -->/s';

        if (preg_match($pattern, $body)) {
            return preg_replace(
                $pattern,
                "<!-- section: {$section} -->\n{$content}\n<!-- /section: {$section} -->",
                $body
            );
        }

        // Section not found — append
        return $body . "\n\n<!-- section: {$section} -->\n{$content}\n<!-- /section: {$section} -->";
    }
}
