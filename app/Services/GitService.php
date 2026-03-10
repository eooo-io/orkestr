<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitService
{
    /**
     * Check if a directory is inside a git repository.
     */
    public function isGitRepo(string $projectPath): bool
    {
        return $this->run($projectPath, ['git', 'rev-parse', '--is-inside-work-tree']) === 'true';
    }

    /**
     * Get the current branch name.
     */
    public function currentBranch(string $projectPath): ?string
    {
        try {
            return $this->run($projectPath, ['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Stage and commit a single file if it has changes.
     */
    public function commit(string $projectPath, string $relativePath, string $message): bool
    {
        // Stage the file
        $this->run($projectPath, ['git', 'add', $relativePath]);

        // Check if there are staged changes for this file
        $diff = $this->run($projectPath, ['git', 'diff', '--cached', '--name-only']);

        if (empty(trim($diff))) {
            return false; // No changes to commit
        }

        $this->run($projectPath, ['git', 'commit', '-m', $message, '--', $relativePath]);

        return true;
    }

    /**
     * Get git log for a specific file.
     *
     * @return array<int, array{hash: string, short_hash: string, message: string, author: string, date: string, branch: string|null}>
     */
    public function log(string $projectPath, string $relativePath, int $limit = 20): array
    {
        $format = '%H||%h||%s||%an||%aI';
        $output = $this->run($projectPath, [
            'git', 'log',
            "--format={$format}",
            "-n", (string) $limit,
            '--follow',
            '--', $relativePath,
        ]);

        if (empty(trim($output))) {
            return [];
        }

        $branch = $this->currentBranch($projectPath);

        return collect(explode("\n", trim($output)))
            ->map(function (string $line) use ($branch) {
                $parts = explode('||', $line, 5);
                if (count($parts) < 5) {
                    return null;
                }

                return [
                    'hash' => $parts[0],
                    'short_hash' => $parts[1],
                    'message' => $parts[2],
                    'author' => $parts[3],
                    'date' => $parts[4],
                    'branch' => $branch,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the diff for a file between a commit and its parent (or working tree).
     */
    public function diff(string $projectPath, string $relativePath, ?string $ref = null, ?string $ref2 = null): string
    {
        $cmd = ['git', 'diff'];

        if ($ref && $ref2) {
            $cmd[] = $ref;
            $cmd[] = $ref2;
        } elseif ($ref) {
            $cmd[] = "{$ref}~1";
            $cmd[] = $ref;
        }

        $cmd[] = '--';
        $cmd[] = $relativePath;

        return $this->run($projectPath, $cmd);
    }

    /**
     * Get file content at a specific commit.
     */
    public function showAtRef(string $projectPath, string $relativePath, string $ref): ?string
    {
        try {
            return $this->run($projectPath, ['git', 'show', "{$ref}:{$relativePath}"]);
        } catch (\RuntimeException) {
            return null;
        }
    }

    protected function run(string $cwd, array $command): string
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                "Git command failed: " . $process->getErrorOutput()
            );
        }

        return trim($process->getOutput());
    }
}
