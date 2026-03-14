export interface Project {
  id: number
  uuid: string
  name: string
  description: string | null
  path: string
  providers: string[]
  git_auto_commit: boolean
  skills_count: number
  synced_at: string | null
  created_at: string
  updated_at: string
}

export interface TemplateVariable {
  name: string
  description: string
  default?: string
}

export interface SkillVariableValue {
  key: string
  value: string | null
}

export interface Skill {
  id: number
  uuid: string
  project_id: number
  slug: string
  name: string
  description: string | null
  model: string | null
  max_tokens: number | null
  tools: string[]
  includes: string[]
  conditions: SkillConditions | null
  template_variables: TemplateVariable[] | null
  body: string
  resolved_body: string
  tags: string[]
  token_estimate: number
  project?: Project
  created_at: string
  updated_at: string
}

export interface GeneratedSkill {
  name: string
  description: string
  model: string | null
  max_tokens: number | null
  tags: string[]
  body: string
}

export interface SkillVersion {
  id: number
  skill_id: number
  version_number: number
  frontmatter: Record<string, unknown>
  body: string
  note: string | null
  saved_at: string
}

export interface Tag {
  id: number
  name: string
  color: string
  skills_count?: number
}

export interface LibrarySkill {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  category: string | null
  tags: string[]
  frontmatter: Record<string, unknown>
  body: string
  source: string | null
  created_at: string
}

export interface Agent {
  id: number
  uuid: string
  name: string
  slug: string
  role: string
  description: string | null
  base_instructions: string
  persona_prompt: string | null
  model: string | null
  fallback_models: string[] | null
  routing_strategy: string
  icon: string | null
  sort_order: number

  // Goal
  objective_template: string | null
  success_criteria: string[] | null
  max_iterations: number | null
  timeout_seconds: number | null

  // Perception
  input_schema: Record<string, unknown> | null
  memory_sources: string[] | null
  context_strategy: string

  // Reasoning
  planning_mode: string
  temperature: number | null
  system_prompt: string | null

  // Observation
  eval_criteria: string[] | null
  output_schema: Record<string, unknown> | null
  loop_condition: string

  // Orchestration
  parent_agent_id: number | null
  delegation_rules: Record<string, unknown> | null
  can_delegate: boolean

  // Autonomy & Permissions
  autonomy_level?: 'supervised' | 'semi_autonomous' | 'autonomous'
  budget_limit_usd?: number | null
  daily_budget_limit_usd?: number | null
  allowed_tools?: string[] | null
  blocked_tools?: string[] | null
  data_access_scope?: Record<string, unknown> | null

  // Actions
  custom_tools: Record<string, unknown>[] | null

  // Meta
  is_template: boolean
  created_by: number | null
  has_loop_config: boolean

  created_at: string
  updated_at: string
}

export interface ProjectAgent extends Agent {
  is_enabled: boolean
  custom_instructions: string | null
  skill_ids: number[]
  mcp_server_ids: number[]
  a2a_agent_ids: number[]

  // Override fields
  objective_override?: string | null
  success_criteria_override?: string[] | null
  max_iterations_override?: number | null
  timeout_override?: number | null
  model_override?: string | null
  temperature_override?: number | null
  context_strategy_override?: string | null
  planning_mode_override?: string | null
  custom_tools_override?: Record<string, unknown>[] | null
}

export interface SkillsShDiscoveredSkill {
  path: string
  name: string
}

export interface SkillsShSkillDetail {
  path: string
  name: string
  description: string | null
  body: string
  frontmatter: Record<string, unknown>
}

export interface AgentComposed {
  content: string
  token_estimate: number
  agent: {
    id: number
    name: string
    slug: string
    role: string
    icon: string | null
  }
  skill_count: number
}

export interface AgentStructured {
  agent: {
    id: number
    uuid: string
    name: string
    slug: string
    role: string
    icon: string | null
    description: string | null
  }
  system_prompt: string
  token_estimate: number
  model: string | null
  temperature: number | null
  goal: {
    objective: string | null
    success_criteria: string[] | null
    max_iterations: number | null
    timeout_seconds: number | null
    loop_condition: string
  }
  perception: {
    input_schema: Record<string, unknown> | null
    memory_sources: string[] | null
    context_strategy: string
  }
  reasoning: {
    planning_mode: string
    persona_prompt: string | null
  }
  tools: {
    mcp_servers: Array<{ name: string; transport: string; command: string | null; url: string | null }>
    a2a_agents: Array<{ name: string; url: string; description: string | null }>
    custom_tools: Record<string, unknown>[] | null
  }
  skills: Array<{
    id: number
    slug: string
    name: string
    description: string | null
    model: string | null
    body: string
    token_estimate: number
  }>
  skill_count: number
  observation: {
    eval_criteria: string[] | null
    output_schema: Record<string, unknown> | null
  }
  orchestration: {
    can_delegate: boolean
    delegation_rules: Record<string, unknown> | null
    parent_agent_id: number | null
    parent_agent: { id: number; name: string; slug: string } | null
  }
}

export interface LintIssue {
  severity: 'warning' | 'suggestion'
  rule: string
  message: string
  suggestion: string
  line: number | null
}

export interface GitLogEntry {
  hash: string
  short_hash: string
  message: string
  author: string
  date: string
  branch: string | null
}

export interface BundlePreviewItem {
  slug: string
  name: string
  description: string | null
  tags?: string[]
  role?: string
}

export interface BundlePreview {
  metadata: Record<string, unknown>
  skills: BundlePreviewItem[]
  agents: BundlePreviewItem[]
}

export interface BundleImportResult {
  imported: number
  skipped: number
  errors: string[]
}

export interface SyncPreviewFile {
  path: string
  provider: string
  current: string | null
  proposed: string
  status: 'added' | 'modified' | 'unchanged' | 'deleted'
}

export interface MarketplaceSkill {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  category: string | null
  tags: string[]
  frontmatter: Record<string, unknown>
  body: string
  author: string | null
  source: string | null
  downloads: number
  upvotes: number
  downvotes: number
  version: string
  created_at: string
  updated_at: string
}

export interface Webhook {
  id: number
  project_id: number
  event: string
  url: string
  secret: string | null
  is_active: boolean
  last_triggered_at: string | null
  last_status: number | null
  created_at: string
  updated_at: string
}

export interface WebhookDelivery {
  id: number
  webhook_id: number
  event: string
  payload: Record<string, unknown>
  response_status: number | null
  response_body: string | null
  duration_ms: number | null
  created_at: string
}

export interface ModelInfo {
  id: string
  name: string
  provider: string
  context_window: number
}

export interface ModelGroup {
  provider: string
  label: string
  configured: boolean
  models: ModelInfo[]
}

export interface ProjectRepository {
  id: number
  provider: 'github' | 'gitlab'
  owner: string
  name: string
  full_name: string
  default_branch: string
  url: string
  has_access_token: boolean
  auto_scan_on_push: boolean
  auto_sync_on_push: boolean
  last_synced_at: string | null
  last_commit_sha: string | null
  created_at: string
}

export interface RepositoryStatus {
  connected: boolean
  accessible: boolean
  reason?: string
  default_branch?: string
  visibility?: string
  last_push?: string
  open_issues?: number
}

export interface RepositoryFile {
  path: string
  size: number | null
  sha: string | null
}

export interface SkillConditions {
  file_patterns?: string[]
  path_prefixes?: string[]
}

export interface ImportDetectedSkill {
  name: string
  slug: string
  description: string | null
  body_length: number
  tags: string[]
}

export interface ImportResult {
  created: number
  skipped: number
}

export interface BillingPlan {
  slug: string
  name: string
  description: string | null
  price_monthly: string
  price_yearly: string
  price_monthly_cents: number
  price_yearly_cents: number
  limits: {
    max_projects: number
    max_skills_per_project: number
    max_providers: number
    max_members: number
    included_tokens_monthly: number
  }
  features: {
    marketplace_publish: boolean
    ai_generation: boolean
    webhook_access: boolean
    bundle_export: boolean
    repository_access: boolean
    priority_support: boolean
  }
}

export interface BillingStatus {
  plan: {
    slug: string
    name: string
    price_monthly: string
    price_yearly: string
  }
  subscription: {
    status: string
    trial_ends_at: string | null
    ends_at: string | null
    on_grace_period: boolean
    cancelled: boolean
  } | null
  usage: {
    tokens_used: number
    tokens_included: number
    tokens_remaining: number
    overage_rate: number
  }
  payment_method: {
    type: string
    last_four: string
  } | null
  has_stripe_id: boolean
}

export interface UsageSummary {
  summary: {
    period_start: string
    period_end: string
    llm_tokens: {
      used: number
      included: number
      requests: number
    }
    sync_operations: { count: number }
    api_calls: { count: number }
  }
  daily_tokens: Array<{ date: string; tokens: number }>
}

export interface ProjectGraphData {
  project: {
    id: number
    name: string
    synced_at: string | null
  }
  skills: Array<{
    id: number
    slug: string
    name: string
    description: string | null
    model: string | null
    includes: string[]
    conditions: SkillConditions | null
    template_variables: TemplateVariable[] | null
    tags: string[]
    token_estimate: number
  }>
  skill_edges: Array<{
    source: number
    target: number
    type: string
  }>
  circular_deps: string[]
  agents: Array<{
    id: number
    name: string
    slug: string
    role: string
    icon: string | null
    is_enabled: boolean
    has_custom_instructions: boolean
    skill_ids: number[]
    mcp_server_ids: number[]
    a2a_agent_ids: number[]
    model: string | null
    planning_mode: string
    context_strategy: string
    loop_condition: string
    max_iterations: number | null
    objective_template: string | null
    can_delegate: boolean
    has_loop_config: boolean
    parent_agent_id: number | null
  }>
  agent_edges: Array<{
    source_type: string
    source: number
    target_type: string
    target: number
    type: string
  }>
  providers: Array<{
    slug: string
    name: string
  }>
  mcp_servers: Array<{
    id: number
    name: string
    transport: string
  }>
  a2a_agents: Array<{
    id: number
    name: string
    url: string
  }>
  sync_outputs: Record<string, string[]>
}

export interface Workflow {
  id: number
  uuid: string
  project_id: number
  name: string
  slug: string
  description: string | null
  trigger_type: string
  trigger_config: Record<string, unknown> | null
  entry_step_id: number | null
  status: string
  context_schema: Record<string, unknown> | null
  termination_policy: Record<string, unknown> | null
  config: Record<string, unknown> | null
  created_by: number | null
  is_active: boolean
  is_draft: boolean
  step_count: number | null
  edge_count: number | null
  steps?: WorkflowStep[]
  edges?: WorkflowEdge[]
  created_at: string
  updated_at: string
}

export interface WorkflowStep {
  id: number
  uuid: string
  workflow_id: number
  agent_id: number | null
  type: string
  name: string
  position_x: number
  position_y: number
  config: Record<string, unknown> | null
  model_override: string | null
  sort_order: number
  is_agent: boolean
  is_checkpoint: boolean
  is_condition: boolean
  is_terminal: boolean
  requires_agent: boolean
  agent?: Agent
  created_at: string
  updated_at: string
}

export interface WorkflowEdge {
  id: number
  workflow_id: number
  source_step_id: number
  target_step_id: number
  condition_expression: string | null
  label: string | null
  priority: number
  has_condition: boolean
  created_at: string
  updated_at: string
}

export interface WorkflowVersion {
  id: number
  workflow_id: number
  version_number: number
  note: string | null
  created_at: string
}

export interface WorkflowValidation {
  valid: boolean
  errors: string[]
  warnings: string[]
}

// --- Execution ---

export interface ExecutionRun {
  id: number
  uuid: string
  project_id: number
  agent_id: number
  agent?: {
    id: number
    name: string
    slug: string
    icon: string | null
  }
  status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled' | 'awaiting_approval'
  approval_required?: boolean
  approved_by?: number | null
  approved_at?: string | null
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  config: Record<string, unknown> | null
  error: string | null
  started_at: string | null
  completed_at: string | null
  total_tokens: number
  total_cost_microcents: number
  total_duration_ms: number
  model_used?: string | null
  steps?: ExecutionStep[]
  steps_count?: number
  created_at: string
}

export interface ExecutionStep {
  id: number
  uuid: string
  step_number: number
  phase: 'perceive' | 'reason' | 'act' | 'observe'
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  tool_calls: ToolCallData[] | null
  token_usage: TokenUsage | null
  duration_ms: number
  status: 'pending' | 'running' | 'completed' | 'failed'
  requires_approval?: boolean
  approved_by?: number | null
  approved_at?: string | null
  approval_note?: string | null
  error: string | null
  model_used?: string | null
  model_requested?: string | null
  created_at: string
}

export interface ToolCallData {
  tool_name: string
  content: Array<{ type: string; text?: string }>
  is_error: boolean
  duration_ms: number
}

export interface TokenUsage {
  input_tokens: number
  output_tokens: number
  cache_read?: number
  cache_write?: number
}

export interface ExecutionStats {
  total_runs: number
  total_tokens: number
  total_cost_microcents: number
  total_cost_formatted: string
  total_duration_ms: number
  by_model: Record<string, { tokens: number; cost: number; runs: number }>
  success_rate: number
  completed_count: number
  failed_count: number
}

export interface ProviderHealth {
  status: 'healthy' | 'degraded' | 'down'
  error_count: number
  last_error: string | null
  last_success_at: string | null
  avg_latency_ms: number
  updated_at: string | null
}

export interface AgentSchedule {
  id: number
  uuid: string
  project_id: number
  agent_id: number
  agent?: {
    id: number
    name: string
    slug: string
    icon: string | null
  }
  name: string
  trigger_type: 'cron' | 'webhook' | 'event'
  cron_expression: string | null
  timezone: string
  webhook_token: string | null
  webhook_url: string | null
  event_name: string | null
  event_filters: Record<string, unknown> | null
  input_template: Record<string, unknown> | null
  execution_config: Record<string, unknown> | null
  is_enabled: boolean
  last_run_at: string | null
  next_run_at: string | null
  run_count: number
  failure_count: number
  max_retries: number
  last_error: string | null
  created_at: string
  updated_at: string
}

export interface Organization {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  plan: 'free' | 'pro' | 'teams'
  member_count: number
  role: string
  created_at: string
  updated_at: string
}

export interface OrganizationMember {
  id: number
  name: string
  email: string
  avatar: string | null
  role: 'owner' | 'admin' | 'editor' | 'viewer' | 'member'
  accepted_at: string | null
}

export interface OrganizationInvitation {
  id: number
  uuid: string
  email: string
  role: string
  invited_by: { id: number; name: string } | null
  expires_at: string
  created_at: string
}

export interface AgentBudgetStatus {
  budget_limit_usd: number | null
  daily_budget_limit_usd: number | null
  current_run_spend_usd: number
  daily_spend_usd: number
  budget_remaining_usd: number | null
  daily_remaining_usd: number | null
}

export interface AuditLogEntry {
  id: number
  uuid: string
  event: string
  description: string
  metadata: Record<string, unknown> | null
  user: { id: number; name: string } | null
  agent: { id: number; name: string } | null
  project: { id: number; name: string } | null
  ip_address: string | null
  created_at: string
}

export interface ApiResponse<T> {
  data: T
}
