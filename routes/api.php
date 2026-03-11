<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\SkillsShController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BulkSkillController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\SkillGenerateController;
use App\Http\Controllers\SkillTestController;
use App\Http\Controllers\SkillVariableController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\InboundWebhookController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\OpenClawConfigController;
use App\Http\Controllers\McpServerController;
use App\Http\Controllers\A2aAgentController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Projects
Route::get('/projects', [ProjectController::class, 'index']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::get('/projects/{project}', [ProjectController::class, 'show']);
Route::put('/projects/{project}', [ProjectController::class, 'update']);
Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
Route::post('/projects/{project}/scan', [ProjectController::class, 'scan']);
Route::post('/projects/{project}/sync', [ProjectController::class, 'sync']);
Route::post('/projects/{project}/sync/preview', [ProjectController::class, 'syncPreview']);
Route::get('/projects/{project}/git-log', [ProjectController::class, 'gitLog']);
Route::get('/projects/{project}/git-diff', [ProjectController::class, 'gitDiff']);

// Skills (nested under project for create/index)
Route::get('/projects/{project}/skills', [SkillController::class, 'index']);
Route::post('/projects/{project}/skills', [SkillController::class, 'store']);

// Bulk Skill Operations (must be before /skills/{skill} routes)
Route::post('/skills/bulk-tag', [BulkSkillController::class, 'bulkTag']);
Route::post('/skills/bulk-assign', [BulkSkillController::class, 'bulkAssign']);
Route::post('/skills/bulk-delete', [BulkSkillController::class, 'bulkDelete']);
Route::post('/skills/bulk-move', [BulkSkillController::class, 'bulkMove']);

// Skills (standalone for show/update/delete/duplicate)
Route::get('/skills/{skill}', [SkillController::class, 'show']);
Route::put('/skills/{skill}', [SkillController::class, 'update']);
Route::delete('/skills/{skill}', [SkillController::class, 'destroy']);
Route::post('/skills/{skill}/duplicate', [SkillController::class, 'duplicate']);
Route::get('/skills/{skill}/lint', [SkillController::class, 'lint']);

// Skill Template Variables
Route::get('/projects/{project}/skills/{skill}/variables', [SkillVariableController::class, 'index']);
Route::put('/projects/{project}/skills/{skill}/variables', [SkillVariableController::class, 'update']);

// Live Test Runner (SSE)
Route::post('/skills/{skill}/test', SkillTestController::class);
Route::post('/playground', [SkillTestController::class, 'playground']);

// AI Skill Generation
Route::post('/skills/generate', SkillGenerateController::class);

// Versions
Route::get('/skills/{skill}/versions', [VersionController::class, 'index']);
Route::get('/skills/{skill}/versions/{versionNumber}', [VersionController::class, 'show']);
Route::post('/skills/{skill}/versions/{versionNumber}/restore', [VersionController::class, 'restore']);

// Tags
Route::get('/tags', [TagController::class, 'index']);
Route::post('/tags', [TagController::class, 'store']);
Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

// Search
Route::get('/search', SearchController::class);

// Library
Route::get('/library', [LibraryController::class, 'index']);
Route::post('/library/{librarySkill}/import', [LibraryController::class, 'import']);

// Skills.sh
Route::post('/skills-sh/discover', [SkillsShController::class, 'discover']);
Route::post('/skills-sh/preview', [SkillsShController::class, 'preview']);
Route::post('/skills-sh/import', [SkillsShController::class, 'import']);

// Agents
Route::get('/agents', [AgentController::class, 'index']);
Route::get('/projects/{project}/agents', [AgentController::class, 'projectAgents']);
Route::put('/projects/{project}/agents/{agent}/toggle', [AgentController::class, 'toggle']);
Route::put('/projects/{project}/agents/{agent}/instructions', [AgentController::class, 'updateInstructions']);
Route::put('/projects/{project}/agents/{agent}/skills', [AgentController::class, 'assignSkills']);
Route::get('/projects/{project}/agents/{agent}/compose', [AgentController::class, 'compose']);
Route::get('/projects/{project}/agents/compose', [AgentController::class, 'composeAll']);

// Bundles (Export/Import)
Route::post('/projects/{project}/export', [BundleController::class, 'export']);
Route::post('/projects/{project}/import-bundle', [BundleController::class, 'import']);

// Marketplace
Route::get('/marketplace', [MarketplaceController::class, 'index']);
Route::get('/marketplace/{marketplaceSkill}', [MarketplaceController::class, 'show']);
Route::post('/marketplace/publish', [MarketplaceController::class, 'publish']);
Route::post('/marketplace/{marketplaceSkill}/install', [MarketplaceController::class, 'install']);
Route::post('/marketplace/{marketplaceSkill}/vote', [MarketplaceController::class, 'vote']);

// Webhooks
Route::get('/projects/{project}/webhooks', [WebhookController::class, 'index']);
Route::post('/projects/{project}/webhooks', [WebhookController::class, 'store']);
Route::put('/webhooks/{webhook}', [WebhookController::class, 'update']);
Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy']);
Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);
Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test']);
Route::post('/webhooks/github/{project}', [InboundWebhookController::class, 'github']);

// Repositories
Route::get('/projects/{project}/repositories', [RepositoryController::class, 'show']);
Route::post('/projects/{project}/repositories', [RepositoryController::class, 'connect']);
Route::put('/projects/{project}/repositories/{provider}', [RepositoryController::class, 'update']);
Route::delete('/projects/{project}/repositories/{provider}', [RepositoryController::class, 'disconnect']);
Route::get('/projects/{project}/repositories/{provider}/status', [RepositoryController::class, 'status']);
Route::get('/projects/{project}/repositories/{provider}/branches', [RepositoryController::class, 'branches']);
Route::get('/projects/{project}/repositories/{provider}/latest-commit', [RepositoryController::class, 'latestCommit']);
Route::get('/projects/{project}/repositories/{provider}/files', [RepositoryController::class, 'files']);
Route::post('/projects/{project}/repositories/{provider}/pull', [RepositoryController::class, 'pullSkills']);
Route::post('/projects/{project}/repositories/{provider}/push', [RepositoryController::class, 'pushSkills']);
Route::get('/repositories/allowed-paths', [RepositoryController::class, 'allowedPaths']);

// OpenClaw Config
Route::get('/projects/{project}/openclaw', [OpenClawConfigController::class, 'show']);
Route::put('/projects/{project}/openclaw', [OpenClawConfigController::class, 'update']);

// MCP Servers (shared across providers)
Route::get('/projects/{project}/mcp-servers', [McpServerController::class, 'index']);
Route::post('/projects/{project}/mcp-servers', [McpServerController::class, 'store']);
Route::put('/mcp-servers/{mcpServer}', [McpServerController::class, 'update']);
Route::delete('/mcp-servers/{mcpServer}', [McpServerController::class, 'destroy']);

// A2A Agents (shared across providers)
Route::get('/projects/{project}/a2a-agents', [A2aAgentController::class, 'index']);
Route::post('/projects/{project}/a2a-agents', [A2aAgentController::class, 'store']);
Route::put('/a2a-agents/{a2aAgent}', [A2aAgentController::class, 'update']);
Route::delete('/a2a-agents/{a2aAgent}', [A2aAgentController::class, 'destroy']);

// Import (Reverse-Sync)
Route::post('/import/detect', [ImportController::class, 'detect']);
Route::post('/projects/{project}/import', [ImportController::class, 'import']);

// Models
Route::get('/models', [ModelController::class, 'index']);

// Settings
Route::get('/settings', SettingsController::class);
Route::put('/settings', [SettingsController::class, 'update']);
