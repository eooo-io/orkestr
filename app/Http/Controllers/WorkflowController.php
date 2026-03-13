<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkflowResource;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\WorkflowExportService;
use App\Services\WorkflowValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowValidationService $validator,
        private WorkflowExportService $exporter,
    ) {}

    public function index(Project $project)
    {
        $workflows = $project->workflows()
            ->withCount(['steps', 'edges'])
            ->orderBy('name')
            ->get();

        return WorkflowResource::collection($workflows)->response();
    }

    public function show(Project $project, Workflow $workflow)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $workflow->load(['steps.agent', 'edges', 'entryStep']);

        return (new WorkflowResource($workflow))->response();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'nullable|string|in:manual,webhook,schedule,event',
            'trigger_config' => 'nullable|array',
            'status' => 'nullable|string|in:draft,active,archived',
            'context_schema' => 'nullable|array',
            'termination_policy' => 'nullable|array',
            'config' => 'nullable|array',
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()?->id;

        $workflow = Workflow::create($validated);
        $workflow->refresh();
        $workflow->loadCount(['steps', 'edges']);

        return (new WorkflowResource($workflow))->response()->setStatusCode(201);
    }

    public function update(Request $request, Project $project, Workflow $workflow)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'nullable|string|in:manual,webhook,schedule,event',
            'trigger_config' => 'nullable|array',
            'entry_step_id' => 'nullable|integer|exists:workflow_steps,id',
            'status' => 'nullable|string|in:draft,active,archived',
            'context_schema' => 'nullable|array',
            'termination_policy' => 'nullable|array',
            'config' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $workflow->update($validated);
        $workflow->load(['steps.agent', 'edges', 'entryStep']);

        return (new WorkflowResource($workflow))->response();
    }

    public function destroy(Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $workflow->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Project $project, Workflow $workflow)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $workflow->load(['steps', 'edges']);

        $newWorkflow = $workflow->replicate(['uuid', 'slug']);
        $newWorkflow->name = $workflow->name . ' (copy)';
        $newWorkflow->slug = Str::slug($newWorkflow->name);
        $newWorkflow->status = 'draft';
        $newWorkflow->entry_step_id = null;
        $newWorkflow->save();

        // Duplicate steps and build old→new ID map
        $stepIdMap = [];
        foreach ($workflow->steps as $step) {
            $newStep = $step->replicate(['uuid']);
            $newStep->workflow_id = $newWorkflow->id;
            $newStep->save();
            $stepIdMap[$step->id] = $newStep->id;
        }

        // Duplicate edges with remapped step IDs
        foreach ($workflow->edges as $edge) {
            WorkflowEdge::create([
                'workflow_id' => $newWorkflow->id,
                'source_step_id' => $stepIdMap[$edge->source_step_id] ?? $edge->source_step_id,
                'target_step_id' => $stepIdMap[$edge->target_step_id] ?? $edge->target_step_id,
                'condition_expression' => $edge->condition_expression,
                'label' => $edge->label,
                'priority' => $edge->priority,
            ]);
        }

        // Remap entry_step_id
        if ($workflow->entry_step_id && isset($stepIdMap[$workflow->entry_step_id])) {
            $newWorkflow->update(['entry_step_id' => $stepIdMap[$workflow->entry_step_id]]);
        }

        $newWorkflow->load(['steps.agent', 'edges']);

        return (new WorkflowResource($newWorkflow))->response()->setStatusCode(201);
    }

    // --- Step & Edge Management ---

    public function updateSteps(Request $request, Project $project, Workflow $workflow)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $validated = $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.uuid' => 'nullable|string',
            'steps.*.agent_id' => 'nullable|integer|exists:agents,id',
            'steps.*.type' => 'required|string|in:' . implode(',', WorkflowStep::TYPES),
            'steps.*.name' => 'required|string|max:255',
            'steps.*.position_x' => 'required|numeric',
            'steps.*.position_y' => 'required|numeric',
            'steps.*.config' => 'nullable|array',
            'steps.*.sort_order' => 'nullable|integer',
        ]);

        $existingIds = $workflow->steps()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($validated['steps'], 'id'));

        // Delete steps not in the incoming payload
        $toDelete = array_diff($existingIds, $incomingIds);
        if (! empty($toDelete)) {
            // Clean up orphaned edges first
            $workflow->edges()
                ->where(function ($q) use ($toDelete) {
                    $q->whereIn('source_step_id', $toDelete)
                        ->orWhereIn('target_step_id', $toDelete);
                })
                ->delete();
            $workflow->steps()->whereIn('id', $toDelete)->delete();
        }

        // Upsert steps
        foreach ($validated['steps'] as $index => $stepData) {
            $stepData['workflow_id'] = $workflow->id;
            $stepData['sort_order'] = $stepData['sort_order'] ?? $index;

            if (! empty($stepData['id']) && in_array($stepData['id'], $existingIds)) {
                $step = WorkflowStep::find($stepData['id']);
                $step->update($stepData);
            } else {
                unset($stepData['id']);
                WorkflowStep::create($stepData);
            }
        }

        $workflow->load(['steps.agent', 'edges']);

        return (new WorkflowResource($workflow))->response();
    }

    public function updateEdges(Request $request, Project $project, Workflow $workflow)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $validated = $request->validate([
            'edges' => 'required|array',
            'edges.*.id' => 'nullable|integer',
            'edges.*.source_step_id' => 'required|integer|exists:workflow_steps,id',
            'edges.*.target_step_id' => 'required|integer|exists:workflow_steps,id',
            'edges.*.condition_expression' => 'nullable|string',
            'edges.*.label' => 'nullable|string|max:255',
            'edges.*.priority' => 'nullable|integer',
        ]);

        // Replace all edges
        $workflow->edges()->delete();

        foreach ($validated['edges'] as $edgeData) {
            $edgeData['workflow_id'] = $workflow->id;
            unset($edgeData['id']);
            WorkflowEdge::create($edgeData);
        }

        $workflow->load(['steps.agent', 'edges']);

        return (new WorkflowResource($workflow))->response();
    }

    // --- Validation ---

    public function validate(Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $result = $this->validator->validate($workflow);

        return response()->json($result);
    }

    // --- Version Management ---

    public function versions(Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $versions = $workflow->versions()
            ->select(['id', 'workflow_id', 'version_number', 'note', 'created_at'])
            ->get();

        return response()->json($versions);
    }

    public function createVersion(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $workflow->load(['steps', 'edges']);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => $workflow->nextVersionNumber(),
            'snapshot' => $workflow->snapshot(),
            'note' => $request->input('note'),
        ]);

        return response()->json($version, 201);
    }

    public function restoreVersion(Project $project, Workflow $workflow, int $versionNumber)
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $version = $workflow->versions()->where('version_number', $versionNumber)->firstOrFail();
        $snapshot = $version->snapshot;

        // Update workflow metadata
        if (isset($snapshot['workflow'])) {
            $workflow->update($snapshot['workflow']);
        }

        // Replace steps
        $workflow->edges()->delete();
        $workflow->steps()->delete();

        if (isset($snapshot['steps'])) {
            foreach ($snapshot['steps'] as $stepData) {
                unset($stepData['id']);
                $stepData['workflow_id'] = $workflow->id;
                WorkflowStep::create($stepData);
            }
        }

        // Replace edges — step IDs in snapshot reference old IDs
        // For now, edges that reference non-existent steps are skipped
        if (isset($snapshot['edges'])) {
            foreach ($snapshot['edges'] as $edgeData) {
                $edgeData['workflow_id'] = $workflow->id;
                if (
                    WorkflowStep::where('id', $edgeData['source_step_id'])->exists()
                    && WorkflowStep::where('id', $edgeData['target_step_id'])->exists()
                ) {
                    WorkflowEdge::create($edgeData);
                }
            }
        }

        $workflow->load(['steps.agent', 'edges']);

        return (new WorkflowResource($workflow))->response();
    }

    // --- Export ---

    public function export(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflowBelongsToProject($workflow, $project);

        $format = $request->query('format', 'json');

        return match ($format) {
            'langgraph' => response()->json([
                'format' => 'langgraph',
                'content' => $this->exporter->exportLangGraph($workflow),
            ]),
            'crewai' => response()->json([
                'format' => 'crewai',
                'content' => $this->exporter->exportCrewAI($workflow),
            ]),
            default => response()->json($this->exporter->exportJson($workflow)),
        };
    }

    // --- Helpers ---

    private function authorizeWorkflowBelongsToProject(Workflow $workflow, Project $project): void
    {
        if ($workflow->project_id !== $project->id) {
            abort(404, 'Workflow not found in this project.');
        }
    }
}
