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
  icon: string | null
  sort_order: number
}

export interface ProjectAgent extends Agent {
  is_enabled: boolean
  custom_instructions: string | null
  skill_ids: number[]
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

export interface ApiResponse<T> {
  data: T
}
