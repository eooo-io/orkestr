<?php

namespace App\Filament\Pages;

use App\Jobs\ProjectScanJob;
use App\Models\AppSetting;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'anthropic_api_key' => AppSetting::get('anthropic_api_key', ''),
            'default_model' => AppSetting::get('default_model', 'claude-sonnet-4-6'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('API Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('anthropic_api_key')
                            ->label('Anthropic API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Used for the live skill test runner'),

                        Forms\Components\Select::make('default_model')
                            ->label('Default Model')
                            ->options([
                                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                                'claude-opus-4-6' => 'Claude Opus 4.6',
                                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                            ]),
                    ]),

                Forms\Components\Section::make('Provider Output Reference')
                    ->schema([
                        Forms\Components\Placeholder::make('provider_reference')
                            ->content(
                                new \Illuminate\Support\HtmlString('
                                    <table class="w-full text-sm">
                                        <thead><tr class="border-b"><th class="text-left p-2">Provider</th><th class="text-left p-2">Output Path</th><th class="text-left p-2">Format</th></tr></thead>
                                        <tbody>
                                            <tr class="border-b"><td class="p-2">Claude</td><td class="p-2 font-mono text-xs">.claude/CLAUDE.md</td><td class="p-2">Concatenated H2</td></tr>
                                            <tr class="border-b"><td class="p-2">Cursor</td><td class="p-2 font-mono text-xs">.cursor/rules/{slug}.mdc</td><td class="p-2">Per-file MDC</td></tr>
                                            <tr class="border-b"><td class="p-2">Copilot</td><td class="p-2 font-mono text-xs">.github/copilot-instructions.md</td><td class="p-2">Concatenated H2</td></tr>
                                            <tr class="border-b"><td class="p-2">Windsurf</td><td class="p-2 font-mono text-xs">.windsurf/rules/{slug}.md</td><td class="p-2">Per-file</td></tr>
                                            <tr class="border-b"><td class="p-2">Cline</td><td class="p-2 font-mono text-xs">.clinerules</td><td class="p-2">Single flat file</td></tr>
                                            <tr><td class="p-2">OpenAI</td><td class="p-2 font-mono text-xs">.openai/instructions.md</td><td class="p-2">Concatenated H2</td></tr>
                                        </tbody>
                                    </table>
                                ')
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::set('anthropic_api_key', $data['anthropic_api_key'] ?? '');
        AppSetting::set('default_model', $data['default_model'] ?? 'claude-sonnet-4-6');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function rescanAll(): void
    {
        $projects = Project::all();

        foreach ($projects as $project) {
            ProjectScanJob::dispatch($project);
        }

        Notification::make()
            ->title('Rescan started')
            ->body("Queued scan for {$projects->count()} projects")
            ->success()
            ->send();
    }
}
