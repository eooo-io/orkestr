import * as vscode from 'vscode';

const FRONTMATTER_REGEX = /^---\r?\n([\s\S]*?)\r?\n---/;
const REQUIRED_FIELDS = ['id', 'name'];

export class SkillDiagnosticProvider implements vscode.Disposable {
    private readonly diagnosticCollection: vscode.DiagnosticCollection;
    private readonly disposables: vscode.Disposable[] = [];

    constructor() {
        this.diagnosticCollection = vscode.languages.createDiagnosticCollection('orkestr');

        this.disposables.push(
            vscode.workspace.onDidOpenTextDocument((doc) => this.validateDocument(doc)),
            vscode.workspace.onDidSaveTextDocument((doc) => this.validateDocument(doc)),
            vscode.workspace.onDidChangeTextDocument((e) => this.validateDocument(e.document)),
            vscode.workspace.onDidCloseTextDocument((doc) => {
                this.diagnosticCollection.delete(doc.uri);
            }),
        );

        // Validate already-open documents
        vscode.workspace.textDocuments.forEach((doc) => this.validateDocument(doc));
    }

    private validateDocument(document: vscode.TextDocument): void {
        if (!this.isSkillFile(document)) {
            return;
        }

        const diagnostics: vscode.Diagnostic[] = [];
        const text = document.getText();

        // Check for frontmatter presence
        const frontmatterMatch = text.match(FRONTMATTER_REGEX);
        if (!frontmatterMatch) {
            if (text.startsWith('---')) {
                diagnostics.push(
                    new vscode.Diagnostic(
                        new vscode.Range(0, 0, 0, 3),
                        'YAML frontmatter block is not properly closed. Add a closing "---" line.',
                        vscode.DiagnosticSeverity.Error,
                    ),
                );
            } else {
                diagnostics.push(
                    new vscode.Diagnostic(
                        new vscode.Range(0, 0, 0, 0),
                        'Skill files must begin with YAML frontmatter (--- block).',
                        vscode.DiagnosticSeverity.Error,
                    ),
                );
            }
            this.diagnosticCollection.set(document.uri, diagnostics);
            return;
        }

        const frontmatterContent = frontmatterMatch[1];
        const frontmatterStartLine = 1; // Line after opening ---

        // Parse frontmatter fields (lightweight — no YAML parser dependency)
        const fields = this.parseFrontmatterFields(frontmatterContent);

        // Check required fields
        for (const field of REQUIRED_FIELDS) {
            if (!fields.has(field)) {
                diagnostics.push(
                    new vscode.Diagnostic(
                        new vscode.Range(0, 0, 0, 3),
                        `Required frontmatter field "${field}" is missing.`,
                        vscode.DiagnosticSeverity.Error,
                    ),
                );
            } else {
                const value = fields.get(field)!;
                if (!value.value || value.value.trim() === '') {
                    diagnostics.push(
                        new vscode.Diagnostic(
                            new vscode.Range(
                                frontmatterStartLine + value.line,
                                0,
                                frontmatterStartLine + value.line,
                                value.rawLength,
                            ),
                            `Required field "${field}" must not be empty.`,
                            vscode.DiagnosticSeverity.Error,
                        ),
                    );
                }
            }
        }

        // Validate id format (slug-like)
        if (fields.has('id')) {
            const idField = fields.get('id')!;
            if (idField.value && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(idField.value)) {
                diagnostics.push(
                    new vscode.Diagnostic(
                        new vscode.Range(
                            frontmatterStartLine + idField.line,
                            0,
                            frontmatterStartLine + idField.line,
                            idField.rawLength,
                        ),
                        'Field "id" should be a lowercase slug (e.g., "my-skill-name").',
                        vscode.DiagnosticSeverity.Warning,
                    ),
                );
            }
        }

        // Validate max_tokens is a positive number
        if (fields.has('max_tokens')) {
            const mtField = fields.get('max_tokens')!;
            const num = parseInt(mtField.value, 10);
            if (isNaN(num) || num <= 0) {
                diagnostics.push(
                    new vscode.Diagnostic(
                        new vscode.Range(
                            frontmatterStartLine + mtField.line,
                            0,
                            frontmatterStartLine + mtField.line,
                            mtField.rawLength,
                        ),
                        'Field "max_tokens" must be a positive integer.',
                        vscode.DiagnosticSeverity.Warning,
                    ),
                );
            }
        }

        // Check for duplicate keys
        const seenKeys = new Map<string, number>();
        const lines = frontmatterContent.split(/\r?\n/);
        for (let i = 0; i < lines.length; i++) {
            const keyMatch = lines[i].match(/^(\w[\w-]*):/);
            if (keyMatch) {
                const key = keyMatch[1];
                if (seenKeys.has(key)) {
                    diagnostics.push(
                        new vscode.Diagnostic(
                            new vscode.Range(
                                frontmatterStartLine + i,
                                0,
                                frontmatterStartLine + i,
                                lines[i].length,
                            ),
                            `Duplicate frontmatter key "${key}" (first defined on line ${(seenKeys.get(key)! + frontmatterStartLine + 1)}).`,
                            vscode.DiagnosticSeverity.Warning,
                        ),
                    );
                } else {
                    seenKeys.set(key, i);
                }
            }
        }

        this.diagnosticCollection.set(document.uri, diagnostics);
    }

    private parseFrontmatterFields(content: string): Map<string, { value: string; line: number; rawLength: number }> {
        const fields = new Map<string, { value: string; line: number; rawLength: number }>();
        const lines = content.split(/\r?\n/);

        for (let i = 0; i < lines.length; i++) {
            const match = lines[i].match(/^(\w[\w-]*):\s*(.*)/);
            if (match) {
                const key = match[1];
                let value = match[2].trim();
                // Strip surrounding quotes
                if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
                    value = value.slice(1, -1);
                }
                fields.set(key, { value, line: i, rawLength: lines[i].length });
            }
        }

        return fields;
    }

    private isSkillFile(document: vscode.TextDocument): boolean {
        // Match .orkestr files or files inside .orkestr/skills/ directory
        if (document.languageId === 'orkestr-skill') {
            return true;
        }
        const path = document.uri.fsPath;
        return path.includes('.orkestr/skills/') || path.endsWith('.orkestr');
    }

    dispose(): void {
        this.diagnosticCollection.dispose();
        this.disposables.forEach((d) => d.dispose());
    }
}
