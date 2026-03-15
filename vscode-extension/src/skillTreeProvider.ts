import * as vscode from 'vscode';
import { OrkestrApiClient, OrkestrSkill, OrkestrProject } from './api';

type TreeElement = ProjectTreeItem | SkillTreeItem;

export class SkillTreeProvider implements vscode.TreeDataProvider<TreeElement> {
    private _onDidChangeTreeData = new vscode.EventEmitter<TreeElement | undefined | null>();
    readonly onDidChangeTreeData = this._onDidChangeTreeData.event;

    private filterText: string = '';
    private cachedProjects: OrkestrProject[] = [];
    private cachedSkills: Map<string, OrkestrSkill[]> = new Map();

    constructor(private readonly api: OrkestrApiClient) {}

    refresh(): void {
        this.cachedProjects = [];
        this.cachedSkills.clear();
        this._onDidChangeTreeData.fire(undefined);
    }

    setFilter(text: string): void {
        this.filterText = text.toLowerCase();
        this._onDidChangeTreeData.fire(undefined);
    }

    getTreeItem(element: TreeElement): vscode.TreeItem {
        return element;
    }

    async getChildren(element?: TreeElement): Promise<TreeElement[]> {
        if (!element) {
            return this.getProjectNodes();
        }
        if (element instanceof ProjectTreeItem) {
            return this.getSkillNodes(element.projectId);
        }
        return [];
    }

    private async getProjectNodes(): Promise<ProjectTreeItem[]> {
        try {
            if (this.cachedProjects.length === 0) {
                this.cachedProjects = await this.api.getProjects();
            }
            return this.cachedProjects.map(
                (p) => new ProjectTreeItem(p.id.toString(), p.name, p.skills_count)
            );
        } catch (err) {
            const message = err instanceof Error ? err.message : String(err);
            vscode.window.showErrorMessage(`Failed to load projects: ${message}`);
            return [];
        }
    }

    private async getSkillNodes(projectId: string): Promise<SkillTreeItem[]> {
        try {
            if (!this.cachedSkills.has(projectId)) {
                const skills = await this.api.getSkills(projectId);
                this.cachedSkills.set(projectId, skills);
            }

            let skills = this.cachedSkills.get(projectId) || [];

            if (this.filterText) {
                skills = skills.filter((s) => {
                    const searchable = [
                        s.name,
                        s.description || '',
                        s.slug,
                        ...(s.tags || []).map((t) => t.name),
                    ]
                        .join(' ')
                        .toLowerCase();
                    return searchable.includes(this.filterText);
                });
            }

            return skills.map((s) => new SkillTreeItem(s));
        } catch (err) {
            const message = err instanceof Error ? err.message : String(err);
            vscode.window.showErrorMessage(`Failed to load skills: ${message}`);
            return [];
        }
    }
}

class ProjectTreeItem extends vscode.TreeItem {
    constructor(
        public readonly projectId: string,
        public readonly projectName: string,
        skillsCount?: number,
    ) {
        super(projectName, vscode.TreeItemCollapsibleState.Collapsed);
        this.contextValue = 'project';
        this.iconPath = new vscode.ThemeIcon('folder');
        if (skillsCount !== undefined) {
            this.description = `${skillsCount} skills`;
        }
    }
}

class SkillTreeItem extends vscode.TreeItem {
    constructor(public readonly skill: OrkestrSkill) {
        super(skill.name, vscode.TreeItemCollapsibleState.None);
        this.contextValue = 'skill';
        this.description = skill.tags?.map((t) => t.name).join(', ') || '';
        this.tooltip = this.buildTooltip();
        this.iconPath = new vscode.ThemeIcon('symbol-method');
        this.command = {
            command: 'orkestr.openSkill',
            title: 'Open Skill',
            arguments: [skill],
        };
    }

    private buildTooltip(): vscode.MarkdownString {
        const md = new vscode.MarkdownString();
        md.appendMarkdown(`**${this.skill.name}**\n\n`);
        if (this.skill.description) {
            md.appendMarkdown(`${this.skill.description}\n\n`);
        }
        if (this.skill.model) {
            md.appendMarkdown(`Model: \`${this.skill.model}\`\n\n`);
        }
        if (this.skill.tags?.length) {
            md.appendMarkdown(`Tags: ${this.skill.tags.map((t) => `\`${t.name}\``).join(', ')}\n`);
        }
        return md;
    }
}
