export interface Project {
  id: number
  uuid: string
  name: string
  description: string | null
  path: string | null
  default_model?: string | null
  monthly_budget_usd?: number | null
  environment?: 'development' | 'staging' | 'production'
  icon?: string | null
  color?: string | null
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

export interface AgentProcessInfo {
  id: number
  uuid: string
  agent: { id: number; name: string; slug: string; icon: string | null }
  project: { id: number; name: string }
  status: 'starting' | 'running' | 'idle' | 'stopping' | 'stopped' | 'crashed'
  healthy: boolean
  started_at: string | null
  last_heartbeat_at: string | null
  restart_count: number
  uptime_seconds: number
  total_input_tokens: number
  total_output_tokens: number
  iterations_completed: number
  error_count: number
  avg_response_ms: number
}

export interface AgentProcessStatus {
  data: {
    id: number
    uuid: string
    status: string
    healthy: boolean
    started_at: string | null
    last_heartbeat_at: string | null
    stopped_at: string | null
    restart_count: number
    restart_policy: string
    state: Record<string, unknown> | null
    stop_reason: string | null
    uptime_seconds: number
  } | null
  running: boolean
}

export interface Artifact {
  id: number
  uuid: string
  project_id: number
  agent_id: number | null
  execution_run_id: number | null
  type: 'report' | 'code' | 'dataset' | 'decision' | 'document' | 'image' | 'other'
  title: string
  description: string | null
  content: string | null
  metadata: Record<string, unknown> | null
  format: 'markdown' | 'json' | 'csv' | 'html' | 'pdf' | 'plain' | 'binary'
  file_path: string | null
  file_size: number | null
  mime_type: string | null
  status: 'draft' | 'pending_review' | 'approved' | 'rejected' | 'published'
  version_number: number
  parent_artifact_id: number | null
  reviewed_by: number | null
  reviewed_at: string | null
  agent?: { id: number; name: string; slug: string; icon: string | null }
  created_at: string
  updated_at: string
}

export interface SkillEvalSuite {
  id: number
  skill_id: number
  name: string
  description: string | null
  prompts_count?: number
  runs_count?: number
  prompts?: SkillEvalPrompt[]
  runs?: SkillEvalRun[]
  created_at: string
  updated_at: string
}

export interface SkillEvalPrompt {
  id: number
  eval_suite_id: number
  prompt: string
  expected_behavior: string | null
  grading_criteria: Record<string, unknown> | null
  sort_order: number
  created_at: string
  updated_at: string
}

export interface SkillEvalRun {
  id: number
  eval_suite_id: number
  skill_version_id: number | null
  baseline_run_id: number | null
  model: string
  mode: 'with_skill' | 'without_skill' | 'ab_test'
  status: 'pending' | 'running' | 'completed' | 'failed'
  overall_score: number | null
  delta_score: number | null
  results: unknown[] | null
  started_at: string | null
  completed_at: string | null
  created_at: string
  updated_at: string
}

export interface DescriptionScore {
  score: number
  issues: string[]
  name: string
  description: string
  summary: string
}

export interface SkillCategory {
  id: number
  slug: string
  name: string
  description: string | null
  icon: string | null
  color: string | null
  sort_order: number
}

export interface SkillGotcha {
  id: number
  skill_id: number
  title: string
  description: string
  severity: 'critical' | 'warning' | 'info'
  source: 'manual' | 'test_failure' | 'execution' | 'review'
  source_reference_id: number | null
  resolved_at: string | null
  created_at: string
  updated_at: string
}

export interface SkillAsset {
  path: string
  name: string
  directory: string
  size: number
  type: string
}

export interface Skill {
  id: number
  uuid: string
  project_id: number
  slug: string
  name: string
  description: string | null
  summary: string | null
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
  category_id: number | null
  category: { slug: string; name: string; icon: string | null; color: string | null } | null
  skill_type: 'capability_uplift' | 'encoded_preference' | 'hybrid' | null
  tuned_for_model: string | null
  last_validated_model: string | null
  last_validated_at: string | null
  last_validated_eval_run_id: number | null
  is_folder: boolean
  assets: SkillAsset[]
  asset_count: number
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
  tuned_for_model: string | null
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

export interface AgentPersona {
  name?: string
  aliases?: string[]
  avatar?: string
  personality?: string
  bio?: string
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
  persona: AgentPersona | null
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

  // Memory
  memory_enabled?: boolean
  auto_remember?: boolean
  memory_recall_limit?: number

  // Data access
  document_access?: boolean
  knowledge_access?: boolean

  // Meta
  is_template: boolean
  created_by: number | null
  has_loop_config: boolean

  created_at: string
  updated_at: string
}

export interface AgentMemoryEntry {
  id: number
  uuid: string
  agent_id: number
  project_id: number
  type: string
  key: string | null
  content: Record<string, unknown>
  embedding: number[] | null
  metadata: Record<string, unknown> | null
  expires_at: string | null
  created_at: string
  updated_at: string
}

export interface AgentMemoryRecallResult {
  data: AgentMemoryEntry[]
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

export interface SkillBreakdownEntry {
  slug: string
  name: string
  token_estimate: number
  starts_at_char: number
  ends_at_char: number
  tuned_for_model: string | null
  last_validated_model: string | null
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
  target_model: string | null
  model_context_window: number
  skill_breakdown: SkillBreakdownEntry[]
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
    persona?: AgentPersona | null
    display_name?: string | null
    data_source_ids?: number[]
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
  data_sources?: Array<{
    id: number
    name: string
    type: string
    access_mode: string
    enabled: boolean
    health_status: string | null
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
  status:
    | 'pending'
    | 'running'
    | 'completed'
    | 'failed'
    | 'cancelled'
    | 'awaiting_approval'
    | 'halted_guardrail'
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
  token_budget?: number | null
  cost_budget_microcents?: number | null
  halt_reason?: 'loop_detected' | 'turn_cap_exceeded' | 'budget_token_exceeded' | 'budget_cost_exceeded' | null
  halt_step_id?: number | null
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

export interface ManagedUser {
  id: number
  name: string
  email: string
  avatar: string | null
  role: 'owner' | 'admin' | 'editor' | 'viewer' | 'member'
  accepted_at: string | null
  created_at: string
}

export interface PaginatedUsers {
  data: ManagedUser[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
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

// --- Performance & Dashboard ---

export interface PerformanceOverview {
  total_runs: number
  successful_runs: number
  failed_runs: number
  success_rate: number
  total_cost_usd: number
  avg_cost_per_run_usd: number
  total_tokens: number
  avg_tokens_per_run: number
  avg_duration_ms: number
  active_agents: number
}

export interface AgentPerformance {
  agent_id: number
  agent_name: string
  agent_icon: string | null
  run_count: number
  success_rate: number
  avg_cost_usd: number
  avg_duration_ms: number
  total_cost_usd: number
  last_run_at: string | null
}

export interface PerformanceTrend {
  date: string
  run_count: number
  success_count: number
  failure_count: number
  total_cost_usd: number
  total_tokens: number
}

export interface ModelUsage {
  model_name: string
  run_count: number
  total_tokens: number
  total_cost_usd: number
  avg_latency_ms: number
}

export interface AgentsOverview {
  total_agents: number
  active_agents: number
  total_runs_today: number
  total_cost_today: number
  recent_runs: Array<{
    id: number
    agent_name: string
    status: string
    duration_ms: number | null
    cost_usd: number
    created_at: string
  }>
  top_agents: Array<{
    id: number
    name: string
    icon: string | null
    run_count: number
  }>
}

export interface OnboardingStatus {
  has_project: boolean
  has_agent: boolean
  has_skill: boolean
  has_run: boolean
  has_schedule: boolean
  completed_steps: number
  total_steps: number
}

export interface ApiResponse<T> {
  data: T
}

// --- E.4: Custom Endpoints & Model Health ---

export interface CustomEndpoint {
  id: number
  organization_id: number
  name: string
  slug: string
  base_url: string
  models: string[]
  enabled: boolean
  health_status: string | null
  last_health_check: string | null
  avg_latency_ms: number | null
  created_at: string
  updated_at: string
}

export interface ModelHealthResult {
  provider: string
  status: 'healthy' | 'degraded' | 'down' | 'unconfigured'
  latency_ms: number | null
  error: string | null
  models: string[]
}

export interface ModelBenchmarkResult {
  model: string
  provider: string
  latency_ms: number
  tokens_per_second: number | null
  output: string
  error: string | null
}

export interface ModelComparisonResult {
  prompt: string
  results: ModelBenchmarkResult[]
}

export interface LocalModel {
  id: string
  name: string
  provider: string
  source: 'ollama' | 'custom'
  size: string | null
  quantization: string | null
  modified_at: string | null
}

export interface OllamaModelDetail {
  name: string
  size: number | null
  digest: string | null
  details: Record<string, unknown>
}

export interface AirGapStatus {
  enabled: boolean
  allowed_hosts: string[]
  blocked_cloud_providers: string[]
}

// --- E.5: API Tokens ---

export interface ApiToken {
  id: number
  user_id: number
  organization_id: number | null
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
  updated_at: string
}

export interface ApiTokenCreateResult {
  plain_token: string
  name: string
  id: number
}

// --- E.3: Guardrails & Security ---

export interface GuardrailPolicy {
  id: number
  uuid: string
  organization_id: number
  name: string
  description: string | null
  scope: string
  scope_id: number | null
  budget_limits: Record<string, unknown> | null
  tool_restrictions: Record<string, unknown> | null
  output_rules: Record<string, unknown> | null
  access_rules: Record<string, unknown> | null
  approval_level: string | null
  priority: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface GuardrailProfile {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  is_system: boolean
  organization_id: number | null
  budget_limits: Record<string, unknown> | null
  tool_restrictions: Record<string, unknown> | null
  output_rules: Record<string, unknown> | null
  access_rules: Record<string, unknown> | null
  approval_level: string | null
  input_sanitization: Record<string, unknown> | null
  network_rules: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface GuardrailViolation {
  id: number
  uuid: string
  organization_id: number
  project_id: number | null
  agent_id: number | null
  execution_run_id: number | null
  guard_type: string
  severity: 'low' | 'medium' | 'high' | 'critical'
  rule_name: string
  message: string
  context: Record<string, unknown> | null
  action_taken: string
  dismissed_by: number | null
  dismissed_at: string | null
  dismissal_reason: string | null
  created_at: string
}

export interface GuardrailReport {
  violations: GuardrailViolation[]
  total: number
  current_page: number
  last_page: number
}

export interface GuardrailTrend {
  date: string
  total: number
  by_severity: Record<string, number>
  by_guard_type: Record<string, number>
}

export interface SecurityScanResult {
  risk_level: 'low' | 'medium' | 'high' | 'critical'
  findings: Array<{
    type: string
    severity: string
    message: string
    line: number | null
  }>
}

export interface ContentReviewResult {
  risk_score: number
  findings: Array<{
    category: string
    severity: string
    description: string
  }>
}

export interface ContentPolicy {
  id: number
  uuid: string
  organization_id: number
  name: string
  description: string | null
  rules: Array<{ type: string; config: Record<string, unknown> }>
  is_active: boolean
  created_at: string
  updated_at: string
}

// --- E.1: SSO ---

export interface SsoProvider {
  id: number
  uuid: string
  organization_id: number
  type: 'saml' | 'oidc'
  name: string
  entity_id: string | null
  metadata_url: string | null
  sso_url: string | null
  client_id: string | null
  allowed_domains: string[]
  auto_provision: boolean
  default_role: string
  is_active: boolean
  last_used_at: string | null
  created_at: string
  updated_at: string
}

// --- E.6: Enterprise ---

export interface SkillReview {
  id: number
  skill_id: number
  skill_version_id: number | null
  reviewer_id: number | null
  submitted_by: number
  status: 'pending' | 'approved' | 'rejected'
  comments: string | null
  reviewer?: { id: number; name: string }
  submitter?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface SkillOwnership {
  owner_id: number | null
  codeowners: string[] | null
  owner?: { id: number; name: string; email: string }
}

export interface SkillAnalytic {
  id: number
  skill_id: number
  date: string
  test_runs: number
  pass_count: number
  fail_count: number
  avg_tokens: number
  avg_cost_microcents: number
  avg_latency_ms: number
}

export interface SkillTestCase {
  id: number
  skill_id: number
  name: string
  input: string
  expected_output: string | null
  assertion_type: string
  pass_threshold: number
  created_at: string
  updated_at: string
}

export interface SkillTestCaseResult {
  test_case_id: number
  name: string
  passed: boolean
  actual_output: string
  score: number | null
  error: string | null
}

export interface SkillBenchmarkResult {
  model: string
  output: string
  latency_ms: number
  tokens: number
  cost_microcents: number
  error: string | null
}

export interface SkillInheritanceInfo {
  extends_skill_id: number | null
  override_sections: Record<string, unknown> | null
  resolved_body: string
  parent?: { id: number; name: string; slug: string }
  children: Array<{ id: number; name: string; slug: string }>
}

export interface Notification {
  id: number
  user_id: number
  organization_id: number | null
  type: string
  title: string
  body: string | null
  data: Record<string, unknown> | null
  read_at: string | null
  created_at: string
}

export interface GitHubDiscoveredRepo {
  name: string
  full_name: string
  skills_count: number
  paths: string[]
}

export interface GitHubImportResult {
  imported: number
  skipped: number
  errors: string[]
}

// --- G.4: Execution Replay ---

export interface ExecutionReplay {
  id: number
  uuid: string
  project_id: number
  agent_id: number | null
  agent?: {
    id: number
    name: string
    slug: string
    icon: string | null
  }
  name: string
  status: 'running' | 'completed' | 'failed' | 'cancelled'
  total_steps: number
  total_tokens: number
  total_cost_microcents: number
  total_duration_ms: number
  metadata: Record<string, unknown> | null
  started_at: string | null
  completed_at: string | null
  steps?: ExecutionReplayStep[]
  steps_count?: number
  created_at: string
  updated_at: string
}

export interface ExecutionReplayStep {
  id: number
  execution_replay_id: number
  step_number: number
  type: 'tool_call' | 'llm_response' | 'decision' | 'observation' | 'error'
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  model: string | null
  tokens_used: number | null
  cost_microcents: number | null
  duration_ms: number | null
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface ExecutionDiff {
  left: (ExecutionReplayStep | null)[]
  right: (ExecutionReplayStep | null)[]
  summary: ExecutionDiffSummary
}

export interface ExecutionDiffSummary {
  tokens_diff: number
  cost_diff: number
  duration_diff: number
  steps_diff: number
}

// --- E.2: Deployment ---

export interface LicenseStatus {
  valid: boolean
  tier: string | null
  features: Record<string, boolean>
  expires_at: string | null
}

export interface SetupStatus {
  completed: boolean
  steps: Record<string, boolean>
}

export interface BackupEntry {
  filename: string
  size: number
  created_at: string
}

export interface DiagnosticCheck {
  name: string
  status: string
  message: string
  latency_ms?: number | null
  details?: Record<string, unknown> | null
}

// --- G.3: Model Pull & Recommendations ---

export interface ModelPullProgress {
  status: string
  completed: number
  total: number
  model: string
  error?: string
}

export interface ModelRecommendation {
  model: string
  provider: string
  reason: string
  size_gb: number | null
  local_available: boolean
}

// --- Agent Tasks (M.4 #391-#396) ---

export interface AgentTask {
  id: number
  project_id: number
  agent_id: number | null
  parent_task_id: number | null
  title: string
  description: string | null
  priority: 'low' | 'medium' | 'high' | 'critical'
  status: 'pending' | 'assigned' | 'running' | 'completed' | 'failed' | 'cancelled'
  input_data: Record<string, unknown> | null
  output_data: Record<string, unknown> | null
  execution_id: number | null
  created_at: string
  started_at: string | null
  completed_at: string | null
  agent?: {
    id: number
    name: string
    slug: string
    icon: string | null
    persona: AgentPersona | null
  } | null
  child_tasks?: AgentTask[]
}

// --- Canvas Layout ---

export interface CanvasLayout {
  nodes: Record<string, { x: number; y: number }>
}

// --- Canvas Delegation Config (#347) ---

export interface DelegationConfig {
  edge_id: string
  source_agent_id: number
  target_agent_id: number
  delegation_trigger: string
  handoff_context: {
    pass_conversation: boolean
    pass_memory: boolean
    pass_tools: boolean
    custom_json: string
  }
  return_behavior: 'report_back' | 'fire_and_forget' | 'chain_forward'
}

// --- MCP Server full detail (for canvas detail panel) ---

export interface McpServerDetail {
  id: number
  project_id: number
  name: string
  transport: string
  command: string | null
  args: string[] | null
  url: string | null
  env: Record<string, string> | null
  headers: Record<string, string> | null
  enabled: boolean
  approval_status: string | null
  created_at: string
  updated_at: string
}

// --- A2A Agent full detail (for canvas detail panel) ---

export interface A2aAgentDetail {
  id: number
  project_id: number
  name: string
  url: string
  description: string | null
  skills: string[] | null
  enabled: boolean
  approval_status: string | null
  created_at: string
  updated_at: string
}

// --- N.3: Document Storage ---

export interface AgentDocument {
  path: string
  size: number
  last_modified: number
}

// --- N.5: Data Sources ---

export interface DataSource {
  id: number
  project_id: number
  name: string
  type: 'postgres' | 'mysql' | 'minio' | 's3' | 'filesystem' | 'redis'
  connection_config: Record<string, unknown>
  access_mode: 'read_only' | 'read_write'
  enabled: boolean
  health_status: string | null
  last_health_check: string | null
  agents_count: number
  created_at: string
  updated_at: string
}

export interface DataSourceTestResult {
  status: string
  message: string
}

// --- N.4: Knowledge Base ---

export interface AgentKnowledgeEntry {
  id: number
  agent_id: number
  project_id: number
  namespace: string
  key: string
  value: Record<string, unknown>
  created_at: string
  updated_at: string
}

// --- R.1: Control Plane ---

export interface ControlPlaneSession {
  id: number
  uuid: string
  user_id: number
  organization_id: number | null
  title: string | null
  context: Record<string, unknown>
  messages: ControlPlaneMessage[]
  created_at: string
  updated_at: string
}

export interface ControlPlaneMessage {
  id: number
  session_id: number
  role: 'user' | 'assistant' | 'system' | 'tool_result'
  content: string
  tool_calls: Array<{ name: string; input: Record<string, unknown>; result?: unknown }> | null
  metadata: Record<string, unknown> | null
  created_at: string
}

// --- R.2: Plugin System ---

export interface Plugin {
  id: number
  uuid: string
  organization_id: number
  name: string
  slug: string
  description: string | null
  version: string
  author: string | null
  type: 'tool' | 'node' | 'panel' | 'provider' | 'composite'
  manifest: Record<string, unknown>
  entry_point: string
  config: Record<string, unknown> | null
  enabled: boolean
  installed_at: string
  hooks: PluginHook[]
  created_at: string
  updated_at: string
}

export interface PluginHook {
  id: number
  plugin_id: number
  hook_name: string
  handler: string
  priority: number
  enabled: boolean
}

// --- R.3: Agent Templates Marketplace ---

export interface MarketplaceAgent {
  id: number
  uuid: string
  name: string
  slug: string
  description: string
  category: string | null
  tags: string[]
  agent_config: Record<string, unknown>
  skills_config: Array<Record<string, unknown>>
  workflow_config: Record<string, unknown> | null
  wiring_config: Record<string, unknown> | null
  author: string
  author_url: string | null
  version: string
  downloads: number
  upvotes: number
  downvotes: number
  screenshots: string[] | null
  readme: string | null
  created_at: string
  updated_at: string
}

// --- R.4: Shared Memory ---

export interface SharedMemoryPool {
  id: number
  uuid: string
  project_id: number
  name: string
  slug: string
  description: string | null
  access_policy: 'open' | 'explicit' | 'role_based'
  retention_days: number | null
  entry_count?: number
  agent_count?: number
  agents?: Array<{ id: number; name: string; access_mode: string }>
  created_at: string
  updated_at: string
}

export interface SharedMemoryEntry {
  id: number
  uuid: string
  pool_id: number
  contributed_by_agent_id: number | null
  contributor?: { id: number; name: string }
  key: string
  content: Record<string, unknown>
  tags: string[] | null
  confidence: number
  metadata: Record<string, unknown> | null
  expires_at: string | null
  created_at: string
  updated_at: string
}

export interface KnowledgeGraphNode {
  id: number
  uuid: string
  project_id: number
  pool_id: number | null
  entity_type: string
  entity_name: string
  properties: Record<string, unknown> | null
  created_at: string
}

export interface KnowledgeGraphEdge {
  id: number
  source_node_id: number
  target_node_id: number
  relationship: string
  properties: Record<string, unknown> | null
  weight: number
  created_at: string
}

export interface KnowledgeGraph {
  nodes: KnowledgeGraphNode[]
  edges: KnowledgeGraphEdge[]
}

// --- R.5: Smart Routing ---

export interface AgentCapability {
  id: number
  agent_id: number
  project_id: number
  agent?: { id: number; name: string; slug: string }
  capability: string
  proficiency: number
  avg_duration_ms: number
  avg_cost_microcents: number
  success_rate: number
  task_count: number
  last_used_at: string | null
}

export interface RoutingRule {
  id: number
  uuid: string
  project_id: number
  name: string
  description: string | null
  conditions: Record<string, unknown>
  target_strategy: 'best_fit' | 'round_robin' | 'least_loaded' | 'cost_optimized' | 'fastest'
  target_agents: number[] | null
  sla_config: { max_wait_seconds?: number; max_cost?: number; priority_boost?: number } | null
  priority: number
  enabled: boolean
  created_at: string
  updated_at: string
}

export interface RoutingDecision {
  id: number
  task_id: number | null
  selected_agent_id: number | null
  selected_agent?: { id: number; name: string }
  strategy_used: string
  candidates: Array<{ agent_id: number; agent_name: string; score: number; breakdown: Record<string, number> }>
  reasoning: string | null
  sla_met: boolean
  created_at: string
}

export interface RoutingSimulationResult {
  selected_agent: { id: number; name: string; score: number } | null
  strategy: string
  candidates: Array<{ agent_id: number; agent_name: string; score: number; breakdown: Record<string, number> }>
  reasoning: string
}

// --- S.1: Collaboration ---

export interface PresenceSession {
  id: number
  uuid: string
  user_id: number
  user?: { id: number; name: string; avatar: string | null }
  resource_type: string
  resource_id: number
  cursor_position: { line: number; column: number } | null
  selection: Record<string, unknown> | null
  color: string
  last_seen_at: string
}

export interface CollaborationComment {
  id: number
  uuid: string
  user_id: number
  user?: { id: number; name: string; avatar: string | null }
  resource_type: string
  resource_id: number
  thread_id: number | null
  line_number: number | null
  body: string
  resolved_at: string | null
  resolved_by: number | null
  replies?: CollaborationComment[]
  created_at: string
  updated_at: string
}

export interface DebugSession {
  id: number
  uuid: string
  project_id: number
  execution_run_id: number | null
  created_by: number
  creator?: { id: number; name: string }
  title: string
  status: 'active' | 'paused' | 'ended'
  participants: number[]
  breakpoints: Record<string, unknown> | null
  created_at: string
  ended_at: string | null
}

// --- S.2: Mobile ---

export interface PushSubscription {
  id: number
  user_id: number
  endpoint: string
  user_agent: string | null
  created_at: string
}

export interface MobileOverview {
  active_agents: number
  pending_approvals: number
  recent_runs: Array<{ id: number; agent_name: string; status: string; created_at: string }>
  fleet_health: { healthy: number; unhealthy: number }
}

// --- S.3: Observability ---

export interface CustomMetric {
  id: number
  uuid: string
  organization_id: number
  name: string
  slug: string
  query_type: 'count_runs' | 'sum_tokens' | 'avg_cost' | 'avg_duration' | 'error_rate' | 'custom'
  query_config: Record<string, unknown>
  unit: string
  created_at: string
  updated_at: string
}

export interface AlertRule {
  id: number
  uuid: string
  organization_id: number
  name: string
  metric_slug: string
  condition: 'gt' | 'lt' | 'gte' | 'lte' | 'eq'
  threshold: number
  window_minutes: number
  cooldown_minutes: number
  notification_channel_id: number | null
  severity: 'info' | 'warning' | 'critical'
  enabled: boolean
  last_triggered_at: string | null
  incident_count?: number
  created_at: string
}

export interface AlertIncident {
  id: number
  uuid: string
  alert_rule_id: number
  alert_rule?: AlertRule
  metric_value: number
  threshold_value: number
  status: 'firing' | 'acknowledged' | 'resolved'
  acknowledged_by: number | null
  acknowledged_at: string | null
  resolved_at: string | null
  created_at: string
}

export interface DashboardLayout {
  id: number
  uuid: string
  organization_id: number
  user_id: number | null
  name: string
  layout: Array<{ metric_slug: string; chart_type: string; x: number; y: number; w: number; h: number }>
  is_default: boolean
  created_at: string
}

export interface CostForecast {
  daily_costs: Array<{ date: string; cost: number }>
  forecast_7d: number
  forecast_30d: number
  trend: 'increasing' | 'stable' | 'decreasing'
  avg_daily: number
}

export interface MetricDataPoint {
  timestamp: string
  value: number
}

// --- S.4: Negotiation ---

export interface TaskBid {
  id: number
  uuid: string
  task_id: number | null
  agent_id: number
  agent?: { id: number; name: string; slug: string }
  project_id: number
  bid_score: number
  estimated_duration_ms: number
  estimated_cost_microcents: number
  confidence: number
  reasoning: string | null
  status: 'pending' | 'accepted' | 'rejected' | 'expired' | 'withdrawn'
  expires_at: string
  created_at: string
}

export interface CapabilityAdvertisement {
  id: number
  agent_id: number
  agent?: { id: number; name: string; slug: string; icon: string | null }
  project_id: number
  capabilities: Array<{ name: string; proficiency: number; cost_per_task: number }>
  availability_status: 'available' | 'busy' | 'offline'
  max_concurrent_tasks: number
  current_load: number
  advertised_at: string
  expires_at: string
}

export interface TeamFormation {
  id: number
  uuid: string
  project_id: number
  name: string
  objective: string
  formation_strategy: 'capability_match' | 'cost_optimized' | 'speed_optimized'
  agent_ids: number[]
  agents?: Array<{ id: number; name: string }>
  status: 'forming' | 'active' | 'disbanded'
  performance_score: number | null
  created_at: string
  disbanded_at: string | null
}

export interface NegotiationLog {
  id: number
  task_id: number | null
  team_formation_id: number | null
  agent_id: number
  agent?: { id: number; name: string }
  action: string
  details: Record<string, unknown> | null
  created_at: string
}

// --- S.5: Federation ---

export interface FederationPeer {
  id: number
  uuid: string
  organization_id: number
  name: string
  base_url: string
  status: 'pending' | 'active' | 'suspended' | 'revoked'
  capabilities: Record<string, unknown> | null
  last_heartbeat_at: string | null
  last_sync_at: string | null
  trust_level: 'untrusted' | 'basic' | 'verified' | 'full'
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface FederationDelegation {
  id: number
  uuid: string
  peer_id: number
  peer?: { id: number; name: string; base_url: string }
  local_agent_id: number | null
  local_agent?: { id: number; name: string }
  remote_agent_slug: string
  direction: 'outbound' | 'inbound'
  status: 'pending' | 'active' | 'completed' | 'failed'
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  cost_microcents: number
  duration_ms: number
  created_at: string
  completed_at: string | null
}

export interface FederatedIdentity {
  id: number
  user_id: number
  peer_id: number
  peer?: { id: number; name: string }
  remote_user_id: string
  remote_email: string | null
  remote_role: string | null
  verified_at: string | null
  created_at: string
}
