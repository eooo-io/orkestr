import * as vscode from 'vscode';
import * as path from 'path';
import * as fs from 'fs';
import { OrkestrApiClient, OrkestrSkill } from './api';

type SyncState = 'synced' | 'modified' | 'error' | 'idle';

export class SyncManager implements vscode.Disposable {
    private statusBarItem: vscode.StatusBarItem;
    private state: SyncState = 'idle';
    private disposables: vscode.Disposable[] = [];
    private fileWatcher: vscode.FileSystemWatcher | undefined;

    constructor(private readonly api: OrkestrApiClient) {
        this.statusBarItem = vscode.window.createStatusBarItem(
            vscode.StatusBarAlignment.Left,
            100,
        );
        this.statusBarItem.command = 'orkestr.syncPush';
        this.updateStatusBar();
        this.statusBarItem.show();

        // Watch for file changes in .orkestr directories
        this.fileWatcher = vscode.workspace.createFileSystemWatcher('**/.orkestr/skills/**');
        this.disposables.push(
            this.fileWatcher.onDidChange(() => this.onFileChanged()),
            this.fileWatcher.onDidCreate(() => this.onFileChanged()),
            this.fileWatcher.onDidDelete(() => this.onFileChanged()),
        );

        // Auto-sync on save if enabled
        this.disposables.push(
            vscode.workspace.onDidSaveTextDocument((doc) => {
                if (this.isSkillFile(doc.uri.fsPath)) {
                    this.onFileChanged();
                    const autoSync = vscode.workspace.getConfiguration('orkestr').get<boolean>('autoSync', false);
                    if (autoSync) {
                        this.push();
                    }
                }
            }),
        );
    }

    private onFileChanged(): void {
        this.setState('modified');
    }

    private setState(state: SyncState): void {
        this.state = state;
        this.updateStatusBar();
    }

    private updateStatusBar(): void {
        switch (this.state) {
            case 'synced':
                this.statusBarItem.text = '$(check) Orkestr: Synced';
                this.statusBarItem.backgroundColor = undefined;
                this.statusBarItem.tooltip = 'All skills are in sync with the server';
                break;
            case 'modified':
                this.statusBarItem.text = '$(cloud-upload) Orkestr: Modified';
                this.statusBarItem.backgroundColor = new vscode.ThemeColor('statusBarItem.warningBackground');
                this.statusBarItem.tooltip = 'Local changes detected. Click to push.';
                break;
            case 'error':
                this.statusBarItem.text = '$(error) Orkestr: Error';
                this.statusBarItem.backgroundColor = new vscode.ThemeColor('statusBarItem.errorBackground');
                this.statusBarItem.tooltip = 'Sync error occurred. Click to retry.';
                break;
            case 'idle':
                this.statusBarItem.text = '$(cloud) Orkestr';
                this.statusBarItem.backgroundColor = undefined;
                this.statusBarItem.tooltip = 'Click to sync with Orkestr server';
                break;
        }
    }

    async push(): Promise<void> {
        const projectId = vscode.workspace.getConfiguration('orkestr').get<string>('projectId', '');
        if (!projectId) {
            vscode.window.showErrorMessage('No project ID configured. Set orkestr.projectId in settings.');
            return;
        }

        try {
            this.statusBarItem.text = '$(sync~spin) Orkestr: Pushing...';
            const result = await this.api.syncPush(projectId);
            this.setState('synced');
            vscode.window.showInformationMessage(`Orkestr: Pushed ${result.synced} skill(s) to server.`);
        } catch (err) {
            this.setState('error');
            const message = err instanceof Error ? err.message : String(err);
            vscode.window.showErrorMessage(`Orkestr push failed: ${message}`);
        }
    }

    async pull(): Promise<void> {
        const projectId = vscode.workspace.getConfiguration('orkestr').get<string>('projectId', '');
        if (!projectId) {
            vscode.window.showErrorMessage('No project ID configured. Set orkestr.projectId in settings.');
            return;
        }

        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) {
            vscode.window.showErrorMessage('No workspace folder open.');
            return;
        }

        const targetDir = path.join(workspaceFolders[0].uri.fsPath, '.orkestr', 'skills');

        try {
            this.statusBarItem.text = '$(sync~spin) Orkestr: Pulling...';
            const skills = await this.api.syncPull(projectId);

            // Ensure directory exists
            if (!fs.existsSync(targetDir)) {
                fs.mkdirSync(targetDir, { recursive: true });
            }

            let written = 0;
            for (const skill of skills) {
                const content = this.skillToMarkdown(skill);
                const filePath = path.join(targetDir, `${skill.slug}.md`);
                fs.writeFileSync(filePath, content, 'utf-8');
                written++;
            }

            this.setState('synced');
            vscode.window.showInformationMessage(`Orkestr: Pulled ${written} skill(s) to .orkestr/skills/.`);
        } catch (err) {
            this.setState('error');
            const message = err instanceof Error ? err.message : String(err);
            vscode.window.showErrorMessage(`Orkestr pull failed: ${message}`);
        }
    }

    private skillToMarkdown(skill: OrkestrSkill): string {
        const frontmatter: string[] = ['---'];
        frontmatter.push(`id: ${skill.slug}`);
        frontmatter.push(`name: ${skill.name}`);
        if (skill.description) {
            frontmatter.push(`description: ${skill.description}`);
        }
        if (skill.tags?.length) {
            frontmatter.push(`tags: [${skill.tags.map((t) => t.name).join(', ')}]`);
        }
        if (skill.model) {
            frontmatter.push(`model: ${skill.model}`);
        }
        if (skill.max_tokens) {
            frontmatter.push(`max_tokens: ${skill.max_tokens}`);
        }
        if (skill.tools?.length) {
            frontmatter.push(`tools: [${skill.tools.join(', ')}]`);
        }
        if (skill.includes?.length) {
            frontmatter.push(`includes: [${skill.includes.join(', ')}]`);
        }
        if (skill.template_variables?.length) {
            frontmatter.push('template_variables:');
            for (const v of skill.template_variables) {
                frontmatter.push(`  - name: ${v.name}`);
                if (v.description) {
                    frontmatter.push(`    description: ${v.description}`);
                }
                if (v.default) {
                    frontmatter.push(`    default: ${v.default}`);
                }
            }
        }
        frontmatter.push(`created_at: ${skill.created_at}`);
        frontmatter.push(`updated_at: ${skill.updated_at}`);
        frontmatter.push('---');

        return frontmatter.join('\n') + '\n\n' + (skill.body || '') + '\n';
    }

    private isSkillFile(filePath: string): boolean {
        return filePath.includes('.orkestr/skills/') || filePath.endsWith('.orkestr');
    }

    dispose(): void {
        this.statusBarItem.dispose();
        this.fileWatcher?.dispose();
        this.disposables.forEach((d) => d.dispose());
    }
}
