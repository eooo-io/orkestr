<?php

namespace App\Services\Memory;

use App\Models\AgentMemory;

class DocumentIndexer
{
    /**
     * Split text content into overlapping chunks for RAG indexing.
     *
     * @param  string  $content  The full text content to chunk
     * @param  int  $chunkSize  Approximate number of characters per chunk
     * @param  int  $overlap  Number of characters to overlap between chunks
     * @return array<int, string> Array of text chunks
     */
    public function chunk(string $content, int $chunkSize = 512, int $overlap = 50): array
    {
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if (strlen($content) <= $chunkSize) {
            return [$content];
        }

        $chunks = [];
        $position = 0;
        $length = strlen($content);

        while ($position < $length) {
            $chunk = substr($content, $position, $chunkSize);
            $chunks[] = $chunk;

            $nextPosition = $position + $chunkSize - $overlap;

            // Prevent infinite loop if overlap >= chunkSize
            if ($nextPosition <= $position) {
                $nextPosition = $position + 1;
            }

            $position = $nextPosition;

            // If remaining content is smaller than overlap, include it in the last chunk
            if ($position < $length && ($length - $position) < $overlap) {
                $chunks[] = substr($content, $position);

                break;
            }
        }

        return $chunks;
    }

    /**
     * Index a document by splitting it into chunks and storing them as agent memories.
     *
     * @param  int  $agentId  The agent that owns the document
     * @param  int  $projectId  The project scope
     * @param  string  $path  Relative path of the document
     * @param  string  $content  Full text content
     * @param  int  $chunkSize  Characters per chunk
     * @param  int  $overlap  Overlap between chunks
     * @return int Number of chunks created
     */
    public function index(
        int $agentId,
        int $projectId,
        string $path,
        string $content,
        int $chunkSize = 512,
        int $overlap = 50,
    ): int {
        // Remove existing index for this document
        $this->removeIndex($agentId, $projectId, $path);

        $chunks = $this->chunk($content, $chunkSize, $overlap);

        foreach ($chunks as $index => $chunkContent) {
            AgentMemory::create([
                'agent_id' => $agentId,
                'project_id' => $projectId,
                'type' => 'long_term',
                'key' => "doc:{$path}:chunk:{$index}",
                'content' => ['text' => $chunkContent],
                'metadata' => [
                    'type' => 'document_chunk',
                    'path' => $path,
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                ],
            ]);
        }

        return count($chunks);
    }

    /**
     * Remove all indexed chunks for a given document path.
     *
     * @param  int  $agentId  The agent that owns the document
     * @param  int  $projectId  The project scope
     * @param  string  $path  Relative path of the document
     * @return int Number of chunks removed
     */
    public function removeIndex(int $agentId, int $projectId, string $path): int
    {
        return AgentMemory::where('agent_id', $agentId)
            ->where('project_id', $projectId)
            ->where('key', 'like', "doc:{$path}:chunk:%")
            ->delete();
    }
}
