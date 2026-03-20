<?php

namespace App\Services\Providers;

use App\Models\Skill;
use App\Services\AgentisManifestService;
use App\Services\SkillFileParser;
use Illuminate\Support\Facades\File;

trait ResolvesSkillAssets
{
    /**
     * Max file size (in bytes) to inline in sync output.
     * Files larger than this get referenced instead.
     */
    protected int $maxInlineSize = 10240; // 10KB

    /**
     * File extensions that are safe to inline as text.
     */
    protected array $textExtensions = [
        'md', 'txt', 'json', 'yaml', 'yml', 'csv', 'xml', 'html',
        'sh', 'bash', 'py', 'js', 'ts', 'php', 'rb', 'go', 'rs',
        'sql', 'toml', 'ini', 'cfg', 'conf', 'env', 'log',
    ];

    /**
     * Build an asset context section for a skill's sync output.
     *
     * Returns markdown with inlined text assets and references for binary/large files.
     * Returns empty string if no assets exist.
     */
    protected function buildAssetContext(Skill $skill): string
    {
        $manifestService = app(AgentisManifestService::class);
        $project = $skill->project;

        if (! $project) {
            return '';
        }

        $folderPath = $manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);

        if (! $folderPath) {
            return '';
        }

        $parser = app(SkillFileParser::class);
        $assets = $parser->inventoryAssets($folderPath);

        if (empty($assets)) {
            return '';
        }

        $sections = ["### Skill Assets\n"];

        foreach ($assets as $asset) {
            $fullPath = $folderPath . '/' . $asset['path'];
            $ext = strtolower($asset['type']);
            $isText = in_array($ext, $this->textExtensions);
            $isSmall = $asset['size'] <= $this->maxInlineSize;

            if ($isText && $isSmall && File::exists($fullPath)) {
                $content = File::get($fullPath);
                $sections[] = "#### `{$asset['path']}`\n\n```{$ext}\n{$content}\n```\n";
            } else {
                $sizeLabel = $this->formatSize($asset['size']);
                $sections[] = "- `{$asset['path']}` ({$sizeLabel}, {$ext})\n";
            }
        }

        return implode("\n", $sections);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
