import axios from 'axios'
import type {
  Project,
  Skill,
  SkillVersion,
  SkillVariableValue,
  Tag,
  LibrarySkill,
  MarketplaceSkill,
  ModelGroup,
  Agent,
  ProjectAgent,
  AgentComposed,
  AgentStructured,
  GeneratedSkill,
  LintIssue,
  GitLogEntry,
  SkillsShDiscoveredSkill,
  SkillsShSkillDetail,
  BundlePreview,
  BundleImportResult,
  SyncPreviewFile,
  Webhook,
  WebhookDelivery,
  ProjectRepository,
  RepositoryStatus,
  RepositoryFile,
  ImportDetectedSkill,
  ImportResult,
  BillingPlan,
  BillingStatus,
  UsageSummary,
  ProjectGraphData,
  Workflow,
  WorkflowStep,
  WorkflowEdge,
  WorkflowVersion,
  WorkflowValidation,
  ExecutionRun,
  ExecutionStats,
  ProviderHealth,
  AgentSchedule,
  Organization,
  OrganizationMember,
  OrganizationInvitation,
  AgentBudgetStatus,
  AuditLogEntry,
  PerformanceOverview,
  AgentPerformance,
  PerformanceTrend,
  ModelUsage,
  AgentsOverview,
  OnboardingStatus,
  ApiResponse,
  CustomEndpoint,
  ModelHealthResult,
  ModelBenchmarkResult,
  ModelComparisonResult,
  LocalModel,
  OllamaModelDetail,
  AirGapStatus,
  ApiToken,
  ApiTokenCreateResult,
  GuardrailPolicy,
  GuardrailProfile,
  GuardrailViolation,
  GuardrailTrend,
  SecurityScanResult,
  ContentReviewResult,
  ContentPolicy,
  SsoProvider,
  SkillReview,
  SkillOwnership,
  SkillAnalytic,
  SkillTestCase,
  SkillTestCaseResult,
  SkillBenchmarkResult,
  SkillInheritanceInfo,
  Notification,
  GitHubDiscoveredRepo,
  GitHubImportResult,
  LicenseStatus,
  SetupStatus,
  BackupEntry,
  DiagnosticCheck,
  ModelPullProgress,
  ModelRecommendation,
  ExecutionReplay,
  ExecutionReplayStep,
  ExecutionDiff,
  PaginatedUsers,
  ManagedUser,
  CanvasLayout,
  McpServerDetail,
  A2aAgentDetail,
  DelegationConfig,
  AgentTask,
  AgentDocument,
  AgentKnowledgeEntry,
  AgentMemoryEntry,
  AgentMemoryRecallResult,
  DataSource,
  DataSourceTestResult,
  SkillGotcha,
  SkillAsset,
  SkillCategory,
  SkillEvalSuite,
  SkillEvalPrompt,
  SkillEvalRun,
  DescriptionScore,
  Artifact,
  AgentProcessInfo,
  AgentProcessStatus,
  ControlPlaneSession,
  Plugin,
  MarketplaceAgent,
  SharedMemoryPool,
  SharedMemoryEntry,
  KnowledgeGraph,
  KnowledgeGraphNode,
  KnowledgeGraphEdge,
  AgentCapability,
  RoutingRule,
  RoutingDecision,
  RoutingSimulationResult,
  PresenceSession,
  CollaborationComment,
  DebugSession,
  MobileOverview,
  CustomMetric,
  AlertRule,
  AlertIncident,
  DashboardLayout,
  CostForecast,
  MetricDataPoint,
  TaskBid,
  CapabilityAdvertisement,
  TeamFormation,
  NegotiationLog,
  FederationPeer,
  FederationDelegation,
  FederatedIdentity,
} from '@/types'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

// Fetch CSRF cookie before the first mutating request
let csrfReady = false
api.interceptors.request.use(async (config) => {
  if (!csrfReady && config.method && config.method !== 'get') {
    const baseUrl = import.meta.env.VITE_API_URL?.replace(/\/api$/, '') || ''
    await axios.get(`${baseUrl}/csrf-cookie`, { withCredentials: true })
    csrfReady = true
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const message =
      error.response?.data?.message || error.message || 'An error occurred'
    console.error('[API Error]', message)

    // Show global toast for server errors (lazy import to avoid circular dep)
    if (error.response?.status >= 500) {
      import('@/store/useAppStore').then(({ useAppStore }) => {
        useAppStore.getState().showToast(message, 'error')
      })
    }

    return Promise.reject(error)
  },
)

// Projects
export const fetchProjects = () =>
  api.get<ApiResponse<Project[]>>('/projects').then((r) => r.data.data)

export const fetchProject = (id: number) =>
  api.get<ApiResponse<Project>>(`/projects/${id}`).then((r) => r.data.data)

export const createProject = (data: Partial<Project>) =>
  api.post<ApiResponse<Project>>('/projects', data).then((r) => r.data.data)

export const updateProject = (id: number, data: Partial<Project>) =>
  api.put<ApiResponse<Project>>(`/projects/${id}`, data).then((r) => r.data.data)

export const deleteProject = (id: number) => api.delete(`/projects/${id}`)

export const scanProject = (id: number) => api.post(`/projects/${id}/scan`)

export const syncProject = (id: number) => api.post(`/projects/${id}/sync`)

export const fetchSyncPreview = (projectId: number) =>
  api
    .post<ApiResponse<SyncPreviewFile[]>>(`/projects/${projectId}/sync/preview`)
    .then((r) => r.data.data)

export const fetchProjectGraph = (projectId: number) =>
  api
    .get<ApiResponse<ProjectGraphData>>(`/projects/${projectId}/graph`)
    .then((r) => r.data.data)

// Skills
export const fetchSkills = (projectId: number) =>
  api
    .get<ApiResponse<Skill[]>>(`/projects/${projectId}/skills`)
    .then((r) => r.data.data)

export const fetchSkill = (id: number) =>
  api.get<ApiResponse<Skill>>(`/skills/${id}`).then((r) => r.data.data)

export const createSkill = (projectId: number, data: Partial<Skill>) =>
  api
    .post<ApiResponse<Skill>>(`/projects/${projectId}/skills`, data)
    .then((r) => r.data.data)

export const updateSkill = (id: number, data: Partial<Skill>) =>
  api.put<ApiResponse<Skill>>(`/skills/${id}`, data).then((r) => r.data.data)

export const deleteSkill = (id: number) => api.delete(`/skills/${id}`)

export const duplicateSkill = (id: number, targetProjectId?: number) =>
  api
    .post<ApiResponse<Skill>>(`/skills/${id}/duplicate`, {
      target_project_id: targetProjectId,
    })
    .then((r) => r.data.data)

// Skill Template Variables
export const fetchSkillVariables = (projectId: number, skillId: number) =>
  api
    .get<{
      data: {
        definitions: Array<{ name: string; description: string; default: string | null; value: string | null }>
        values: SkillVariableValue[]
        body_variables: string[]
      }
    }>(`/projects/${projectId}/skills/${skillId}/variables`)
    .then((r) => r.data.data)

export const updateSkillVariables = (
  projectId: number,
  skillId: number,
  variables: Record<string, string>,
) =>
  api
    .put<{ data: SkillVariableValue[]; message: string }>(
      `/projects/${projectId}/skills/${skillId}/variables`,
      { variables },
    )
    .then((r) => r.data)

// Bulk Skill Operations
export const bulkTagSkills = (skillIds: number[], addTags: string[], removeTags: string[]) =>
  api
    .post<{ message: string; count: number }>('/skills/bulk-tag', {
      skill_ids: skillIds,
      add_tags: addTags,
      remove_tags: removeTags,
    })
    .then((r) => r.data)

export const bulkAssignSkills = (skillIds: number[], agentId: number, projectId: number) =>
  api
    .post<{ message: string; count: number }>('/skills/bulk-assign', {
      skill_ids: skillIds,
      agent_id: agentId,
      project_id: projectId,
    })
    .then((r) => r.data)

export const bulkDeleteSkills = (skillIds: number[]) =>
  api
    .post<{ message: string; count: number }>('/skills/bulk-delete', {
      skill_ids: skillIds,
    })
    .then((r) => r.data)

export const bulkMoveSkills = (skillIds: number[], targetProjectId: number) =>
  api
    .post<{ message: string; count: number }>('/skills/bulk-move', {
      skill_ids: skillIds,
      target_project_id: targetProjectId,
    })
    .then((r) => r.data)

// Lint
export const lintSkill = (skillId: number) =>
  api
    .get<ApiResponse<LintIssue[]>>(`/skills/${skillId}/lint`)
    .then((r) => r.data.data)

// Eval Suites
export const fetchEvalSuites = (skillId: number) =>
  api
    .get<ApiResponse<SkillEvalSuite[]>>(`/skills/${skillId}/eval-suites`)
    .then((r) => r.data.data)

export const createEvalSuite = (
  skillId: number,
  data: { name: string; description?: string },
) =>
  api
    .post<ApiResponse<SkillEvalSuite>>(`/skills/${skillId}/eval-suites`, data)
    .then((r) => r.data.data)

export const fetchEvalSuite = (id: number) =>
  api
    .get<ApiResponse<SkillEvalSuite>>(`/eval-suites/${id}`)
    .then((r) => r.data.data)

export const deleteEvalSuite = (id: number) =>
  api.delete(`/eval-suites/${id}`)

export const createEvalPrompt = (
  suiteId: number,
  data: { prompt: string; expected_behavior?: string },
) =>
  api
    .post<ApiResponse<SkillEvalPrompt>>(
      `/eval-suites/${suiteId}/prompts`,
      data,
    )
    .then((r) => r.data.data)

export const deleteEvalPrompt = (id: number) =>
  api.delete(`/eval-prompts/${id}`)

export const runEval = (
  suiteId: number,
  data: { model: string; mode: string },
) =>
  api
    .post<ApiResponse<SkillEvalRun>>(`/eval-suites/${suiteId}/run`, data)
    .then((r) => r.data.data)

export const fetchEvalRuns = (suiteId: number) =>
  api
    .get<ApiResponse<SkillEvalRun[]>>(`/eval-suites/${suiteId}/runs`)
    .then((r) => r.data.data)

export const scoreDescription = (skillId: number) =>
  api
    .get<ApiResponse<DescriptionScore>>(
      `/skills/${skillId}/score-description`,
    )
    .then((r) => r.data.data)

// Agent Processes (Daemon)
export const fetchAgentFleet = () =>
  api
    .get<ApiResponse<AgentProcessInfo[]>>('/agent-processes')
    .then((r) => r.data.data)

export const fetchAgentProcessStatus = (projectId: number, agentId: number) =>
  api
    .get<AgentProcessStatus>(
      `/projects/${projectId}/agents/${agentId}/process`,
    )
    .then((r) => r.data)

export const startAgentProcess = (
  projectId: number,
  agentId: number,
  options?: { restart_policy?: string; wake_conditions?: Record<string, unknown> },
) =>
  api.post(`/projects/${projectId}/agents/${agentId}/process/start`, options)

export const stopAgentProcess = (projectId: number, agentId: number) =>
  api.post(`/projects/${projectId}/agents/${agentId}/process/stop`)

export const restartAgentProcess = (projectId: number, agentId: number) =>
  api.post(`/projects/${projectId}/agents/${agentId}/process/restart`)

// Artifacts
export const fetchArtifacts = (
  projectId: number,
  params?: { type?: string; status?: string; agent_id?: number },
) =>
  api
    .get<ApiResponse<Artifact[]>>(`/projects/${projectId}/artifacts`, {
      params,
    })
    .then((r) => r.data.data)

export const createArtifact = (
  projectId: number,
  data: Partial<Artifact>,
) =>
  api
    .post<ApiResponse<Artifact>>(`/projects/${projectId}/artifacts`, data)
    .then((r) => r.data.data)

export const fetchArtifact = (id: number) =>
  api.get<ApiResponse<Artifact>>(`/artifacts/${id}`).then((r) => r.data.data)

export const updateArtifact = (id: number, data: Partial<Artifact>) =>
  api
    .put<ApiResponse<Artifact>>(`/artifacts/${id}`, data)
    .then((r) => r.data.data)

export const deleteArtifact = (id: number) =>
  api.delete(`/artifacts/${id}`)

export const fetchArtifactVersions = (id: number) =>
  api
    .get<ApiResponse<Artifact[]>>(`/artifacts/${id}/versions`)
    .then((r) => r.data.data)

export const approveArtifact = (id: number) =>
  api
    .post<ApiResponse<Artifact>>(`/artifacts/${id}/approve`)
    .then((r) => r.data.data)

export const rejectArtifact = (id: number) =>
  api
    .post<ApiResponse<Artifact>>(`/artifacts/${id}/reject`)
    .then((r) => r.data.data)

export const fetchExecutionArtifacts = (executionRunId: number) =>
  api
    .get<ApiResponse<Artifact[]>>(
      `/executions/${executionRunId}/artifacts`,
    )
    .then((r) => r.data.data)

// Skill Categories
export const fetchSkillCategories = () =>
  api
    .get<ApiResponse<SkillCategory[]>>('/skill-categories')
    .then((r) => r.data.data)

// Skill Gotchas
export const fetchGotchas = (skillId: number, activeOnly = false) =>
  api
    .get<ApiResponse<SkillGotcha[]>>(
      `/skills/${skillId}/gotchas${activeOnly ? '?active_only=true' : ''}`,
    )
    .then((r) => r.data.data)

export const createGotcha = (
  skillId: number,
  data: { title: string; description: string; severity?: string; source?: string; source_reference_id?: number },
) =>
  api
    .post<ApiResponse<SkillGotcha>>(`/skills/${skillId}/gotchas`, data)
    .then((r) => r.data.data)

export const updateGotcha = (id: number, data: Partial<SkillGotcha>) =>
  api
    .put<ApiResponse<SkillGotcha>>(`/gotchas/${id}`, data)
    .then((r) => r.data.data)

export const deleteGotcha = (id: number) => api.delete(`/gotchas/${id}`)

export const resolveGotcha = (id: number) =>
  api
    .post<ApiResponse<SkillGotcha>>(`/gotchas/${id}/resolve`)
    .then((r) => r.data.data)

export const reopenGotcha = (id: number) =>
  api
    .post<ApiResponse<SkillGotcha>>(`/gotchas/${id}/reopen`)
    .then((r) => r.data.data)

// Skill Assets
export const fetchSkillAssets = (skillId: number) =>
  api
    .get<{ data: SkillAsset[]; is_folder: boolean }>(`/skills/${skillId}/assets`)
    .then((r) => r.data)

export const uploadSkillAssets = (
  skillId: number,
  files: File[],
  directory: 'assets' | 'scripts' | 'data',
) => {
  const formData = new FormData()
  files.forEach((f) => formData.append('files[]', f))
  formData.append('directory', directory)
  return api
    .post<{ data: SkillAsset[]; message: string }>(
      `/skills/${skillId}/assets`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    .then((r) => r.data)
}

export const deleteSkillAsset = (skillId: number, path: string) =>
  api.delete(`/skills/${skillId}/assets/${path}`)

// Versions
export const fetchVersions = (skillId: number) =>
  api
    .get<ApiResponse<SkillVersion[]>>(`/skills/${skillId}/versions`)
    .then((r) => r.data.data)

export const fetchVersion = (skillId: number, versionNumber: number) =>
  api
    .get<ApiResponse<SkillVersion>>(
      `/skills/${skillId}/versions/${versionNumber}`,
    )
    .then((r) => r.data.data)

export const restoreVersion = (skillId: number, versionNumber: number) =>
  api
    .post<ApiResponse<SkillVersion>>(
      `/skills/${skillId}/versions/${versionNumber}/restore`,
    )
    .then((r) => r.data.data)

// Tags
export const fetchTags = () =>
  api.get<Tag[]>('/tags').then((r) => r.data)

export const createTag = (data: { name: string; color?: string }) =>
  api.post<Tag>('/tags', data).then((r) => r.data)

export const deleteTag = (id: number) => api.delete(`/tags/${id}`)

// Search
export const searchSkills = (params: {
  q?: string
  tags?: string
  project_id?: number
  model?: string
}) =>
  api
    .get<ApiResponse<Skill[]>>('/search', { params })
    .then((r) => r.data.data)

// Library
export const fetchLibrary = (params?: {
  category?: string
  tags?: string
  q?: string
}) => api.get<LibrarySkill[]>('/library', { params }).then((r) => r.data)

export const createLibrarySkill = (data: Partial<LibrarySkill>) =>
  api.post<LibrarySkill>('/library', data).then((r) => r.data)

export const updateLibrarySkill = (id: number, data: Partial<LibrarySkill>) =>
  api.put<LibrarySkill>(`/library/${id}`, data).then((r) => r.data)

export const deleteLibrarySkill = (id: number) => api.delete(`/library/${id}`)

export const importLibrarySkill = (id: number, projectId: number) =>
  api
    .post<ApiResponse<Skill>>(`/library/${id}/import`, {
      project_id: projectId,
    })
    .then((r) => r.data.data)

// Skills.sh
export const discoverSkillsSh = (repo: string) =>
  api
    .post<{ data: SkillsShDiscoveredSkill[]; repo: string; count: number }>(
      '/skills-sh/discover',
      { repo },
    )
    .then((r) => r.data)

export const previewSkillsSh = (repo: string, paths: string[]) =>
  api
    .post<{ data: SkillsShSkillDetail[] }>('/skills-sh/preview', { repo, paths })
    .then((r) => r.data.data)

export const importSkillSh = (
  repo: string,
  path: string,
  target: 'library' | 'project',
  projectId?: number,
) =>
  api.post('/skills-sh/import', {
    repo,
    path,
    target,
    project_id: projectId,
  })

// Agents — global CRUD
export const fetchAgents = () =>
  api.get<ApiResponse<Agent[]>>('/agents').then((r) => r.data.data)

export const fetchAgent = (agentId: number) =>
  api.get<ApiResponse<Agent>>(`/agents/${agentId}`).then((r) => r.data.data)

export const createAgent = (data: Partial<Agent>) =>
  api.post<ApiResponse<Agent>>('/agents', data).then((r) => r.data.data)

export const updateAgent = (agentId: number, data: Partial<Agent>) =>
  api.put<ApiResponse<Agent>>(`/agents/${agentId}`, data).then((r) => r.data.data)

export const deleteAgent = (agentId: number) =>
  api.delete(`/agents/${agentId}`)

export const duplicateAgent = (agentId: number) =>
  api.post<ApiResponse<Agent>>(`/agents/${agentId}/duplicate`).then((r) => r.data.data)

export const exportAgent = (agentId: number, format: 'json' | 'yaml' = 'json') =>
  api.get(`/agents/${agentId}/export`, { params: { format } }).then((r) => r.data)

// Agents — project-scoped
export const fetchProjectAgents = (projectId: number) =>
  api
    .get<ApiResponse<ProjectAgent[]>>(`/projects/${projectId}/agents`)
    .then((r) => r.data.data)

export const toggleAgent = (projectId: number, agentId: number, isEnabled: boolean) =>
  api.put(`/projects/${projectId}/agents/${agentId}/toggle`, { is_enabled: isEnabled })

export const updateAgentInstructions = (
  projectId: number,
  agentId: number,
  customInstructions: string | null,
) =>
  api.put(`/projects/${projectId}/agents/${agentId}/instructions`, {
    custom_instructions: customInstructions,
  })

export const assignAgentSkills = (
  projectId: number,
  agentId: number,
  skillIds: number[],
) =>
  api.put(`/projects/${projectId}/agents/${agentId}/skills`, {
    skill_ids: skillIds,
  })

export const bindAgentMcpServers = (
  projectId: number,
  agentId: number,
  mcpServerIds: number[],
) =>
  api.put(`/projects/${projectId}/agents/${agentId}/mcp-servers`, {
    mcp_server_ids: mcpServerIds,
  })

export const bindAgentA2aAgents = (
  projectId: number,
  agentId: number,
  a2aAgentIds: number[],
) =>
  api.put(`/projects/${projectId}/agents/${agentId}/a2a-agents`, {
    a2a_agent_ids: a2aAgentIds,
  })

// Agent Compose
export const fetchAgentCompose = (projectId: number, agentId: number) =>
  api
    .get<ApiResponse<AgentComposed>>(
      `/projects/${projectId}/agents/${agentId}/compose`,
    )
    .then((r) => r.data.data)

export const fetchAgentComposeStructured = (projectId: number, agentId: number) =>
  api
    .get<ApiResponse<AgentStructured>>(
      `/projects/${projectId}/agents/${agentId}/compose-structured`,
    )
    .then((r) => r.data.data)

export const fetchAllAgentCompose = (projectId: number) =>
  api
    .get<ApiResponse<AgentComposed[]>>(`/projects/${projectId}/agents/compose`)
    .then((r) => r.data.data)

// AI Skill Generation
export const generateSkill = (description: string, constraints?: string) =>
  api
    .post<ApiResponse<GeneratedSkill>>('/skills/generate', {
      description,
      constraints: constraints || undefined,
    })
    .then((r) => r.data.data)

// Token Estimation (client-side, ~1 token per 4 chars)
export const estimateTokens = (text: string): number =>
  Math.ceil(text.length / 4)

// Git
export const fetchGitLog = (projectId: number, file: string) =>
  api
    .get<{ data: GitLogEntry[]; branch: string | null }>(
      `/projects/${projectId}/git-log`,
      { params: { file } },
    )
    .then((r) => r.data)

export const fetchGitDiff = (projectId: number, file: string, ref?: string) =>
  api
    .get<{ diff: string; content: string | null }>(
      `/projects/${projectId}/git-diff`,
      { params: { file, ref } },
    )
    .then((r) => r.data)

// Bundles (Export/Import)
export const exportBundle = (
  projectId: number,
  data: { skill_ids: number[]; agent_ids: number[]; content_format: string },
) =>
  api
    .post(`/projects/${projectId}/export`, data, {
      responseType: 'blob',
    })
    .then((r) => r.data as Blob)

export const importBundlePreview = (projectId: number, file: File) => {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('preview', '1')
  return api
    .post<BundlePreview>(`/projects/${projectId}/import-bundle`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data)
}

export const importBundle = (
  projectId: number,
  file: File,
  conflictMode: string,
) => {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('conflict_mode', conflictMode)
  return api
    .post<BundleImportResult>(`/projects/${projectId}/import-bundle`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data)
}

// Marketplace
export const fetchMarketplace = (params?: {
  category?: string
  tags?: string
  q?: string
  sort?: string
  page?: number
}) =>
  api
    .get<{
      data: MarketplaceSkill[]
      current_page: number
      last_page: number
      total: number
    }>('/marketplace', { params })
    .then((r) => r.data)

export const fetchMarketplaceSkill = (id: number) =>
  api
    .get<ApiResponse<MarketplaceSkill>>(`/marketplace/${id}`)
    .then((r) => r.data.data)

export const publishToMarketplace = (data: {
  source_type: string
  source_id: number
  author?: string
}) =>
  api
    .post<ApiResponse<MarketplaceSkill>>('/marketplace/publish', data)
    .then((r) => r.data.data)

export const installFromMarketplace = (
  id: number,
  data: { target: string; project_id?: number },
) =>
  api
    .post<ApiResponse<MarketplaceSkill> & { message: string }>(
      `/marketplace/${id}/install`,
      data,
    )
    .then((r) => r.data)

export const voteMarketplaceSkill = (id: number, vote: 'up' | 'down') =>
  api
    .post<ApiResponse<MarketplaceSkill>>(`/marketplace/${id}/vote`, { vote })
    .then((r) => r.data.data)

// Webhooks
export const fetchWebhooks = (projectId: number) =>
  api
    .get<ApiResponse<Webhook[]>>(`/projects/${projectId}/webhooks`)
    .then((r) => r.data.data)

export const createWebhook = (
  projectId: number,
  data: { event: string; url: string; secret?: string; is_active?: boolean },
) =>
  api
    .post<ApiResponse<Webhook>>(`/projects/${projectId}/webhooks`, data)
    .then((r) => r.data.data)

export const updateWebhook = (
  id: number,
  data: Partial<{ event: string; url: string; secret: string | null; is_active: boolean }>,
) =>
  api
    .put<ApiResponse<Webhook>>(`/webhooks/${id}`, data)
    .then((r) => r.data.data)

export const deleteWebhook = (id: number) => api.delete(`/webhooks/${id}`)

export const fetchWebhookDeliveries = (id: number) =>
  api
    .get<ApiResponse<WebhookDelivery[]>>(`/webhooks/${id}/deliveries`)
    .then((r) => r.data.data)

export const testWebhook = (id: number) =>
  api.post(`/webhooks/${id}/test`).then((r) => r.data)

// Repositories
export const fetchRepositories = (projectId: number) =>
  api
    .get<ApiResponse<ProjectRepository[]>>(`/projects/${projectId}/repositories`)
    .then((r) => r.data.data)

export const connectRepository = (
  projectId: number,
  data: {
    provider: 'github' | 'gitlab'
    full_name: string
    access_token?: string
    auto_scan_on_push?: boolean
    auto_sync_on_push?: boolean
  },
) =>
  api
    .post<ApiResponse<ProjectRepository> & { message: string }>(
      `/projects/${projectId}/repositories`,
      data,
    )
    .then((r) => r.data)

export const updateRepository = (
  projectId: number,
  provider: string,
  data: {
    access_token?: string
    auto_scan_on_push?: boolean
    auto_sync_on_push?: boolean
    default_branch?: string
  },
) =>
  api
    .put<ApiResponse<ProjectRepository> & { message: string }>(
      `/projects/${projectId}/repositories/${provider}`,
      data,
    )
    .then((r) => r.data)

export const disconnectRepository = (projectId: number, provider: string) =>
  api.delete(`/projects/${projectId}/repositories/${provider}`)

export const fetchRepositoryStatus = (projectId: number, provider: string) =>
  api
    .get<ApiResponse<RepositoryStatus>>(
      `/projects/${projectId}/repositories/${provider}/status`,
    )
    .then((r) => r.data.data)

export const fetchRepositoryBranches = (projectId: number, provider: string) =>
  api
    .get<ApiResponse<string[]>>(
      `/projects/${projectId}/repositories/${provider}/branches`,
    )
    .then((r) => r.data.data)

export const fetchRepositoryFiles = (projectId: number, provider: string) =>
  api
    .get<ApiResponse<RepositoryFile[]>>(
      `/projects/${projectId}/repositories/${provider}/files`,
    )
    .then((r) => r.data.data)

export const pullRepositorySkills = (projectId: number, provider: string) =>
  api
    .post<{ data: Array<{ path: string; content: string; sha: string }>; count: number; message: string }>(
      `/projects/${projectId}/repositories/${provider}/pull`,
    )
    .then((r) => r.data)

export const pushRepositorySkills = (
  projectId: number,
  provider: string,
  files: Array<{ path: string; content: string }>,
  commitMessage?: string,
) =>
  api
    .post<{ data: Array<{ path: string; status: string; message?: string }>; message: string }>(
      `/projects/${projectId}/repositories/${provider}/push`,
      { files, commit_message: commitMessage },
    )
    .then((r) => r.data)

export const fetchAllowedPaths = () =>
  api
    .get<ApiResponse<string[]>>('/repositories/allowed-paths')
    .then((r) => r.data.data)

// Import (Reverse-Sync)
export const detectImportableSkills = (path: string, provider?: string) =>
  api
    .post<ApiResponse<Record<string, ImportDetectedSkill[]>>>('/import/detect', {
      path,
      provider,
    })
    .then((r) => r.data.data)

export const importFromProvider = (projectId: number, path: string, provider?: string) =>
  api
    .post<ApiResponse<ImportResult>>(`/projects/${projectId}/import`, {
      path,
      provider,
    })
    .then((r) => r.data.data)

// Models
export const fetchModels = () =>
  api.get<{ data: ModelGroup[] }>('/models').then((r) => r.data.data)

// Skill staleness
export interface StalenessStatus {
  is_stale: boolean
  reason: 'ok' | 'needs_tuning' | 'needs_revalidation' | 'model_deprecated'
  tuned_for_model: string | null
  last_validated_model: string | null
  last_validated_at: string | null
  suggested_action: string
}

export const fetchStaleness = (skillId: number, currentModel?: string) =>
  api
    .get<{ data: StalenessStatus }>(
      `/skills/${skillId}/staleness${currentModel ? `?current_model=${encodeURIComponent(currentModel)}` : ''}`,
    )
    .then((r) => r.data.data)

export const updateStalenessTuning = (skillId: number, tunedForModel: string | null) =>
  api
    .put<{ data: StalenessStatus }>(`/skills/${skillId}/staleness`, {
      tuned_for_model: tunedForModel,
    })
    .then((r) => r.data.data)

// Settings
export const fetchSettings = () =>
  api
    .get<{
      anthropic_api_key_set: boolean
      openai_api_key_set: boolean
      gemini_api_key_set: boolean
      ollama_url: string
      default_model: string
      allowed_project_paths: string[]
    }>('/settings')
    .then((r) => r.data)

export const updateSettings = (data: Record<string, string>) =>
  api.put<{ message: string }>('/settings', data).then((r) => r.data)

// Billing
export const fetchBillingPlans = () =>
  api.get<ApiResponse<BillingPlan[]>>('/billing/plans').then((r) => r.data.data)

export const fetchBillingStatus = () =>
  api.get<ApiResponse<BillingStatus>>('/billing/status').then((r) => r.data.data)

export const subscribeToPlan = (plan: string, interval: 'monthly' | 'yearly', paymentMethod?: string) =>
  api
    .post<ApiResponse<Record<string, unknown>>>('/billing/subscribe', {
      plan,
      interval,
      payment_method: paymentMethod,
    })
    .then((r) => r.data.data)

export const changePlan = (plan: string, interval: 'monthly' | 'yearly') =>
  api
    .post<ApiResponse<Record<string, unknown>>>('/billing/change-plan', { plan, interval })
    .then((r) => r.data.data)

export const cancelSubscription = () =>
  api.post<ApiResponse<Record<string, unknown>>>('/billing/cancel').then((r) => r.data.data)

export const resumeSubscription = () =>
  api.post<ApiResponse<Record<string, unknown>>>('/billing/resume').then((r) => r.data.data)

export const createSetupIntent = () =>
  api.post<ApiResponse<{ client_secret: string }>>('/billing/setup-intent').then((r) => r.data.data)

export const updatePaymentMethod = (paymentMethod: string) =>
  api.put('/billing/payment-method', { payment_method: paymentMethod })

export const fetchInvoices = () =>
  api
    .get<ApiResponse<Array<{ id: string; date: string; total: string; status: string; pdf_url: string }>>>('/billing/invoices')
    .then((r) => r.data.data)

export const fetchUsage = () =>
  api.get<ApiResponse<UsageSummary>>('/billing/usage').then((r) => r.data.data)

export const setupStripeConnect = () =>
  api.post<ApiResponse<{ onboarding_url?: string; dashboard_url?: string; status: string }>>('/billing/connect').then((r) => r.data.data)

export const fetchConnectStatus = () =>
  api.get<ApiResponse<{ status: string; onboarded: boolean }>>('/billing/connect/status').then((r) => r.data.data)

export const fetchEarnings = () =>
  api
    .get<ApiResponse<{ total_earned: number; pending: number; payout_count: number }>>('/billing/earnings')
    .then((r) => r.data.data)

// Workflows
export const fetchWorkflows = (projectId: number) =>
  api
    .get<ApiResponse<Workflow[]>>(`/projects/${projectId}/workflows`)
    .then((r) => r.data.data)

export const fetchWorkflow = (projectId: number, workflowId: number) =>
  api
    .get<ApiResponse<Workflow>>(`/projects/${projectId}/workflows/${workflowId}`)
    .then((r) => r.data.data)

export const createWorkflow = (projectId: number, data: Partial<Workflow>) =>
  api
    .post<ApiResponse<Workflow>>(`/projects/${projectId}/workflows`, data)
    .then((r) => r.data.data)

export const updateWorkflow = (projectId: number, workflowId: number, data: Partial<Workflow>) =>
  api
    .put<ApiResponse<Workflow>>(`/projects/${projectId}/workflows/${workflowId}`, data)
    .then((r) => r.data.data)

export const deleteWorkflow = (projectId: number, workflowId: number) =>
  api.delete(`/projects/${projectId}/workflows/${workflowId}`)

export const duplicateWorkflow = (projectId: number, workflowId: number) =>
  api
    .post<ApiResponse<Workflow>>(`/projects/${projectId}/workflows/${workflowId}/duplicate`)
    .then((r) => r.data.data)

export const updateWorkflowSteps = (
  projectId: number,
  workflowId: number,
  steps: Partial<WorkflowStep>[],
) =>
  api
    .put<ApiResponse<Workflow>>(`/projects/${projectId}/workflows/${workflowId}/steps`, { steps })
    .then((r) => r.data.data)

export const updateWorkflowEdges = (
  projectId: number,
  workflowId: number,
  edges: Partial<WorkflowEdge>[],
) =>
  api
    .put<ApiResponse<Workflow>>(`/projects/${projectId}/workflows/${workflowId}/edges`, { edges })
    .then((r) => r.data.data)

export const validateWorkflow = (projectId: number, workflowId: number) =>
  api
    .post<WorkflowValidation>(`/projects/${projectId}/workflows/${workflowId}/validate`)
    .then((r) => r.data)

export const fetchWorkflowVersions = (projectId: number, workflowId: number) =>
  api
    .get<WorkflowVersion[]>(`/projects/${projectId}/workflows/${workflowId}/versions`)
    .then((r) => r.data)

export const createWorkflowVersion = (projectId: number, workflowId: number, note?: string) =>
  api
    .post<WorkflowVersion>(`/projects/${projectId}/workflows/${workflowId}/versions`, { note })
    .then((r) => r.data)

export const restoreWorkflowVersion = (
  projectId: number,
  workflowId: number,
  versionNumber: number,
) =>
  api
    .post<ApiResponse<Workflow>>(
      `/projects/${projectId}/workflows/${workflowId}/versions/${versionNumber}/restore`,
    )
    .then((r) => r.data.data)

// Workflow Export
export const exportWorkflow = (projectId: number, workflowId: number, format: 'json' | 'langgraph' | 'crewai' = 'json') =>
  api
    .get(`/projects/${projectId}/workflows/${workflowId}/export`, { params: { format } })
    .then((r) => r.data)

// MCP Server Tools
export const fetchMcpServerTools = (projectId: number, serverId: number) =>
  api
    .get(`/projects/${projectId}/mcp-servers/${serverId}/tools`)
    .then((r) => r.data)

export const pingMcpServer = (projectId: number, serverId: number) =>
  api
    .post(`/projects/${projectId}/mcp-servers/${serverId}/ping`)
    .then((r) => r.data)

// Agent Execution
export const executeAgent = (projectId: number, agentId: number, input: Record<string, unknown> = {}, config: Record<string, unknown> = {}) =>
  api
    .post<ApiResponse<ExecutionRun>>(`/projects/${projectId}/agents/${agentId}/execute`, { input, config })
    .then((r) => r.data.data)

export const fetchExecutionRuns = (projectId: number, params: { status?: string; agent_id?: number } = {}) =>
  api
    .get<ApiResponse<ExecutionRun[]>>(`/projects/${projectId}/runs`, { params })
    .then((r) => r.data.data)

export const fetchExecutionRun = (runId: number) =>
  api
    .get<ApiResponse<ExecutionRun>>(`/runs/${runId}`)
    .then((r) => r.data.data)

export const cancelExecutionRun = (runId: number) =>
  api
    .post(`/runs/${runId}/cancel`)
    .then((r) => r.data)

export const fetchExecutionStats = (projectId: number) =>
  api
    .get<ExecutionStats>(`/projects/${projectId}/runs/stats`)
    .then((r) => r.data)

// #381 — Run agent from canvas (returns execution_id + stream_url)
export const runAgent = (projectId: number, agentId: number, input: string, triggerType?: string) =>
  api
    .post<{ execution_id: number; stream_url: string }>(`/projects/${projectId}/agents/${agentId}/run`, { input, trigger_type: triggerType })
    .then((r) => r.data)

// #382 — Cancel execution
export const cancelExecution = (executionId: number) =>
  api
    .post(`/executions/${executionId}/cancel`)
    .then((r) => r.data)

// Provider Health
export const fetchProviderHealth = () =>
  api
    .get<ApiResponse<Record<string, ProviderHealth>>>('/provider-health')
    .then((r) => r.data.data)

export const checkProviderHealth = (provider: string) =>
  api
    .post<ApiResponse<ProviderHealth>>(`/provider-health/check/${provider}`)
    .then((r) => r.data.data)

// Agent Schedules
export const fetchSchedules = (projectId: number) =>
  api.get<ApiResponse<AgentSchedule[]>>(`/projects/${projectId}/schedules`).then((r) => r.data.data)

export const createSchedule = (projectId: number, data: Partial<AgentSchedule>) =>
  api.post<ApiResponse<AgentSchedule>>(`/projects/${projectId}/schedules`, data).then((r) => r.data.data)

export const fetchSchedule = (scheduleId: number) =>
  api.get<ApiResponse<AgentSchedule>>(`/schedules/${scheduleId}`).then((r) => r.data.data)

export const updateSchedule = (scheduleId: number, data: Partial<AgentSchedule>) =>
  api.put<ApiResponse<AgentSchedule>>(`/schedules/${scheduleId}`, data).then((r) => r.data.data)

export const deleteSchedule = (scheduleId: number) =>
  api.delete(`/schedules/${scheduleId}`)

export const toggleSchedule = (scheduleId: number, isEnabled: boolean) =>
  api.post(`/schedules/${scheduleId}/toggle`, { is_enabled: isEnabled })

export const triggerSchedule = (scheduleId: number) =>
  api.post<ApiResponse<unknown>>(`/schedules/${scheduleId}/trigger`).then((r) => r.data)

export const fetchScheduleRuns = (scheduleId: number) =>
  api.get<ApiResponse<ExecutionRun[]>>(`/schedules/${scheduleId}/runs`).then((r) => r.data.data)

// Organizations
export const fetchOrganizations = () =>
  api.get<ApiResponse<Organization[]>>('/organizations').then((r) => r.data.data)

export const createOrganization = (data: { name: string; description?: string }) =>
  api.post<ApiResponse<Organization>>('/organizations', data).then((r) => r.data.data)

export const fetchOrganization = (orgId: number) =>
  api.get<ApiResponse<Organization>>(`/organizations/${orgId}`).then((r) => r.data.data)

export const updateOrganization = (orgId: number, data: { name?: string; description?: string }) =>
  api.put<ApiResponse<Organization>>(`/organizations/${orgId}`, data).then((r) => r.data.data)

export const deleteOrganization = (orgId: number) =>
  api.delete(`/organizations/${orgId}`)

export const switchOrganization = (orgId: number) =>
  api.post(`/organizations/${orgId}/switch`).then((r) => r.data)

export const fetchOrgMembers = (orgId: number) =>
  api.get<ApiResponse<OrganizationMember[]>>(`/organizations/${orgId}/members`).then((r) => r.data.data)

export const inviteOrgMember = (orgId: number, data: { email: string; role?: string }) =>
  api.post(`/organizations/${orgId}/invitations`, data).then((r) => r.data)

export const fetchOrgInvitations = (orgId: number) =>
  api.get<ApiResponse<OrganizationInvitation[]>>(`/organizations/${orgId}/invitations`).then((r) => r.data.data)

export const cancelInvitation = (invitationId: number) =>
  api.delete(`/invitations/${invitationId}`)

export const acceptInvitation = (token: string) =>
  api.post(`/invitations/accept/${token}`).then((r) => r.data)

export const updateMemberRole = (orgId: number, userId: number, role: string) =>
  api.put(`/organizations/${orgId}/members/${userId}`, { role }).then((r) => r.data)

export const removeMember = (orgId: number, userId: number) =>
  api.delete(`/organizations/${orgId}/members/${userId}`)

// User Management
export const fetchManagedUsers = (params?: { per_page?: number; page?: number }) =>
  api.get<PaginatedUsers>('/users', { params }).then((r) => r.data)

export const createManagedUser = (data: { name: string; email: string; password: string; role?: string }) =>
  api.post<{ data: ManagedUser }>('/users', data).then((r) => r.data.data)

export const updateManagedUser = (userId: number, data: { name?: string; email?: string; password?: string; role?: string }) =>
  api.put<{ data: ManagedUser }>(`/users/${userId}`, data).then((r) => r.data.data)

export const deleteManagedUser = (userId: number) =>
  api.delete(`/users/${userId}`)

// Agent Budget
export const fetchAgentBudgetStatus = (agentId: number) =>
  api.get<ApiResponse<AgentBudgetStatus>>(`/agents/${agentId}/budget-status`).then((r) => r.data.data)

export const updateAgentBudget = (agentId: number, data: { budget_limit_usd?: number | null; daily_budget_limit_usd?: number | null }) =>
  api.put(`/agents/${agentId}/budget`, data).then((r) => r.data)

// Agent Tool Scope
export const fetchAgentToolScope = (agentId: number) =>
  api.get(`/agents/${agentId}/tool-scope`).then((r) => r.data)

export const updateAgentToolScope = (agentId: number, data: { allowed_tools?: string[] | null; blocked_tools?: string[] | null }) =>
  api.put(`/agents/${agentId}/tool-scope`, data).then((r) => r.data)

// Step Approval
export const approveStep = (runId: number, stepId: number, note?: string) =>
  api.post(`/runs/${runId}/steps/${stepId}/approve`, { note }).then((r) => r.data)

export const rejectStep = (runId: number, stepId: number, note?: string) =>
  api.post(`/runs/${runId}/steps/${stepId}/reject`, { note }).then((r) => r.data)

export const resumeRun = (runId: number) =>
  api.post(`/runs/${runId}/resume`).then((r) => r.data)

// Audit Logs
export const fetchAuditLogs = (params?: { event?: string; agent_id?: number; page?: number }) =>
  api.get<{ data: AuditLogEntry[]; current_page: number; last_page: number; total: number }>('/audit-logs', { params }).then((r) => r.data)

export const fetchAgentAuditLogs = (agentId: number, params?: { page?: number }) =>
  api.get<{ data: AuditLogEntry[]; current_page: number; last_page: number; total: number }>(`/agents/${agentId}/audit-logs`, { params }).then((r) => r.data)

// Performance Dashboard
export const fetchPerformanceOverview = (params?: { period?: string; agent_id?: number; project_id?: number }) =>
  api.get<ApiResponse<PerformanceOverview>>('/performance/overview', { params }).then(r => r.data.data)

export const fetchAgentPerformance = (params?: { period?: string; project_id?: number; sort?: string }) =>
  api.get<ApiResponse<AgentPerformance[]>>('/performance/agents', { params }).then(r => r.data.data)

export const fetchPerformanceTrends = (params?: { period?: string; agent_id?: number; project_id?: number }) =>
  api.get<ApiResponse<PerformanceTrend[]>>('/performance/trends', { params }).then(r => r.data.data)

export const fetchModelUsage = (params?: { period?: string }) =>
  api.get<ApiResponse<ModelUsage[]>>('/performance/models', { params }).then(r => r.data.data)

export const fetchCostBreakdown = (params?: { period?: string; group_by?: string }) =>
  api.get<ApiResponse<Array<{ name: string; total_cost_usd: number; run_count: number }>>>('/performance/cost-breakdown', { params }).then(r => r.data.data)

// Agents Overview
export const fetchAgentsOverview = () =>
  api.get<ApiResponse<AgentsOverview>>('/agents/overview').then(r => r.data.data)

// Agent Team
export const fetchAgentTeam = (projectId: number) =>
  api.get<ApiResponse<ProjectAgent[]>>(`/projects/${projectId}/agent-team`).then(r => r.data.data)

// Onboarding
export const fetchOnboardingStatus = () =>
  api.get<ApiResponse<OnboardingStatus>>('/onboarding/status').then(r => r.data.data)

export const quickStart = () =>
  api.post<ApiResponse<{ project_id: number; agent_id: number }>>('/onboarding/quick-start').then(r => r.data.data)

// --- Custom Endpoints (E.4) ---

export const fetchCustomEndpoints = () =>
  api.get<ApiResponse<CustomEndpoint[]>>('/custom-endpoints').then((r) => r.data.data)

export const createCustomEndpoint = (data: { name: string; base_url: string; api_key?: string; models?: string[] }) =>
  api.post<ApiResponse<CustomEndpoint>>('/custom-endpoints', data).then((r) => r.data.data)

export const fetchCustomEndpoint = (id: number) =>
  api.get<ApiResponse<CustomEndpoint>>(`/custom-endpoints/${id}`).then((r) => r.data.data)

export const updateCustomEndpoint = (id: number, data: Partial<CustomEndpoint> & { api_key?: string }) =>
  api.put<ApiResponse<CustomEndpoint>>(`/custom-endpoints/${id}`, data).then((r) => r.data.data)

export const deleteCustomEndpoint = (id: number) =>
  api.delete(`/custom-endpoints/${id}`)

export const checkCustomEndpointHealth = (id: number) =>
  api.post(`/custom-endpoints/${id}/health`).then((r) => r.data)

export const discoverCustomEndpointModels = (id: number) =>
  api.post<ApiResponse<string[]>>(`/custom-endpoints/${id}/discover`).then((r) => r.data.data)

// --- Model Health (E.4) ---

export const fetchModelHealth = () =>
  api.get('/model-health').then((r) => {
    const d = r.data?.data
    if (Array.isArray(d)) return d as ModelHealthResult[]
    if (d && typeof d === 'object') {
      return Object.entries(d).map(([provider, result]) => ({ provider, ...(result as object) })) as ModelHealthResult[]
    }
    return [] as ModelHealthResult[]
  })

export const checkModelProviderHealth = (provider: string) =>
  api.get<ApiResponse<ModelHealthResult>>(`/model-health/${provider}`).then((r) => r.data.data)

export const benchmarkModel = (data: { model: string; prompt?: string }) =>
  api.post<ApiResponse<ModelBenchmarkResult>>('/model-health/benchmark', data).then((r) => r.data.data)

export const compareModels = (data: { models: string[]; prompt: string }) =>
  api.post<ApiResponse<ModelComparisonResult>>('/model-health/compare', data).then((r) => r.data.data)

// --- Local Models (E.4) ---

export const fetchLocalModels = () =>
  api.get<ApiResponse<LocalModel[]>>('/local-models').then((r) => r.data.data)

export const fetchOllamaModelDetail = (model: string) =>
  api.get<ApiResponse<OllamaModelDetail>>(`/local-models/ollama/${model}`).then((r) => r.data.data)

// --- Model Pull (G.3) ---

export const pullOllamaModel = async (
  model: string,
  onProgress: (p: ModelPullProgress) => void,
): Promise<void> => {
  // Ensure CSRF cookie is ready
  const baseUrl = import.meta.env.VITE_API_URL?.replace(/\/api$/, '') || ''
  await axios.get(`${baseUrl}/csrf-cookie`, { withCredentials: true })

  const apiBase = import.meta.env.VITE_API_URL || '/api'
  const response = await fetch(`${apiBase}/models/pull`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
    credentials: 'include',
    body: JSON.stringify({ model }),
  })

  if (!response.ok) {
    throw new Error(`Pull request failed: ${response.status}`)
  }

  const reader = response.body?.getReader()
  if (!reader) throw new Error('No response body')

  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    if (done) break

    buffer += decoder.decode(value, { stream: true })
    const lines = buffer.split('\n')
    buffer = lines.pop() || ''

    for (const line of lines) {
      const trimmed = line.trim()
      if (!trimmed.startsWith('data: ')) continue
      try {
        const data = JSON.parse(trimmed.slice(6)) as ModelPullProgress
        onProgress(data)
      } catch {
        // skip malformed lines
      }
    }
  }
}

export const deleteOllamaModel = (model: string) =>
  api.delete(`/models/${model}`)

export const fetchPullingModels = () =>
  api.get<ApiResponse<Array<{ model: string; started_at: string }>>>('/models/pulling').then((r) => r.data.data)

// --- Model Recommendations (G.3) ---

export const fetchModelRecommendations = (taskType: string) =>
  api.get<ApiResponse<ModelRecommendation[]>>(`/models/recommendations?task_type=${taskType}`).then((r) => r.data.data)

// --- OpenRouter (#300) ---

export const fetchOpenRouterModels = () =>
  api.get<ApiResponse<Array<{ id: string; name: string; context_length: number; pricing: { prompt: string; completion: string } }>>>('/models/openrouter').then((r) => r.data.data)

// --- Air-Gap (E.4) ---

export const fetchAirGapStatus = () =>
  api.get<ApiResponse<AirGapStatus>>('/air-gap').then((r) => r.data.data)

export const toggleAirGap = (enabled: boolean) =>
  api.post<ApiResponse<AirGapStatus>>('/air-gap', { enabled }).then((r) => r.data.data)

// --- API Tokens (E.5) ---

export const fetchApiTokens = () =>
  api.get<ApiResponse<ApiToken[]>>('/api-tokens').then((r) => r.data.data)

export const createApiToken = (data: { name: string; abilities?: string[]; expires_in_days?: number }) =>
  api.post<{ data: ApiTokenCreateResult; message: string }>('/api-tokens', data).then((r) => r.data)

export const deleteApiToken = (id: number) =>
  api.delete(`/api-tokens/${id}`)

// --- SDK Downloads (E.5) ---

export const downloadTypescriptSdk = () =>
  api.get('/sdk/typescript', { responseType: 'blob' }).then((r) => r.data as Blob)

export const downloadPhpSdk = () =>
  api.get('/sdk/php', { responseType: 'blob' }).then((r) => r.data as Blob)

export const downloadPythonSdk = () =>
  api.get('/sdk/python', { responseType: 'blob' }).then((r) => r.data as Blob)

// --- Guardrail Policies (E.3) ---

export const fetchGuardrailPolicies = (orgId: number) =>
  api.get<ApiResponse<GuardrailPolicy[]>>(`/organizations/${orgId}/guardrails`).then((r) => r.data.data)

export const createGuardrailPolicy = (orgId: number, data: Partial<GuardrailPolicy>) =>
  api.post<ApiResponse<GuardrailPolicy>>(`/organizations/${orgId}/guardrails`, data).then((r) => r.data.data)

export const updateGuardrailPolicy = (id: number, data: Partial<GuardrailPolicy>) =>
  api.put<ApiResponse<GuardrailPolicy>>(`/guardrails/${id}`, data).then((r) => r.data.data)

export const deleteGuardrailPolicy = (id: number) =>
  api.delete(`/guardrails/${id}`)

export const resolveGuardrailPolicies = (orgId: number, params?: { project_id?: number; agent_id?: number }) =>
  api.get(`/organizations/${orgId}/guardrails/resolve`, { params }).then((r) => r.data)

// --- Guardrail Profiles (E.3) ---

export const fetchGuardrailProfiles = () =>
  api.get<ApiResponse<GuardrailProfile[]>>('/guardrail-profiles').then((r) => r.data.data)

export const fetchGuardrailProfile = (id: number) =>
  api.get<ApiResponse<GuardrailProfile>>(`/guardrail-profiles/${id}`).then((r) => r.data.data)

export const createGuardrailProfile = (data: Partial<GuardrailProfile>) =>
  api.post<ApiResponse<GuardrailProfile>>('/guardrail-profiles', data).then((r) => r.data.data)

export const deleteGuardrailProfile = (id: number) =>
  api.delete(`/guardrail-profiles/${id}`)

// --- Guardrail Reports (E.3) ---

export const fetchGuardrailViolations = (orgId: number, params?: { guard_type?: string; severity?: string; page?: number }) =>
  api.get<{ data: GuardrailViolation[]; current_page: number; last_page: number; total: number }>(`/organizations/${orgId}/guardrail-reports`, { params }).then((r) => r.data)

export const fetchGuardrailTrends = (orgId: number, params?: { period?: string }) =>
  api.get<ApiResponse<GuardrailTrend[]>>(`/organizations/${orgId}/guardrail-reports/trends`, { params }).then((r) => r.data.data)

export const exportGuardrailReport = (orgId: number) =>
  api.get(`/organizations/${orgId}/guardrail-reports/export`, { responseType: 'blob' }).then((r) => r.data as Blob)

export const dismissGuardrailViolation = (violationId: number, reason: string) =>
  api.post(`/guardrail-violations/${violationId}/dismiss`, { reason }).then((r) => r.data)

// --- Security Scanner (E.3) ---

export const scanSkillSecurity = (skillId: number) =>
  api.post<ApiResponse<SecurityScanResult>>(`/skills/${skillId}/security-scan`).then((r) => r.data.data)

export const scanContentSecurity = (content: string) =>
  api.post<ApiResponse<SecurityScanResult>>('/security-scan', { content }).then((r) => r.data.data)

// --- Content Review (E.3) ---

export const reviewSkillContent = (skillId: number) =>
  api.post<ApiResponse<ContentReviewResult>>(`/skills/${skillId}/review`).then((r) => r.data.data)

export const reviewAgentContent = (agentId: number) =>
  api.post<ApiResponse<ContentReviewResult>>(`/agents/${agentId}/review`).then((r) => r.data.data)

// --- Endpoint Approvals (E.3) ---

export const fetchEndpointApprovals = (projectId: number) =>
  api.get<{ mcp_servers: Array<Record<string, unknown>>; a2a_agents: Array<Record<string, unknown>> }>(`/projects/${projectId}/endpoint-approvals`).then((r) => r.data)

export const approveEndpoint = (type: 'mcp' | 'a2a', id: number) =>
  api.post(`/endpoint-approvals/${type}/${id}/approve`).then((r) => r.data)

export const rejectEndpoint = (type: 'mcp' | 'a2a', id: number) =>
  api.post(`/endpoint-approvals/${type}/${id}/reject`).then((r) => r.data)

// --- Content Policies (E.1) ---

export const fetchContentPolicies = (orgId: number) =>
  api.get<ApiResponse<ContentPolicy[]>>(`/organizations/${orgId}/content-policies`).then((r) => r.data.data)

export const createContentPolicy = (orgId: number, data: Partial<ContentPolicy>) =>
  api.post<ApiResponse<ContentPolicy>>(`/organizations/${orgId}/content-policies`, data).then((r) => r.data.data)

export const updateContentPolicy = (id: number, data: Partial<ContentPolicy>) =>
  api.put<ApiResponse<ContentPolicy>>(`/content-policies/${id}`, data).then((r) => r.data.data)

export const deleteContentPolicy = (id: number) =>
  api.delete(`/content-policies/${id}`)

export const checkSkillPolicy = (policyId: number, skillId: number) =>
  api.post(`/content-policies/${policyId}/check/${skillId}`).then((r) => r.data)

// --- Activity Feed (E.1) ---

export const fetchActivityFeed = (orgId: number, params?: { page?: number }) =>
  api.get(`/organizations/${orgId}/activity-feed`, { params }).then((r) => r.data)

// --- SSO Providers (E.1) ---

export const fetchSsoProviders = (orgId: number) =>
  api.get<ApiResponse<SsoProvider[]>>(`/organizations/${orgId}/sso-providers`).then((r) => r.data.data)

export const createSsoProvider = (orgId: number, data: Partial<SsoProvider> & { client_secret?: string; certificate?: string }) =>
  api.post<ApiResponse<SsoProvider>>(`/organizations/${orgId}/sso-providers`, data).then((r) => r.data.data)

export const updateSsoProvider = (id: number, data: Partial<SsoProvider> & { client_secret?: string; certificate?: string }) =>
  api.put<ApiResponse<SsoProvider>>(`/sso-providers/${id}`, data).then((r) => r.data.data)

export const deleteSsoProvider = (id: number) =>
  api.delete(`/sso-providers/${id}`)

export const testSsoProvider = (id: number) =>
  api.post(`/sso-providers/${id}/test`).then((r) => r.data)

// --- Skill Reviews (E.6) ---

export const fetchSkillReviews = (skillId: number) =>
  api.get<ApiResponse<SkillReview[]>>(`/skills/${skillId}/reviews`).then((r) => r.data.data)

export const submitSkillReview = (skillId: number, data: { comments?: string }) =>
  api.post<ApiResponse<SkillReview>>(`/skills/${skillId}/reviews`, data).then((r) => r.data.data)

export const approveSkillReview = (reviewId: number, comments?: string) =>
  api.post(`/skill-reviews/${reviewId}/approve`, { comments }).then((r) => r.data)

export const rejectSkillReview = (reviewId: number, comments?: string) =>
  api.post(`/skill-reviews/${reviewId}/reject`, { comments }).then((r) => r.data)

// --- Skill Ownership (E.6) ---

export const fetchSkillOwnership = (skillId: number) =>
  api.get<ApiResponse<SkillOwnership>>(`/skills/${skillId}/ownership`).then((r) => r.data.data)

export const updateSkillOwnership = (skillId: number, data: { owner_id?: number | null; codeowners?: string[] }) =>
  api.put<ApiResponse<SkillOwnership>>(`/skills/${skillId}/ownership`, data).then((r) => r.data.data)

// --- Notifications (E.6) ---

export const fetchNotifications = (params?: { page?: number }) =>
  api.get<{ data: Notification[]; unread_count: number }>('/notifications', { params }).then((r) => r.data)

export const markNotificationRead = (id: number) =>
  api.post(`/notifications/${id}/read`).then((r) => r.data)

export const markAllNotificationsRead = () =>
  api.post('/notifications/read-all').then((r) => r.data)

// --- Skill Analytics (E.6) ---

export const fetchSkillAnalytics = (skillId: number) =>
  api.get<ApiResponse<SkillAnalytic[]>>(`/skills/${skillId}/analytics`).then((r) => r.data.data)

export const fetchTopSkills = (params?: { period?: string; limit?: number }) =>
  api.get<ApiResponse<Array<{ skill_id: number; skill_name: string; test_runs: number; pass_rate: number }>>>('/analytics/top-skills', { params }).then((r) => r.data.data)

export const fetchAnalyticsTrends = (params?: { period?: string }) =>
  api.get('/analytics/trends', { params }).then((r) => r.data)

// --- Skill Regression Tests (E.6) ---

export const fetchSkillTestCases = (skillId: number) =>
  api.get<ApiResponse<SkillTestCase[]>>(`/skills/${skillId}/test-cases`).then((r) => r.data.data)

export const createSkillTestCase = (skillId: number, data: Partial<SkillTestCase>) =>
  api.post<ApiResponse<SkillTestCase>>(`/skills/${skillId}/test-cases`, data).then((r) => r.data.data)

export const updateSkillTestCase = (id: number, data: Partial<SkillTestCase>) =>
  api.put<ApiResponse<SkillTestCase>>(`/skill-test-cases/${id}`, data).then((r) => r.data.data)

export const deleteSkillTestCase = (id: number) =>
  api.delete(`/skill-test-cases/${id}`)

export const runAllSkillTestCases = (skillId: number) =>
  api.post<ApiResponse<SkillTestCaseResult[]>>(`/skills/${skillId}/test-cases/run-all`).then((r) => r.data.data)

// --- Cross-Model Benchmark (E.6) ---

export const benchmarkSkill = (skillId: number, models?: string[]) =>
  api.post<ApiResponse<SkillBenchmarkResult[]>>(`/skills/${skillId}/benchmark`, { models }).then((r) => r.data.data)

// --- Skill Inheritance (E.6) ---

export const resolveSkillInheritance = (skillId: number) =>
  api.get<ApiResponse<SkillInheritanceInfo>>(`/skills/${skillId}/resolve`).then((r) => r.data.data)

export const fetchSkillChildren = (skillId: number) =>
  api.get<ApiResponse<Array<{ id: number; name: string; slug: string }>>>(`/skills/${skillId}/children`).then((r) => r.data.data)

export const updateSkillInheritance = (skillId: number, data: { extends_skill_id: number | null; override_sections?: Record<string, unknown> }) =>
  api.put(`/skills/${skillId}/inheritance`, data).then((r) => r.data)

// --- Reports (E.6) ---

export const exportSkillsReport = () =>
  api.get('/reports/skills', { responseType: 'blob' }).then((r) => r.data as Blob)

export const exportUsageReport = () =>
  api.get('/reports/usage', { responseType: 'blob' }).then((r) => r.data as Blob)

export const exportAuditReport = () =>
  api.get('/reports/audit', { responseType: 'blob' }).then((r) => r.data as Blob)

// --- GitHub Import (E.6) ---

export const discoverGitHubOrg = (org: string, token?: string) =>
  api.post<ApiResponse<GitHubDiscoveredRepo[]>>('/import/github/discover', { org, token }).then((r) => r.data.data)

export const importFromGitHub = (data: { org: string; repos: string[]; project_id: number; token?: string }) =>
  api.post<ApiResponse<GitHubImportResult>>('/import/github/import', data).then((r) => r.data.data)

// --- License (E.2) ---

export const fetchLicenseStatus = () =>
  api.get<ApiResponse<LicenseStatus>>('/license/status').then((r) => r.data.data)

export const activateLicense = (key: string) =>
  api.post<ApiResponse<LicenseStatus>>('/license/activate', { key }).then((r) => r.data.data)

// --- Setup Wizard (E.2) ---

export const fetchSetupStatus = () =>
  api.get<ApiResponse<SetupStatus>>('/setup/status').then((r) => r.data.data)

export const setupApiKeys = (data: Record<string, string>) =>
  api.post('/setup/api-keys', data).then((r) => r.data)

export const setupDefaultModel = (model: string) =>
  api.post('/setup/default-model', { model }).then((r) => r.data)

export const setupQuickStart = () =>
  api.post('/setup/quick-start').then((r) => r.data)

export const completeSetup = () =>
  api.post('/setup/complete').then((r) => r.data)

// --- Backups (E.2) ---

export const fetchBackups = () =>
  api.get<BackupEntry[]>('/backups').then((r) => {
    const d = r.data as BackupEntry[] | { data: BackupEntry[] }
    return Array.isArray(d) ? d : d.data
  })

export const createBackup = () =>
  api.post<ApiResponse<BackupEntry>>('/backups').then((r) => r.data.data)

export const restoreBackup = (filename: string) =>
  api.post('/backups/restore', { filename }).then((r) => r.data)

export const downloadBackup = (filename: string) =>
  api.get(`/backups/${filename}/download`, { responseType: 'blob' }).then((r) => r.data as Blob)

// --- Health Diagnostics (E.2) ---

export const fetchDiagnostics = () =>
  api.get('/diagnostics').then((r) => {
    const d = r.data as { checks?: Record<string, { status: string; message: string; latency_ms?: number | null }> }
    if (d.checks && !Array.isArray(d.checks)) {
      return Object.entries(d.checks).map(([name, check]) => ({ name, ...check })) as DiagnosticCheck[]
    }
    return (d.checks ?? []) as DiagnosticCheck[]
  })

export const fetchDiagnosticCheck = (check: string) =>
  api.get<ApiResponse<DiagnosticCheck>>(`/diagnostics/${check}`).then((r) => r.data.data)

// --- Audit Log Export ---

export const exportAuditLogs = () =>
  api.get('/audit-logs/export', { responseType: 'blob' }).then((r) => r.data as Blob)

// --- Execution Replay (G.4) ---

export const fetchExecutions = (params?: { project_id?: number; agent_id?: number; status?: string; page?: number }) =>
  api.get<{ data: ExecutionReplay[]; meta?: { current_page: number; last_page: number } }>('/executions', { params }).then((r) => r.data)

export const fetchExecution = (id: number) =>
  api.get<ApiResponse<ExecutionReplay>>(`/executions/${id}`).then((r) => r.data.data)

export const fetchExecutionSteps = (id: number) =>
  api.get<ApiResponse<ExecutionReplayStep[]>>(`/executions/${id}/steps`).then((r) => r.data.data)

export const diffExecutions = (id1: number, id2: number) =>
  api.get<ApiResponse<ExecutionDiff>>(`/executions/${id1}/diff/${id2}`).then((r) => r.data.data)

// --- Canvas Layout (#296) ---

export const fetchCanvasLayout = (projectId: number) =>
  api.get<ApiResponse<CanvasLayout | null>>(`/projects/${projectId}/canvas-layout`).then((r) => r.data.data)

export const saveCanvasLayout = (projectId: number, layout: Record<string, { x: number; y: number }>) =>
  api.put<ApiResponse<CanvasLayout>>(`/projects/${projectId}/canvas-layout`, { nodes: layout }).then((r) => r.data.data)

// --- MCP Server CRUD (L.1 #345) ---

export const fetchMcpServers = (projectId: number) =>
  api.get<{ mcp_servers: McpServerDetail[] }>(`/projects/${projectId}/mcp-servers`).then((r) => r.data.mcp_servers)

export const updateMcpServer = (id: number, data: Partial<McpServerDetail>) =>
  api.put<{ mcp_server: McpServerDetail }>(`/mcp-servers/${id}`, data).then((r) => r.data.mcp_server)

export const deleteMcpServer = (id: number) =>
  api.delete(`/mcp-servers/${id}`)

// --- A2A Agent CRUD (L.1 #346) ---

export const fetchA2aAgents = (projectId: number) =>
  api.get<{ a2a_agents: A2aAgentDetail[] }>(`/projects/${projectId}/a2a-agents`).then((r) => r.data.a2a_agents)

export const updateA2aAgent = (id: number, data: Partial<A2aAgentDetail>) =>
  api.put<{ a2a_agent: A2aAgentDetail }>(`/a2a-agents/${id}`, data).then((r) => r.data.a2a_agent)

export const deleteA2aAgent = (id: number) =>
  api.delete(`/a2a-agents/${id}`)

// --- Canvas Entity Creation (L.2 #348-#352) ---

export const quickCreateAgent = (projectId: number, data: { name: string; role?: string; model?: string }) =>
  api.post<ApiResponse<Agent>>(`/projects/${projectId}/agents/quick-create`, data).then((r) => r.data.data)

export const createMcpServer = (projectId: number, data: { name: string; transport: string; command?: string; url?: string }) =>
  api.post<{ mcp_server: McpServerDetail }>(`/projects/${projectId}/mcp-servers`, data).then((r) => r.data.mcp_server)

export const createA2aAgent = (projectId: number, data: { name: string; url: string; description?: string }) =>
  api.post<{ a2a_agent: A2aAgentDetail }>(`/projects/${projectId}/a2a-agents`, data).then((r) => r.data.a2a_agent)

// --- Delegation Config (L.1 #347) ---

export const saveDelegationConfigs = (projectId: number, configs: DelegationConfig[]) =>
  api.put(`/projects/${projectId}/delegation-configs`, { configs }).then((r) => r.data)

// --- Agent Tasks (M.4 #391-#396) ---

export const fetchProjectTasks = (projectId: number, params?: { status?: string; agent_id?: number; priority?: string }) =>
  api.get<ApiResponse<AgentTask[]>>(`/projects/${projectId}/tasks`, { params }).then((r) => r.data.data)

export const createProjectTask = (projectId: number, data: { title: string; description?: string; priority?: string; agent_id?: number | null }) =>
  api.post<ApiResponse<AgentTask>>(`/projects/${projectId}/tasks`, data).then((r) => r.data.data)

export const updateTask = (taskId: number, data: Partial<AgentTask>) =>
  api.put<ApiResponse<AgentTask>>(`/tasks/${taskId}`, data).then((r) => r.data.data)

export const assignTask = (taskId: number, agentId: number) =>
  api.post<ApiResponse<AgentTask>>(`/tasks/${taskId}/assign`, { agent_id: agentId }).then((r) => r.data.data)

export const runTask = (taskId: number) =>
  api.post<ApiResponse<AgentTask>>(`/tasks/${taskId}/run`).then((r) => r.data.data)

export const deleteTask = (taskId: number) =>
  api.delete(`/tasks/${taskId}`)

// --- Documents (N.3 #409) ---

export const fetchDocuments = (projectId: number, prefix?: string) =>
  api.get<ApiResponse<AgentDocument[]>>(`/projects/${projectId}/documents`, { params: { prefix } }).then((r) => r.data.data)

export const uploadDocument = (projectId: number, data: { path: string; content: string; agent_id?: number }) =>
  api.post<ApiResponse<{ path: string; size: number }>>(`/projects/${projectId}/documents`, data).then((r) => r.data.data)

export const uploadDocumentFile = (projectId: number, file: File, path?: string) => {
  const formData = new FormData()
  formData.append('file', file)
  if (path) formData.append('path', path)
  return api.post<ApiResponse<{ path: string; size: number }>>(`/projects/${projectId}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data.data)
}

export const downloadDocument = (projectId: number, path: string) =>
  api.get(`/projects/${projectId}/documents/download`, { params: { path }, responseType: 'blob' }).then((r) => r.data as Blob)

export const deleteDocument = (projectId: number, path: string, agentId?: number) =>
  api.delete(`/projects/${projectId}/documents`, { params: { path, agent_id: agentId } })

// --- Knowledge (N.4 #413) ---

export const fetchKnowledge = (projectId: number, agentId: number, namespace?: string) =>
  api.get<ApiResponse<AgentKnowledgeEntry[]>>(`/projects/${projectId}/agents/${agentId}/knowledge`, { params: { namespace } }).then((r) => r.data.data)

export const storeKnowledge = (projectId: number, agentId: number, data: { namespace: string; key: string; value: unknown }) =>
  api.post<ApiResponse<AgentKnowledgeEntry>>(`/projects/${projectId}/agents/${agentId}/knowledge`, data).then((r) => r.data.data)

export const searchKnowledge = (projectId: number, agentId: number, query: string) =>
  api.get<ApiResponse<AgentKnowledgeEntry[]>>(`/projects/${projectId}/agents/${agentId}/knowledge/search`, { params: { q: query } }).then((r) => r.data.data)

export const deleteKnowledge = (id: number) =>
  api.delete(`/agent-knowledge/${id}`)

// --- Agent Long-Term Memory (N.2 #402-#407) ---

export const fetchAgentMemories = (projectId: number, agentId: number, perPage = 20, page = 1) =>
  api.get<{ data: AgentMemoryEntry[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>(
    `/projects/${projectId}/agents/${agentId}/memories`,
    { params: { per_page: perPage, page } },
  ).then((r) => r.data)

export const createAgentMemory = (projectId: number, agentId: number, data: { key: string; content: string; metadata?: Record<string, unknown> }) =>
  api.post<{ data: AgentMemoryEntry }>(`/projects/${projectId}/agents/${agentId}/memories`, data).then((r) => r.data.data)

export const recallAgentMemories = (projectId: number, agentId: number, query: string, limit = 5) =>
  api.get<AgentMemoryRecallResult>(`/projects/${projectId}/agents/${agentId}/memories/recall`, { params: { q: query, limit } }).then((r) => r.data.data)

export const updateAgentMemory = (id: number, content: string) =>
  api.put<{ data: AgentMemoryEntry }>(`/agent-memories/${id}`, { content }).then((r) => r.data.data)

export const deleteAgentMemory = (id: number) =>
  api.delete(`/agent-memories/${id}`)

// --- Data Sources (N.5 #422-#425) ---

export const fetchDataSources = (projectId: number) =>
  api.get<ApiResponse<DataSource[]>>(`/projects/${projectId}/data-sources`).then((r) => r.data.data)

export const createDataSource = (projectId: number, data: { name: string; type: string; connection_config?: Record<string, unknown>; access_mode?: string; enabled?: boolean }) =>
  api.post<ApiResponse<DataSource>>(`/projects/${projectId}/data-sources`, data).then((r) => r.data.data)

export const updateDataSource = (id: number, data: Partial<{ name: string; type: string; connection_config: Record<string, unknown>; access_mode: string; enabled: boolean }>) =>
  api.put<ApiResponse<DataSource>>(`/data-sources/${id}`, data).then((r) => r.data.data)

export const deleteDataSource = (id: number) =>
  api.delete(`/data-sources/${id}`)

export const testDataSource = (id: number) =>
  api.post<ApiResponse<DataSourceTestResult>>(`/data-sources/${id}/test`).then((r) => r.data.data)

export const bindAgentDataSources = (projectId: number, agentId: number, dataSourceIds: number[]) =>
  api.put(`/projects/${projectId}/agents/${agentId}/data-sources`, { data_source_ids: dataSourceIds })

// ─── R.1: Control Plane ──────────────────────────────────

export const fetchControlPlaneSessions = () =>
  api.get<ApiResponse<ControlPlaneSession[]>>('/control-plane/sessions').then((r) => r.data.data)

export const createControlPlaneSession = (data?: { title?: string }) =>
  api.post<ApiResponse<ControlPlaneSession>>('/control-plane/sessions', data ?? {}).then((r) => r.data.data)

export const fetchControlPlaneSession = (id: number) =>
  api.get<ApiResponse<ControlPlaneSession>>(`/control-plane/sessions/${id}`).then((r) => r.data.data)

export const deleteControlPlaneSession = (id: number) =>
  api.delete(`/control-plane/sessions/${id}`)

export const controlPlaneQuick = (message: string) =>
  api.post<ApiResponse<{ response: string }>>('/control-plane/quick', { message }).then((r) => r.data.data)

// Chat uses SSE streaming — use EventSource directly in the component

// ─── R.2: Plugins ──────────────────────────────────

export const fetchPlugins = (type?: string) =>
  api.get<ApiResponse<Plugin[]>>('/plugins', { params: { type } }).then((r) => r.data.data)

export const installPlugin = (manifest: Record<string, unknown>) =>
  api.post<ApiResponse<Plugin>>('/plugins', { manifest }).then((r) => r.data.data)

export const fetchPlugin = (id: number) =>
  api.get<ApiResponse<Plugin>>(`/plugins/${id}`).then((r) => r.data.data)

export const updatePluginConfig = (id: number, config: Record<string, unknown>) =>
  api.put<ApiResponse<Plugin>>(`/plugins/${id}`, { config }).then((r) => r.data.data)

export const enablePlugin = (id: number) =>
  api.post<ApiResponse<Plugin>>(`/plugins/${id}/enable`).then((r) => r.data.data)

export const disablePlugin = (id: number) =>
  api.post<ApiResponse<Plugin>>(`/plugins/${id}/disable`).then((r) => r.data.data)

export const uninstallPlugin = (id: number) =>
  api.delete(`/plugins/${id}`)

export const fetchPluginHookPoints = () =>
  api.get<ApiResponse<string[]>>('/plugins/hooks').then((r) => r.data.data)

export const fetchPluginTools = () =>
  api.get<ApiResponse<Array<{ plugin: string; tools: Array<{ name: string; description: string }> }>>>('/plugins/available-tools').then((r) => r.data.data)

// ─── R.3: Agent Templates Marketplace ──────────────────────────────────

export const fetchMarketplaceAgents = (params?: { category?: string; q?: string; sort?: string }) =>
  api.get<ApiResponse<MarketplaceAgent[]>>('/marketplace/agents', { params }).then((r) => r.data.data)

export const fetchMarketplaceAgent = (id: number) =>
  api.get<ApiResponse<MarketplaceAgent>>(`/marketplace/agents/${id}`).then((r) => r.data.data)

export const publishMarketplaceAgent = (data: { agent_id: number; project_id: number; description: string; category?: string; tags?: string[]; readme?: string }) =>
  api.post<ApiResponse<MarketplaceAgent>>('/marketplace/agents/publish', data).then((r) => r.data.data)

export const installMarketplaceAgent = (id: number, projectId: number) =>
  api.post<ApiResponse<{ agent_id: number }>>(`/marketplace/agents/${id}/install`, { project_id: projectId }).then((r) => r.data.data)

export const voteMarketplaceAgent = (id: number, direction: 'up' | 'down') =>
  api.post(`/marketplace/agents/${id}/vote`, { direction })

export const previewMarketplaceAgent = (id: number) =>
  api.get<ApiResponse<{ agent: Record<string, unknown>; skills: Array<{ name: string; description: string }>; tool_count: number }>>(`/marketplace/agents/${id}/preview`).then((r) => r.data.data)

// ─── R.4: Shared Memory ──────────────────────────────────

export const fetchSharedPools = (projectId: number) =>
  api.get<ApiResponse<SharedMemoryPool[]>>(`/projects/${projectId}/shared-pools`).then((r) => r.data.data)

export const createSharedPool = (projectId: number, data: { name: string; description?: string; access_policy?: string; retention_days?: number }) =>
  api.post<ApiResponse<SharedMemoryPool>>(`/projects/${projectId}/shared-pools`, data).then((r) => r.data.data)

export const fetchSharedPool = (id: number) =>
  api.get<ApiResponse<SharedMemoryPool>>(`/shared-pools/${id}`).then((r) => r.data.data)

export const updateSharedPool = (id: number, data: Partial<{ name: string; description: string; access_policy: string; retention_days: number }>) =>
  api.put<ApiResponse<SharedMemoryPool>>(`/shared-pools/${id}`, data).then((r) => r.data.data)

export const deleteSharedPool = (id: number) =>
  api.delete(`/shared-pools/${id}`)

export const addAgentToPool = (poolId: number, agentId: number, accessMode: string = 'write') =>
  api.post(`/shared-pools/${poolId}/agents`, { agent_id: agentId, access_mode: accessMode })

export const removeAgentFromPool = (poolId: number, agentId: number) =>
  api.delete(`/shared-pools/${poolId}/agents/${agentId}`)

export const fetchPoolEntries = (poolId: number, params?: { q?: string; page?: number }) =>
  api.get<ApiResponse<SharedMemoryEntry[]>>(`/shared-pools/${poolId}/entries`, { params }).then((r) => r.data.data)

export const createPoolEntry = (poolId: number, data: { key: string; content: Record<string, unknown>; tags?: string[]; confidence?: number }) =>
  api.post<ApiResponse<SharedMemoryEntry>>(`/shared-pools/${poolId}/entries`, data).then((r) => r.data.data)

export const recallFromPool = (poolId: number, query: string, limit: number = 10) =>
  api.get<ApiResponse<SharedMemoryEntry[]>>(`/shared-pools/${poolId}/recall`, { params: { q: query, limit } }).then((r) => r.data.data)

export const fetchPoolContributors = (poolId: number) =>
  api.get<ApiResponse<Array<{ agent_id: number; agent_name: string; count: number; last_at: string }>>>(`/shared-pools/${poolId}/contributors`).then((r) => r.data.data)

export const fetchKnowledgeGraph = (projectId: number) =>
  api.get<ApiResponse<KnowledgeGraph>>(`/projects/${projectId}/knowledge-graph`).then((r) => r.data.data)

export const createGraphNode = (projectId: number, data: { entity_type: string; entity_name: string; properties?: Record<string, unknown> }) =>
  api.post<ApiResponse<KnowledgeGraphNode>>(`/projects/${projectId}/knowledge-graph/nodes`, data).then((r) => r.data.data)

export const createGraphEdge = (projectId: number, data: { source_node_id: number; target_node_id: number; relationship: string; weight?: number }) =>
  api.post<ApiResponse<KnowledgeGraphEdge>>(`/projects/${projectId}/knowledge-graph/edges`, data).then((r) => r.data.data)

export const queryKnowledgeGraph = (projectId: number, params: { entity_name?: string; entity_type?: string; hops?: number }) =>
  api.get<ApiResponse<KnowledgeGraph>>(`/projects/${projectId}/knowledge-graph/query`, { params }).then((r) => r.data.data)

export const deleteGraphNode = (nodeId: number) =>
  api.delete(`/knowledge-graph-nodes/${nodeId}`)

// ─── R.5: Smart Routing ──────────────────────────────────

export const fetchRoutingRules = (projectId: number) =>
  api.get<ApiResponse<RoutingRule[]>>(`/projects/${projectId}/routing-rules`).then((r) => r.data.data)

export const createRoutingRule = (projectId: number, data: Partial<RoutingRule>) =>
  api.post<ApiResponse<RoutingRule>>(`/projects/${projectId}/routing-rules`, data).then((r) => r.data.data)

export const updateRoutingRule = (id: number, data: Partial<RoutingRule>) =>
  api.put<ApiResponse<RoutingRule>>(`/routing-rules/${id}`, data).then((r) => r.data.data)

export const deleteRoutingRule = (id: number) =>
  api.delete(`/routing-rules/${id}`)

export const fetchProjectCapabilities = (projectId: number) =>
  api.get<ApiResponse<AgentCapability[]>>(`/projects/${projectId}/agent-capabilities`).then((r) => r.data.data)

export const fetchAgentCapabilities = (agentId: number) =>
  api.get<ApiResponse<AgentCapability[]>>(`/agents/${agentId}/capabilities`).then((r) => r.data.data)

export const inferAgentCapabilities = (agentId: number, projectId: number) =>
  api.post<ApiResponse<AgentCapability[]>>(`/agents/${agentId}/capabilities/infer`, { project_id: projectId }).then((r) => r.data.data)

export const fetchRoutingDecisions = (projectId: number, params?: { page?: number }) =>
  api.get<ApiResponse<RoutingDecision[]>>(`/projects/${projectId}/routing-decisions`, { params }).then((r) => r.data.data)

export const simulateRouting = (projectId: number, data: { description: string; task_type?: string; priority?: number }) =>
  api.post<ApiResponse<RoutingSimulationResult>>(`/projects/${projectId}/routing-rules/simulate`, data).then((r) => r.data.data)

// ─── S.1: Collaboration ──────────────────────────────────

export const sendPresenceHeartbeat = (data: { resource_type: string; resource_id: number; cursor_position?: { line: number; column: number }; selection?: Record<string, unknown> }) =>
  api.post<ApiResponse<PresenceSession>>('/collaboration/heartbeat', data).then((r) => r.data.data)

export const fetchPresence = (type: string, id: number) =>
  api.get<ApiResponse<PresenceSession[]>>(`/collaboration/presence/${type}/${id}`).then((r) => r.data.data)

export const fetchComments = (type: string, id: number) =>
  api.get<ApiResponse<CollaborationComment[]>>(`/collaboration/${type}/${id}/comments`).then((r) => r.data.data)

export const createComment = (type: string, id: number, data: { body: string; line_number?: number; thread_id?: number }) =>
  api.post<ApiResponse<CollaborationComment>>(`/collaboration/${type}/${id}/comments`, data).then((r) => r.data.data)

export const resolveComment = (commentId: number) =>
  api.post(`/collaboration/comments/${commentId}/resolve`)

export const deleteComment = (commentId: number) =>
  api.delete(`/collaboration/comments/${commentId}`)

export const fetchDebugSessions = (projectId: number) =>
  api.get<ApiResponse<DebugSession[]>>(`/projects/${projectId}/debug-sessions`).then((r) => r.data.data)

export const createDebugSession = (projectId: number, data: { title: string; execution_run_id?: number }) =>
  api.post<ApiResponse<DebugSession>>(`/projects/${projectId}/debug-sessions`, data).then((r) => r.data.data)

export const joinDebugSession = (sessionId: number) =>
  api.post(`/debug-sessions/${sessionId}/join`)

export const endDebugSession = (sessionId: number) =>
  api.post(`/debug-sessions/${sessionId}/end`)

// ─── S.2: Mobile ──────────────────────────────────

export const subscribePush = (data: { endpoint: string; p256dh_key: string; auth_key: string }) =>
  api.post('/push/subscribe', data)

export const unsubscribePush = (endpoint: string) =>
  api.delete('/push/unsubscribe', { data: { endpoint } })

export const emergencyKill = (projectId: number) =>
  api.post<ApiResponse<{ killed_count: number }>>(`/projects/${projectId}/emergency-kill`).then((r) => r.data.data)

export const fetchMobileOverview = () =>
  api.get<ApiResponse<MobileOverview>>('/mobile/overview').then((r) => r.data.data)

export const fetchMobilePendingApprovals = () =>
  api.get<ApiResponse<Array<{ id: number; agent_name: string; action: string; project_name: string; created_at: string }>>>('/mobile/pending-approvals').then((r) => r.data.data)

// ─── S.3: Observability ──────────────────────────────────

export const fetchCustomMetrics = () =>
  api.get<ApiResponse<CustomMetric[]>>('/observability/metrics').then((r) => r.data.data)

export const createCustomMetric = (data: Partial<CustomMetric>) =>
  api.post<ApiResponse<CustomMetric>>('/observability/metrics', data).then((r) => r.data.data)

export const updateCustomMetric = (id: number, data: Partial<CustomMetric>) =>
  api.put<ApiResponse<CustomMetric>>(`/observability/metrics/${id}`, data).then((r) => r.data.data)

export const deleteCustomMetric = (id: number) =>
  api.delete(`/observability/metrics/${id}`)

export const evaluateMetric = (id: number, params?: { from?: string; to?: string }) =>
  api.get<ApiResponse<MetricDataPoint[]>>(`/observability/metrics/${id}/evaluate`, { params }).then((r) => r.data.data)

export const fetchAlertRules = () =>
  api.get<ApiResponse<AlertRule[]>>('/observability/alert-rules').then((r) => r.data.data)

export const createAlertRule = (data: Partial<AlertRule>) =>
  api.post<ApiResponse<AlertRule>>('/observability/alert-rules', data).then((r) => r.data.data)

export const updateAlertRule = (id: number, data: Partial<AlertRule>) =>
  api.put<ApiResponse<AlertRule>>(`/observability/alert-rules/${id}`, data).then((r) => r.data.data)

export const deleteAlertRule = (id: number) =>
  api.delete(`/observability/alert-rules/${id}`)

export const fetchAlertIncidents = (params?: { page?: number }) =>
  api.get<ApiResponse<AlertIncident[]>>('/observability/incidents', { params }).then((r) => r.data.data)

export const acknowledgeIncident = (id: number) =>
  api.post(`/observability/incidents/${id}/acknowledge`)

export const resolveIncident = (id: number) =>
  api.post(`/observability/incidents/${id}/resolve`)

export const fetchDashboardLayouts = () =>
  api.get<ApiResponse<DashboardLayout[]>>('/observability/dashboards').then((r) => r.data.data)

export const createDashboardLayout = (data: { name: string; layout: DashboardLayout['layout'] }) =>
  api.post<ApiResponse<DashboardLayout>>('/observability/dashboards', data).then((r) => r.data.data)

export const updateDashboardLayout = (id: number, data: Partial<{ name: string; layout: DashboardLayout['layout'] }>) =>
  api.put<ApiResponse<DashboardLayout>>(`/observability/dashboards/${id}`, data).then((r) => r.data.data)

export const deleteDashboardLayout = (id: number) =>
  api.delete(`/observability/dashboards/${id}`)

export const fetchCostForecast = () =>
  api.get<ApiResponse<CostForecast>>('/observability/cost-forecast').then((r) => r.data.data)

// ─── S.4: Negotiation ──────────────────────────────────

export const openBidding = (taskId: number, projectId: number) =>
  api.post<ApiResponse<TaskBid[]>>(`/tasks/${taskId}/open-bidding`, { project_id: projectId }).then((r) => r.data.data)

export const fetchTaskBids = (taskId: number) =>
  api.get<ApiResponse<TaskBid[]>>(`/tasks/${taskId}/bids`).then((r) => r.data.data)

export const acceptBid = (bidId: number) =>
  api.post(`/bids/${bidId}/accept`)

export const fetchAdvertisements = (projectId: number) =>
  api.get<ApiResponse<CapabilityAdvertisement[]>>(`/projects/${projectId}/advertisements`).then((r) => r.data.data)

export const refreshAdvertisements = (projectId: number) =>
  api.post<ApiResponse<{ count: number }>>(`/projects/${projectId}/advertisements/refresh`).then((r) => r.data.data)

export const fetchTeamFormations = (projectId: number) =>
  api.get<ApiResponse<TeamFormation[]>>(`/projects/${projectId}/team-formations`).then((r) => r.data.data)

export const formTeam = (projectId: number, data: { name: string; objective: string; formation_strategy?: string }) =>
  api.post<ApiResponse<TeamFormation>>(`/projects/${projectId}/team-formations`, data).then((r) => r.data.data)

export const disbandTeam = (formationId: number) =>
  api.post(`/team-formations/${formationId}/disband`)

export const fetchNegotiationLog = (projectId: number, params?: { page?: number }) =>
  api.get<ApiResponse<NegotiationLog[]>>(`/projects/${projectId}/negotiation-log`, { params }).then((r) => r.data.data)

// ─── S.5: Federation ──────────────────────────────────

export const fetchFederationPeers = () =>
  api.get<ApiResponse<FederationPeer[]>>('/federation/peers').then((r) => r.data.data)

export const registerFederationPeer = (data: { name: string; base_url: string; api_key: string }) =>
  api.post<ApiResponse<FederationPeer>>('/federation/peers', data).then((r) => r.data.data)

export const updateFederationPeer = (id: number, data: Partial<{ name: string; trust_level: string; status: string }>) =>
  api.put<ApiResponse<FederationPeer>>(`/federation/peers/${id}`, data).then((r) => r.data.data)

export const removeFederationPeer = (id: number) =>
  api.delete(`/federation/peers/${id}`)

export const federationHeartbeat = (id: number) =>
  api.post<ApiResponse<{ success: boolean }>>(`/federation/peers/${id}/heartbeat`).then((r) => r.data.data)

export const fetchPeerCapabilities = (id: number) =>
  api.get<ApiResponse<Record<string, unknown>>>(`/federation/peers/${id}/capabilities`).then((r) => r.data.data)

export const federationDelegate = (peerId: number, data: { agent_id: number; remote_agent_slug: string; input: Record<string, unknown> }) =>
  api.post<ApiResponse<FederationDelegation>>(`/federation/peers/${peerId}/delegate`, data).then((r) => r.data.data)

export const fetchFederationDelegations = () =>
  api.get<ApiResponse<FederationDelegation[]>>('/federation/delegations').then((r) => r.data.data)

export const fetchDelegationStatus = (id: number) =>
  api.get<ApiResponse<FederationDelegation>>(`/federation/delegations/${id}`).then((r) => r.data.data)

export const fetchFederatedIdentities = () =>
  api.get<ApiResponse<FederatedIdentity[]>>('/federation/identities').then((r) => r.data.data)

export const linkFederatedIdentity = (peerId: number, data: { remote_user_id: string; remote_email?: string }) =>
  api.post<ApiResponse<FederatedIdentity>>(`/federation/peers/${peerId}/link-identity`, data).then((r) => r.data.data)

export default api
