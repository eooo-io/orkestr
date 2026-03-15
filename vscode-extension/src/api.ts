import * as vscode from 'vscode';

export interface OrkestrSkill {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    body: string;
    model: string | null;
    max_tokens: number | null;
    tags: Array<{ id: number; name: string }>;
    tools: string[] | null;
    includes: string[] | null;
    template_variables: Array<{ name: string; description?: string; default?: string }> | null;
    created_at: string;
    updated_at: string;
}

export interface OrkestrProject {
    id: number;
    name: string;
    path: string;
    skills_count?: number;
}

export interface OrkestrTestResult {
    status: 'success' | 'error';
    output?: string;
    error?: string;
    tokens_used?: number;
    model?: string;
}

export class OrkestrApiClient {
    private get baseUrl(): string {
        return vscode.workspace.getConfiguration('orkestr').get<string>('serverUrl', 'http://localhost:8000');
    }

    private get apiToken(): string {
        return vscode.workspace.getConfiguration('orkestr').get<string>('apiToken', '');
    }

    private get projectId(): string {
        return vscode.workspace.getConfiguration('orkestr').get<string>('projectId', '');
    }

    private get headers(): Record<string, string> {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };
        if (this.apiToken) {
            headers['Authorization'] = `Bearer ${this.apiToken}`;
        }
        return headers;
    }

    private async request<T>(path: string, options: RequestInit = {}): Promise<T> {
        const url = `${this.baseUrl}/api${path}`;
        const response = await fetch(url, {
            ...options,
            headers: {
                ...this.headers,
                ...(options.headers as Record<string, string> || {}),
            },
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(`API request failed (${response.status}): ${text}`);
        }

        return response.json() as Promise<T>;
    }

    async getProjects(): Promise<OrkestrProject[]> {
        const result = await this.request<{ data: OrkestrProject[] }>('/projects');
        return result.data;
    }

    async getSkills(projectId?: string): Promise<OrkestrSkill[]> {
        const pid = projectId || this.projectId;
        if (!pid) {
            throw new Error('No project ID configured. Set orkestr.projectId in settings.');
        }
        const result = await this.request<{ data: OrkestrSkill[] }>(`/projects/${pid}/skills`);
        return result.data;
    }

    async getSkill(skillId: number): Promise<OrkestrSkill> {
        const result = await this.request<{ data: OrkestrSkill }>(`/skills/${skillId}`);
        return result.data;
    }

    async updateSkill(skillId: number, data: Partial<OrkestrSkill>): Promise<OrkestrSkill> {
        const result = await this.request<{ data: OrkestrSkill }>(`/skills/${skillId}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return result.data;
    }

    async createSkill(projectId: string, data: Partial<OrkestrSkill>): Promise<OrkestrSkill> {
        const pid = projectId || this.projectId;
        const result = await this.request<{ data: OrkestrSkill }>(`/projects/${pid}/skills`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return result.data;
    }

    async runTest(skillId: number, input?: string): Promise<OrkestrTestResult> {
        return this.request<OrkestrTestResult>(`/skills/${skillId}/test`, {
            method: 'POST',
            body: JSON.stringify({ input: input || '' }),
        });
    }

    async syncPush(projectId?: string): Promise<{ synced: number }> {
        const pid = projectId || this.projectId;
        return this.request<{ synced: number }>(`/projects/${pid}/sync`, {
            method: 'POST',
        });
    }

    async syncPull(projectId?: string): Promise<OrkestrSkill[]> {
        const pid = projectId || this.projectId;
        const result = await this.request<{ data: OrkestrSkill[] }>(`/projects/${pid}/skills`);
        return result.data;
    }

    async lintSkill(skillId: number): Promise<Array<{ rule: string; severity: string; message: string }>> {
        return this.request<Array<{ rule: string; severity: string; message: string }>>(`/skills/${skillId}/lint`);
    }
}
