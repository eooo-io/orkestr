<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;

class SkillFileParser
{
    /**
     * Parse a skill file (YAML frontmatter + Markdown body) into its components.
     *
     * @return array{frontmatter: array, body: string}
     */
    public function parseFile(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);

        return $this->parseContent($content);
    }

    /**
     * Parse skill content string into frontmatter and body.
     *
     * @return array{frontmatter: array, body: string}
     */
    public function parseContent(string $content): array
    {
        $content = ltrim($content);

        if (! str_starts_with($content, '---')) {
            return [
                'frontmatter' => [],
                'body' => trim($content),
            ];
        }

        // Find the closing --- delimiter
        $endPos = strpos($content, "\n---", 3);

        if ($endPos === false) {
            return [
                'frontmatter' => [],
                'body' => trim($content),
            ];
        }

        $yamlBlock = substr($content, 4, $endPos - 4);
        $body = substr($content, $endPos + 4);

        $frontmatter = Yaml::parse(trim($yamlBlock)) ?? [];

        return [
            'frontmatter' => $frontmatter,
            'body' => trim($body),
        ];
    }

    /**
     * Render a skill file from frontmatter and body.
     */
    public function renderFile(array $frontmatter, string $body): string
    {
        $yaml = Yaml::dump($frontmatter, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return "---\n{$yaml}---\n\n{$body}\n";
    }

    /**
     * Validate frontmatter and return an array of errors (empty = valid).
     *
     * @return string[]
     */
    public function validateFrontmatter(array $data): array
    {
        $errors = [];

        if (empty($data['id'])) {
            $errors[] = 'Missing required field: id';
        }

        if (empty($data['name'])) {
            $errors[] = 'Missing required field: name';
        }

        return $errors;
    }
}
