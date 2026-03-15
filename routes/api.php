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
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\VisualizationController;
use App\Http\Controllers\CustomEndpointController;
use App\Http\Controllers\ModelHealthController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\OpenApiController;
use App\Http\Controllers\SdkController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\ExecutionController;
use App\Http\Controllers\WorkflowRunController;
use App\Http\Controllers\AgentMemoryController;
use App\Http\Controllers\ProviderHealthController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PerformanceDashboardController;
use App\Http\Controllers\AgentBudgetController;
use App\Http\Controllers\AgentToolScopeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContentPolicyController;
use App\Http\Controllers\ActivityFeedController;
use App\Http\Controllers\SsoProviderController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ContentReviewController;
use App\Http\Controllers\EndpointApprovalController;
use App\Http\Controllers\GuardrailController;
use App\Http\Controllers\GuardrailReportController;
use App\Http\Controllers\SecurityScanController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\SkillReviewController;
use App\Http\Controllers\SkillOwnershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SkillAnalyticsController;
use App\Http\Controllers\SkillRegressionController;
use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\SkillInheritanceController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\GitHubImportController;
use Illuminate\Support\Facades\Route;

// ─── Public Routes (no auth required) ────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/github/{project}', [InboundWebhookController::class, 'github']);
Route::get('/billing/plans', [BillingController::class, 'plans']);
Route::post('/webhooks/schedule/{token}', [ScheduleController::class, 'webhookTrigger']);

// OpenAPI spec & docs (public)
Route::get('/openapi.json', [OpenApiController::class, 'spec']);
Route::get('/docs', [OpenApiController::class, 'docs']);

// ─── Authenticated Routes ────────────────────────────────────
Route::middleware('auth:web')->group(function () {
    // Organizations
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch']);
    Route::get('/organizations/{organization}/members', [OrganizationController::class, 'members']);
    Route::post('/organizations/{organization}/members', [OrganizationController::class, 'inviteMember']);
    Route::put('/organizations/{organization}/members/{user}', [OrganizationController::class, 'updateMemberRole']);
    Route::delete('/organizations/{organization}/members/{user}', [OrganizationController::class, 'removeMember']);
    Route::get('/organizations/{organization}/invitations', [OrganizationController::class, 'invitations']);
    Route::post('/organizations/{organization}/invitations', [OrganizationController::class, 'inviteMember']);
    Route::delete('/invitations/{invitation}', [OrganizationController::class, 'cancelInvitation']);
    Route::post('/invitations/accept/{token}', [OrganizationController::class, 'acceptInvitation']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('org-role:editor');
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('org-role:editor');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('org-role:editor');
    Route::post('/projects/{project}/scan', [ProjectController::class, 'scan']);
    Route::post('/projects/{project}/sync', [ProjectController::class, 'sync']);
    Route::post('/projects/{project}/sync/preview', [ProjectController::class, 'syncPreview']);
    Route::get('/projects/{project}/git-log', [ProjectController::class, 'gitLog']);
    Route::get('/projects/{project}/git-diff', [ProjectController::class, 'gitDiff']);

    // Skills (nested under project for create/index)
    Route::get('/projects/{project}/skills', [SkillController::class, 'index']);
    Route::post('/projects/{project}/skills', [SkillController::class, 'store'])->middleware('org-role:editor');

    // Bulk Skill Operations (must be before /skills/{skill} routes)
    Route::post('/skills/bulk-tag', [BulkSkillController::class, 'bulkTag']);
    Route::post('/skills/bulk-assign', [BulkSkillController::class, 'bulkAssign']);
    Route::post('/skills/bulk-delete', [BulkSkillController::class, 'bulkDelete']);
    Route::post('/skills/bulk-move', [BulkSkillController::class, 'bulkMove']);

    // Skills (standalone for show/update/delete/duplicate)
    Route::get('/skills/{skill}', [SkillController::class, 'show']);
    Route::put('/skills/{skill}', [SkillController::class, 'update'])->middleware('org-role:editor');
    Route::delete('/skills/{skill}', [SkillController::class, 'destroy'])->middleware('org-role:editor');
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

    // Agents — global CRUD
    Route::get('/agents', [AgentController::class, 'index']);
    Route::get('/agents/overview', [PerformanceDashboardController::class, 'agentsOverview']);
    Route::post('/agents', [AgentController::class, 'store'])->middleware('org-role:editor');
    Route::get('/agents/{agent}', [AgentController::class, 'show']);
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->middleware('org-role:editor');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->middleware('org-role:editor');
    Route::post('/agents/{agent}/duplicate', [AgentController::class, 'duplicate']);
    Route::get('/agents/{agent}/export', [AgentController::class, 'export']);

    // Agents — project-scoped
    Route::get('/projects/{project}/agents', [AgentController::class, 'projectAgents']);
    Route::put('/projects/{project}/agents/{agent}/toggle', [AgentController::class, 'toggle']);
    Route::put('/projects/{project}/agents/{agent}/instructions', [AgentController::class, 'updateInstructions']);
    Route::put('/projects/{project}/agents/{agent}/skills', [AgentController::class, 'assignSkills']);
    Route::put('/projects/{project}/agents/{agent}/mcp-servers', [AgentController::class, 'bindMcpServers']);
    Route::put('/projects/{project}/agents/{agent}/a2a-agents', [AgentController::class, 'bindA2aAgents']);
    Route::get('/projects/{project}/agents/{agent}/compose', [AgentController::class, 'compose']);
    Route::get('/projects/{project}/agents/{agent}/compose-structured', [AgentController::class, 'composeStructured']);
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

    // Schedules
    Route::get('/projects/{project}/schedules', [ScheduleController::class, 'index']);
    Route::post('/projects/{project}/schedules', [ScheduleController::class, 'store'])->middleware('org-role:editor');
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('org-role:editor');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->middleware('org-role:editor');
    Route::post('/schedules/{schedule}/toggle', [ScheduleController::class, 'toggle'])->middleware('org-role:editor');
    Route::post('/schedules/{schedule}/trigger', [ScheduleController::class, 'trigger'])->middleware('org-role:editor');
    Route::get('/schedules/{schedule}/runs', [ScheduleController::class, 'runs']);

    // Webhooks
    Route::get('/projects/{project}/webhooks', [WebhookController::class, 'index']);
    Route::post('/projects/{project}/webhooks', [WebhookController::class, 'store'])->middleware('org-role:admin');
    Route::put('/webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('org-role:admin');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('org-role:admin');
    Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);
    Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test']);

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
    Route::get('/projects/{project}/mcp-servers/{mcpServer}/tools', [McpServerController::class, 'tools']);
    Route::post('/projects/{project}/mcp-servers/{mcpServer}/ping', [McpServerController::class, 'ping']);

    // A2A Agents (shared across providers)
    Route::get('/projects/{project}/a2a-agents', [A2aAgentController::class, 'index']);
    Route::post('/projects/{project}/a2a-agents', [A2aAgentController::class, 'store']);
    Route::put('/a2a-agents/{a2aAgent}', [A2aAgentController::class, 'update']);
    Route::delete('/a2a-agents/{a2aAgent}', [A2aAgentController::class, 'destroy']);

    // Workflows
    Route::get('/projects/{project}/workflows', [WorkflowController::class, 'index']);
    Route::post('/projects/{project}/workflows', [WorkflowController::class, 'store']);
    Route::get('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'show']);
    Route::put('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'update']);
    Route::delete('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'destroy']);
    Route::post('/projects/{project}/workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);
    Route::put('/projects/{project}/workflows/{workflow}/steps', [WorkflowController::class, 'updateSteps']);
    Route::put('/projects/{project}/workflows/{workflow}/edges', [WorkflowController::class, 'updateEdges']);
    Route::post('/projects/{project}/workflows/{workflow}/validate', [WorkflowController::class, 'validate']);
    Route::get('/projects/{project}/workflows/{workflow}/export', [WorkflowController::class, 'export']);
    Route::get('/projects/{project}/workflows/{workflow}/versions', [WorkflowController::class, 'versions']);
    Route::post('/projects/{project}/workflows/{workflow}/versions', [WorkflowController::class, 'createVersion']);
    Route::post('/projects/{project}/workflows/{workflow}/versions/{versionNumber}/restore', [WorkflowController::class, 'restoreVersion']);

    // Agent Memory
    Route::get('/projects/{project}/agents/{agent}/memories', [AgentMemoryController::class, 'index']);
    Route::post('/projects/{project}/agents/{agent}/memories', [AgentMemoryController::class, 'store']);
    Route::delete('/projects/{project}/agents/{agent}/memories', [AgentMemoryController::class, 'clear']);
    Route::delete('/memories/{agentMemory}', [AgentMemoryController::class, 'destroy']);
    Route::get('/projects/{project}/agents/{agent}/conversations', [AgentMemoryController::class, 'conversations']);

    // Agent Execution
    Route::post('/projects/{project}/agents/{agent}/execute', [ExecutionController::class, 'execute']);
    Route::get('/projects/{project}/runs/stats', [ExecutionController::class, 'stats']);
    Route::get('/projects/{project}/runs', [ExecutionController::class, 'index']);
    Route::get('/runs/{run}', [ExecutionController::class, 'show']);
    Route::post('/runs/{run}/cancel', [ExecutionController::class, 'cancel']);
    Route::post('/runs/{run}/steps/{step}/approve', [ExecutionController::class, 'approveStep']);
    Route::post('/runs/{run}/steps/{step}/reject', [ExecutionController::class, 'rejectStep']);
    Route::post('/runs/{run}/resume', [ExecutionController::class, 'resume']);

    // Agent Budget
    Route::get('/agents/{agent}/budget-status', [AgentBudgetController::class, 'status']);
    Route::put('/agents/{agent}/budget', [AgentBudgetController::class, 'update'])->middleware('org-role:editor');

    // Agent Tool Scope
    Route::get('/agents/{agent}/tool-scope', [AgentToolScopeController::class, 'show']);
    Route::put('/agents/{agent}/tool-scope', [AgentToolScopeController::class, 'update'])->middleware('org-role:editor');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
    Route::get('/agents/{agent}/audit-logs', [AuditLogController::class, 'agentLogs']);

    // Content Policies
    Route::get('/content-policies/rule-types', [ContentPolicyController::class, 'ruleTypes']);
    Route::get('/organizations/{organization}/content-policies', [ContentPolicyController::class, 'index']);
    Route::post('/organizations/{organization}/content-policies', [ContentPolicyController::class, 'store'])->middleware('org-role:admin');
    Route::get('/content-policies/{contentPolicy}', [ContentPolicyController::class, 'show']);
    Route::put('/content-policies/{contentPolicy}', [ContentPolicyController::class, 'update'])->middleware('org-role:admin');
    Route::delete('/content-policies/{contentPolicy}', [ContentPolicyController::class, 'destroy'])->middleware('org-role:admin');
    Route::post('/content-policies/{contentPolicy}/check/{skill}', [ContentPolicyController::class, 'checkSkill']);
    Route::post('/skills/{skill}/check-policies', [ContentPolicyController::class, 'checkSkillPolicies']);

    // Activity Feed
    Route::get('/organizations/{organization}/activity-feed', [ActivityFeedController::class, 'index']);

    // SSO Providers
    Route::get('/organizations/{organization}/sso-providers', [SsoProviderController::class, 'index'])->middleware('org-role:admin');
    Route::post('/organizations/{organization}/sso-providers', [SsoProviderController::class, 'store'])->middleware('org-role:owner');
    Route::get('/sso-providers/{ssoProvider}', [SsoProviderController::class, 'show'])->middleware('org-role:admin');
    Route::put('/sso-providers/{ssoProvider}', [SsoProviderController::class, 'update'])->middleware('org-role:owner');
    Route::delete('/sso-providers/{ssoProvider}', [SsoProviderController::class, 'destroy'])->middleware('org-role:owner');
    Route::post('/sso-providers/{ssoProvider}/test', [SsoProviderController::class, 'test'])->middleware('org-role:admin');

    // Workflow Execution
    Route::post('/projects/{project}/workflows/{workflow}/execute', [WorkflowRunController::class, 'execute']);
    Route::get('/projects/{project}/workflow-runs', [WorkflowRunController::class, 'index']);
    Route::get('/workflow-runs/{workflowRun}', [WorkflowRunController::class, 'show']);
    Route::post('/workflow-runs/{workflowRun}/cancel', [WorkflowRunController::class, 'cancel']);
    Route::post('/workflow-runs/{workflowRun}/steps/{workflowRunStep}/approve', [WorkflowRunController::class, 'approveCheckpoint']);
    Route::post('/workflow-runs/{workflowRun}/steps/{workflowRunStep}/reject', [WorkflowRunController::class, 'rejectCheckpoint']);

    // Visualization
    Route::get('/projects/{project}/graph', [VisualizationController::class, 'graph']);

    // Import (Reverse-Sync)
    Route::post('/import/detect', [ImportController::class, 'detect']);
    Route::post('/projects/{project}/import', [ImportController::class, 'import']);

    // Provider Health
    Route::get('/provider-health', [ProviderHealthController::class, 'index']);
    Route::post('/provider-health/check/{provider}', [ProviderHealthController::class, 'check']);

    // Models
    Route::get('/models', [ModelController::class, 'index']);

    // Billing & Subscriptions
    Route::get('/billing/status', [BillingController::class, 'status']);
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe'])->middleware('org-role:owner');
    Route::post('/billing/change-plan', [BillingController::class, 'changePlan'])->middleware('org-role:owner');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->middleware('org-role:owner');
    Route::post('/billing/resume', [BillingController::class, 'resume']);
    Route::post('/billing/setup-intent', [BillingController::class, 'setupIntent']);
    Route::put('/billing/payment-method', [BillingController::class, 'updatePaymentMethod']);
    Route::get('/billing/invoices', [BillingController::class, 'invoices']);
    Route::get('/billing/usage', [BillingController::class, 'usage']);

    // Stripe Connect (Marketplace Sellers)
    Route::post('/billing/connect', [BillingController::class, 'connectSetup']);
    Route::get('/billing/connect/status', [BillingController::class, 'connectStatus']);
    Route::get('/billing/earnings', [BillingController::class, 'earnings']);

    // Performance Dashboard
    Route::get('/performance/overview', [PerformanceDashboardController::class, 'overview']);
    Route::get('/performance/agents', [PerformanceDashboardController::class, 'agents']);
    Route::get('/performance/trends', [PerformanceDashboardController::class, 'trends']);
    Route::get('/performance/models', [PerformanceDashboardController::class, 'models']);
    Route::get('/performance/cost-breakdown', [PerformanceDashboardController::class, 'costBreakdown']);

    // Agent Team Overview
    Route::get('/projects/{project}/agent-team', [PerformanceDashboardController::class, 'agentTeam']);

    // Onboarding
    Route::get('/onboarding/status', [PerformanceDashboardController::class, 'onboardingStatus']);
    Route::post('/onboarding/quick-start', [PerformanceDashboardController::class, 'quickStart']);

    // Settings
    Route::get('/settings', SettingsController::class);
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('org-role:admin');

    // License
    Route::get('/license/status', [LicenseController::class, 'status']);
    Route::post('/license/activate', [LicenseController::class, 'activate']);

    // Setup Wizard
    Route::get('/setup/status', [SetupWizardController::class, 'status']);
    Route::post('/setup/api-keys', [SetupWizardController::class, 'configureApiKeys']);
    Route::post('/setup/default-model', [SetupWizardController::class, 'configureDefaultModel']);
    Route::post('/setup/quick-start', [SetupWizardController::class, 'quickStart']);
    Route::post('/setup/complete', [SetupWizardController::class, 'complete']);

    // Backups
    Route::get('/backups', [BackupController::class, 'index'])->middleware('org-role:admin');
    Route::post('/backups', [BackupController::class, 'store'])->middleware('org-role:admin');
    Route::post('/backups/restore', [BackupController::class, 'restore'])->middleware('org-role:owner');
    Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->middleware('org-role:admin');

    // Health Diagnostics
    Route::get('/diagnostics', [HealthCheckController::class, 'index']);
    Route::get('/diagnostics/{check}', [HealthCheckController::class, 'show']);

    // Content Review (#264)
    Route::post('/skills/{skill}/review', [ContentReviewController::class, 'reviewSkill']);
    Route::post('/agents/{agent}/review', [ContentReviewController::class, 'reviewAgent']);

    // Endpoint Approvals (#261)
    Route::get('/projects/{project}/endpoint-approvals', [EndpointApprovalController::class, 'index']);
    Route::post('/endpoint-approvals/{type}/{id}/approve', [EndpointApprovalController::class, 'approve']);
    Route::post('/endpoint-approvals/{type}/{id}/reject', [EndpointApprovalController::class, 'reject']);

    // Guardrail Policies (#259)
    Route::get('/organizations/{org}/guardrails', [GuardrailController::class, 'index']);
    Route::post('/organizations/{org}/guardrails', [GuardrailController::class, 'store']);
    Route::put('/guardrails/{policy}', [GuardrailController::class, 'update']);
    Route::delete('/guardrails/{policy}', [GuardrailController::class, 'destroy']);
    Route::get('/organizations/{org}/guardrails/resolve', [GuardrailController::class, 'resolve']);

    // Guardrail Profiles (#260)
    Route::get('/guardrail-profiles', [GuardrailController::class, 'profiles']);
    Route::get('/guardrail-profiles/{profile}', [GuardrailController::class, 'showProfile']);
    Route::post('/guardrail-profiles', [GuardrailController::class, 'storeProfile']);
    Route::delete('/guardrail-profiles/{profile}', [GuardrailController::class, 'destroyProfile']);

    // Security Scanner (#262)
    Route::post('/skills/{skill}/security-scan', [SecurityScanController::class, 'scanSkill']);
    Route::post('/security-scan', [SecurityScanController::class, 'scanContent']);

    // API Tokens (#215)
    Route::get('/api-tokens', [ApiTokenController::class, 'index']);
    Route::post('/api-tokens', [ApiTokenController::class, 'store']);
    Route::delete('/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy']);

    // SDK Downloads (#213, #214)
    Route::get('/sdk/typescript', [SdkController::class, 'typescript']);
    Route::get('/sdk/php', [SdkController::class, 'php']);

    // Custom Endpoints (#253)
    Route::get('/custom-endpoints', [CustomEndpointController::class, 'index']);
    Route::post('/custom-endpoints', [CustomEndpointController::class, 'store']);
    Route::get('/custom-endpoints/{customEndpoint}', [CustomEndpointController::class, 'show']);
    Route::put('/custom-endpoints/{customEndpoint}', [CustomEndpointController::class, 'update']);
    Route::delete('/custom-endpoints/{customEndpoint}', [CustomEndpointController::class, 'destroy']);
    Route::post('/custom-endpoints/{customEndpoint}/health', [CustomEndpointController::class, 'healthCheck']);
    Route::post('/custom-endpoints/{customEndpoint}/discover', [CustomEndpointController::class, 'discoverModels']);

    // Model Health & Benchmarking (#254)
    Route::get('/model-health', [ModelHealthController::class, 'checkAll']);
    Route::get('/model-health/{provider}', [ModelHealthController::class, 'checkProvider']);
    Route::post('/model-health/benchmark', [ModelHealthController::class, 'benchmark']);
    Route::post('/model-health/compare', [ModelHealthController::class, 'compare']);

    // Local Model Browser (#256)
    Route::get('/local-models', [ModelHealthController::class, 'localModels']);
    Route::get('/local-models/ollama/{model}', [ModelHealthController::class, 'ollamaModelDetail'])
        ->where('model', '.*');

    // Air-Gap Mode (#255)
    Route::get('/air-gap', [ModelHealthController::class, 'airGapStatus']);
    Route::post('/air-gap', [ModelHealthController::class, 'airGapToggle'])->middleware('org-role:admin');

    // Guardrail Reports (#267)
    Route::get('/organizations/{org}/guardrail-reports', [GuardrailReportController::class, 'index']);
    Route::get('/organizations/{org}/guardrail-reports/trends', [GuardrailReportController::class, 'trends']);
    Route::get('/organizations/{org}/guardrail-reports/export', [GuardrailReportController::class, 'export']);
    Route::post('/guardrail-violations/{violation}/dismiss', [GuardrailReportController::class, 'dismiss']);

    // ─── Phase E.6: Enterprise Readiness ────────────────────────

    // Skill Reviews (#219)
    Route::get('/skills/{skill}/reviews', [SkillReviewController::class, 'index']);
    Route::post('/skills/{skill}/reviews', [SkillReviewController::class, 'store']);
    Route::post('/skill-reviews/{review}/approve', [SkillReviewController::class, 'approve']);
    Route::post('/skill-reviews/{review}/reject', [SkillReviewController::class, 'reject']);

    // Skill Ownership (#220)
    Route::get('/skills/{skill}/ownership', [SkillOwnershipController::class, 'show']);
    Route::put('/skills/{skill}/ownership', [SkillOwnershipController::class, 'update']);

    // Notifications (#223)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    // Skill Analytics (#225)
    Route::get('/skills/{skill}/analytics', [SkillAnalyticsController::class, 'show']);
    Route::get('/analytics/top-skills', [SkillAnalyticsController::class, 'topSkills']);
    Route::get('/analytics/trends', [SkillAnalyticsController::class, 'trends']);

    // Skill Regression Testing (#227)
    Route::get('/skills/{skill}/test-cases', [SkillRegressionController::class, 'index']);
    Route::post('/skills/{skill}/test-cases', [SkillRegressionController::class, 'store']);
    Route::put('/skill-test-cases/{testCase}', [SkillRegressionController::class, 'update']);
    Route::delete('/skill-test-cases/{testCase}', [SkillRegressionController::class, 'destroy']);
    Route::post('/skills/{skill}/test-cases/run-all', [SkillRegressionController::class, 'runAll']);

    // Cross-Model Benchmarking (#230)
    Route::post('/skills/{skill}/benchmark', [BenchmarkController::class, 'benchmark']);

    // Skill Inheritance (#231)
    Route::get('/skills/{skill}/resolve', [SkillInheritanceController::class, 'resolve']);
    Route::get('/skills/{skill}/children', [SkillInheritanceController::class, 'children']);
    Route::put('/skills/{skill}/inheritance', [SkillInheritanceController::class, 'update']);

    // Reports (#240)
    Route::get('/reports/skills', [ReportExportController::class, 'skills']);
    Route::get('/reports/usage', [ReportExportController::class, 'usage']);
    Route::get('/reports/audit', [ReportExportController::class, 'audit']);

    // GitHub Org Import (#241)
    Route::post('/import/github/discover', [GitHubImportController::class, 'discover']);
    Route::post('/import/github/import', [GitHubImportController::class, 'import']);
});
