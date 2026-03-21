import * as vscode from 'vscode';
import { OrkestrApiClient } from './api';

const FRONTMATTER_REGEX = /^---\r?\n([\s\S]*?)\r?\n---/;

export class SkillTestRunner implements vscode.Disposable {
    private readonly outputChannel: vscode.OutputChannel;
    private disposables: vscode.Disposable[] = [];

    constructor(private readonly api: OrkestrApiClient) {
        this.outputChannel = vscode.window.createOutputChannel('Orkestr Tests');
    }

    async runTestForSkill(skillId: number, skillName: string): Promise<void> {
        this.outputChannel.show(true);
        this.outputChannel.appendLine(`\n${'='.repeat(60)}`);
        this.outputChannel.appendLine(`Running test: ${skillName} (ID: ${skillId})`);
        this.outputChannel.appendLine(`Started: ${new Date().toISOString()}`);
        this.outputChannel.appendLine('='.repeat(60));

        try {
            const result = await this.api.runTest(skillId);

            if (result.status === 'success') {
                this.outputChannel.appendLine(`\nStatus: PASS`);
                if (result.model) {
                    this.outputChannel.appendLine(`Model: ${result.model}`);
                }
                if (result.tokens_used) {
                    this.outputChannel.appendLine(`Tokens: ${result.tokens_used}`);
                }
                this.outputChannel.appendLine(`\nOutput:\n${result.output || '(empty)'}`);
                vscode.window.showInformationMessage(`Orkestr: Test passed for "${skillName}".`);
            } else {
                this.outputChannel.appendLine(`\nStatus: FAIL`);
                this.outputChannel.appendLine(`Error: ${result.error || 'Unknown error'}`);
                vscode.window.showWarningMessage(`Orkestr: Test failed for "${skillName}".`);
            }
        } catch (err) {
            const message = err instanceof Error ? err.message : String(err);
            this.outputChannel.appendLine(`\nStatus: ERROR`);
            this.outputChannel.appendLine(`Error: ${message}`);
            vscode.window.showErrorMessage(`Orkestr: Test error for "${skillName}": ${message}`);
        }

        this.outputChannel.appendLine(`\nFinished: ${new Date().toISOString()}`);
    }

    async runTestForActiveEditor(): Promise<void> {
        const editor = vscode.window.activeTextEditor;
        if (!editor) {
            vscode.window.showWarningMessage('No active editor.');
            return;
        }

        const skillInfo = this.extractSkillInfo(editor.document);
        if (!skillInfo) {
            vscode.window.showWarningMessage('Could not determine skill ID from this file.');
            return;
        }

        // We need to resolve the skill ID from the server
        // For now, prompt the user if we can't detect it
        if (!skillInfo.id) {
            const input = await vscode.window.showInputBox({
                prompt: 'Enter the skill ID (numeric) to test',
                placeHolder: '123',
            });
            if (!input) {
                return;
            }
            await this.runTestForSkill(parseInt(input, 10), skillInfo.name || 'Unknown');
        } else {
            // The frontmatter 'id' is a slug — we need the numeric ID from the API
            vscode.window.showInformationMessage(
                `Looking up skill "${skillInfo.id}" on server...`,
            );
            try {
                const projectId = vscode.workspace.getConfiguration('orkestr').get<string>('projectId', '');
                const skills = await this.api.getSkills(projectId);
                const match = skills.find((s) => s.slug === skillInfo.id);
                if (match) {
                    await this.runTestForSkill(match.id, match.name);
                } else {
                    vscode.window.showErrorMessage(
                        `Skill with slug "${skillInfo.id}" not found on server.`,
                    );
                }
            } catch (err) {
                const message = err instanceof Error ? err.message : String(err);
                vscode.window.showErrorMessage(`Failed to look up skill: ${message}`);
            }
        }
    }

    private extractSkillInfo(document: vscode.TextDocument): { id?: string; name?: string } | null {
        const text = document.getText();
        const match = text.match(FRONTMATTER_REGEX);
        if (!match) {
            return null;
        }

        const frontmatter = match[1];
        const idMatch = frontmatter.match(/^id:\s*(.+)/m);
        const nameMatch = frontmatter.match(/^name:\s*(.+)/m);

        return {
            id: idMatch?.[1]?.trim(),
            name: nameMatch?.[1]?.trim(),
        };
    }

    dispose(): void {
        this.outputChannel.dispose();
        this.disposables.forEach((d) => d.dispose());
    }
}

/**
 * CodeLens provider that shows "Run Test" above skill files.
 */
export class SkillTestCodeLensProvider implements vscode.CodeLensProvider {
    private _onDidChangeCodeLenses = new vscode.EventEmitter<void>();
    readonly onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;

    provideCodeLenses(document: vscode.TextDocument): vscode.CodeLens[] {
        if (!this.isSkillFile(document)) {
            return [];
        }

        const text = document.getText();
        const match = text.match(FRONTMATTER_REGEX);
        if (!match) {
            return [];
        }

        // Place CodeLens at the top of the file (line 0)
        const range = new vscode.Range(0, 0, 0, 0);

        const frontmatter = match[1];
        const nameMatch = frontmatter.match(/^name:\s*(.+)/m);
        const skillName = nameMatch?.[1]?.trim() || 'this skill';

        return [
            new vscode.CodeLens(range, {
                title: `$(play) Run Test: ${skillName}`,
                command: 'orkestr.runTest',
                tooltip: 'Run this skill test via Orkestr API',
            }),
        ];
    }

    private isSkillFile(document: vscode.TextDocument): boolean {
        if (document.languageId === 'orkestr-skill') {
            return true;
        }
        const p = document.uri.fsPath;
        return p.includes('.orkestr/skills/') || p.endsWith('.orkestr');
    }
}
