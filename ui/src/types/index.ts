export interface Project {
  id: number
  uuid: string
  name: string
  description: string | null
  path: string
  providers: string[]
  skills_count: number
  synced_at: string | null
  created_at: string
  updated_at: string
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
  body: string
  tags: Tag[]
  current_version_number: number
  created_at: string
  updated_at: string
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
  color: string | null
  skills_count?: number
}

export interface LibrarySkill {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  category: string
  tags: string[]
  frontmatter: Record<string, unknown>
  body: string
  source: string | null
  created_at: string
}
