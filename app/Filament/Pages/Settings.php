<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'Administration';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'anthropic_api_key' => AppSetting::get('anthropic_api_key', ''),
            'openai_api_key' => AppSetting::get('openai_api_key', ''),
            'gemini_api_key' => AppSetting::get('gemini_api_key', ''),
            'ollama_url' => AppSetting::get('ollama_url', 'http://localhost:11434'),
            'default_model' => AppSetting::get('default_model', 'claude-sonnet-4-6'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('LLM Provider API Keys')
                    ->description('Configure API keys for the LLM providers your agents will use.')
                    ->schema([
                        Forms\Components\TextInput::make('anthropic_api_key')
                            ->label('Anthropic API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-ant-...')
                            ->helperText('Required for Claude models'),

                        Forms\Components\TextInput::make('openai_api_key')
                            ->label('OpenAI API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-...')
                            ->helperText('Required for GPT and o-series models'),

                        Forms\Components\TextInput::make('gemini_api_key')
                            ->label('Google Gemini API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('AIza...')
                            ->helperText('Required for Gemini models'),

                        Forms\Components\TextInput::make('ollama_url')
                            ->label('Ollama URL')
                            ->placeholder('http://localhost:11434')
                            ->helperText('Local Ollama instance for open-source models'),
                    ]),

                Forms\Components\Section::make('Defaults')
                    ->schema([
                        Forms\Components\Select::make('default_model')
                            ->label('Default Model')
                            ->options([
                                'Anthropic' => [
                                    'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                                    'claude-opus-4-6' => 'Claude Opus 4.6',
                                    'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                                ],
                                'OpenAI' => [
                                    'gpt-4o' => 'GPT-4o',
                                    'gpt-4o-mini' => 'GPT-4o Mini',
                                    'o3' => 'o3',
                                ],
                                'Google' => [
                                    'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                                ],
                            ])
                            ->helperText('Default model for new agents and playground sessions'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::set('anthropic_api_key', $data['anthropic_api_key'] ?? '');
        AppSetting::set('openai_api_key', $data['openai_api_key'] ?? '');
        AppSetting::set('gemini_api_key', $data['gemini_api_key'] ?? '');
        AppSetting::set('ollama_url', $data['ollama_url'] ?? 'http://localhost:11434');
        AppSetting::set('default_model', $data['default_model'] ?? 'claude-sonnet-4-6');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
