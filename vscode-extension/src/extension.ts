import * as vscode from 'vscode';
import { OrkestrApiClient, OrkestrSkill } from './api';
import { SkillTreeProvider } from './skillTreeProvider';
import { SkillDiagnosticProvider } from './skillEditor';
import { SyncManager } from './syncManager';
import { SkillTestRunner, SkillTestCodeLensProvider } from './testRunner';

export function activate(context: vscode.ExtensionContext): void {
    const api = new OrkestrApiClient();
    const skillTreeProvider = new SkillTreeProvider(api);
    const syncManager = new SyncManager(api);
    const testRunner = new SkillTestRunner(api);
    const diagnosticProvider = new SkillDiagnosticProvider();
    const codeLensProvider = new SkillTestCodeLensProvider();

    // Register tree view
    const treeView = vscode.window.createTreeView('orkestr.skillBrowser', {
        treeDataProvider: skillTreeProvider,
        showCollapseAll: true,
    });
    context.subscriptions.push(treeView);

    // Register CodeLens provider for skill files
    context.subscriptions.push(
        vscode.languages.registerCodeLensProvider(
            [
                { language: 'agentis-skill' },
                { pattern: '**/.agentis/skills/**' },
            ],
            codeLensProvider,
        ),
    );

    // Register commands
    context.subscriptions.push(
        vscode.commands.registerCommand('orkestr.refreshSkills', () => {
            skillTreeProvider.refresh();
        }),

        vscode.commands.registerCommand('orkestr.filterSkills', async () => {
            const filter = await vscode.window.showInputBox({
                prompt: 'Filter skills by name, tag, or description',
                placeHolder: 'e.g. summarization',
            });
            if (filter !== undefined) {
                skillTreeProvider.setFilter(filter);
            }
        }),

        vscode.commands.registerCommand('orkestr.openSkill', async (skill: OrkestrSkill) => {
            if (!skill) {
                return;
            }
            // Open a virtual document with the skill content
            const uri = vscode.Uri.parse(
                `orkestr-skill://${skill.slug}.agentis?id=${skill.id}`,
            );

            // Register a content provider if not already done
            const content = buildSkillContent(skill);
            const provider = new (class implements vscode.TextDocumentContentProvider {
                provideTextDocumentContent(): string {
                    return content;
                }
            })();

            const registration = vscode.workspace.registerTextDocumentContentProvider(
                'orkestr-skill',
                provider,
            );
            context.subscriptions.push(registration);

            const doc = await vscode.workspace.openTextDocument(uri);
            await vscode.window.showTextDocument(doc, { preview: false });
        }),

        vscode.commands.registerCommand('orkestr.syncPush', () => {
            syncManager.push();
        }),

        vscode.commands.registerCommand('orkestr.syncPull', () => {
            syncManager.pull();
        }),

        vscode.commands.registerCommand('orkestr.runTest', () => {
            testRunner.runTestForActiveEditor();
        }),

        vscode.commands.registerCommand('orkestr.runAllTests', async () => {
            const projectId = vscode.workspace.getConfiguration('orkestr').get<string>('projectId', '');
            if (!projectId) {
                vscode.window.showErrorMessage('No project ID configured.');
                return;
            }

            try {
                const skills = await api.getSkills(projectId);
                vscode.window.showInformationMessage(`Running tests for ${skills.length} skill(s)...`);
                for (const skill of skills) {
                    await testRunner.runTestForSkill(skill.id, skill.name);
                }
                vscode.window.showInformationMessage('All skill tests completed.');
            } catch (err) {
                const message = err instanceof Error ? err.message : String(err);
                vscode.window.showErrorMessage(`Failed to run tests: ${message}`);
            }
        }),

        vscode.commands.registerCommand('orkestr.createSkill', async () => {
            const name = await vscode.window.showInputBox({
                prompt: 'Skill name',
                placeHolder: 'e.g. Summarize Document',
            });
            if (!name) {
                return;
            }

            const slug = name
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');

            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (!workspaceFolders) {
                vscode.window.showErrorMessage('No workspace folder open.');
                return;
            }

            const fs = await import('fs');
            const path = await import('path');
            const skillsDir = path.join(workspaceFolders[0].uri.fsPath, '.agentis', 'skills');
            if (!fs.existsSync(skillsDir)) {
                fs.mkdirSync(skillsDir, { recursive: true });
            }

            const filePath = path.join(skillsDir, `${slug}.md`);
            const content = [
                '---',
                `id: ${slug}`,
                `name: ${name}`,
                'description: ',
                'tags: []',
                'model: claude-sonnet-4-6',
                'max_tokens: 1000',
                'tools: []',
                'includes: []',
                '---',
                '',
                'Your prompt instructions here...',
                '',
            ].join('\n');

            fs.writeFileSync(filePath, content, 'utf-8');

            const doc = await vscode.workspace.openTextDocument(filePath);
            await vscode.window.showTextDocument(doc);
            vscode.window.showInformationMessage(`Created skill: ${name}`);
        }),
    );

    // Register disposables
    context.subscriptions.push(syncManager, testRunner, diagnosticProvider);

    // Log activation
    const outputChannel = vscode.window.createOutputChannel('Orkestr');
    outputChannel.appendLine('Orkestr extension activated.');
    context.subscriptions.push(outputChannel);
}

function buildSkillContent(skill: OrkestrSkill): string {
    const lines: string[] = ['---'];
    lines.push(`id: ${skill.slug}`);
    lines.push(`name: ${skill.name}`);
    if (skill.description) {
        lines.push(`description: ${skill.description}`);
    }
    if (skill.tags?.length) {
        lines.push(`tags: [${skill.tags.map((t) => t.name).join(', ')}]`);
    }
    if (skill.model) {
        lines.push(`model: ${skill.model}`);
    }
    if (skill.max_tokens) {
        lines.push(`max_tokens: ${skill.max_tokens}`);
    }
    lines.push('---');
    lines.push('');
    lines.push(skill.body || '');
    return lines.join('\n');
}

export function deactivate(): void {
    // Cleanup handled by disposables registered in context.subscriptions
}
