import axios from 'axios'
import type {
  Project,
  Skill,
  SkillVersion,
  Tag,
  LibrarySkill,
  ProjectAgent,
  AgentComposed,
  GeneratedSkill,
  SkillsShDiscoveredSkill,
  SkillsShSkillDetail,
  ApiResponse,
} from '@/types'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
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

// Agents
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

// Agent Compose
export const fetchAgentCompose = (projectId: number, agentId: number) =>
  api
    .get<ApiResponse<AgentComposed>>(
      `/projects/${projectId}/agents/${agentId}/compose`,
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

// Settings
export const fetchSettings = () =>
  api
    .get<{
      anthropic_api_key_set: boolean
      default_model: string
      allowed_project_paths: string[]
    }>('/settings')
    .then((r) => r.data)

export default api
