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
  ApiResponse,
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

export default api
