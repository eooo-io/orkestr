<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SsoProviderResource\Pages;
use App\Models\SsoProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SsoProviderResource extends Resource
{
    protected static ?string $model = SsoProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'SSO Providers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider Details')
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Okta, Azure AD, Google Workspace'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'saml' => 'SAML 2.0',
                                'oidc' => 'OpenID Connect (OIDC)',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(false)
                            ->helperText('Enable after testing the connection'),
                    ]),

                Forms\Components\Section::make('SAML Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('entity_id')
                            ->label('IdP Entity ID')
                            ->maxLength(1000)
                            ->placeholder('https://idp.example.com/saml/metadata'),

                        Forms\Components\TextInput::make('sso_url')
                            ->label('SSO URL')
                            ->url()
                            ->maxLength(2000)
                            ->placeholder('https://idp.example.com/saml/sso'),

                        Forms\Components\TextInput::make('slo_url')
                            ->label('SLO URL (optional)')
                            ->url()
                            ->maxLength(2000),

                        Forms\Components\Textarea::make('certificate')
                            ->label('IdP Certificate (PEM)')
                            ->rows(6)
                            ->placeholder('-----BEGIN CERTIFICATE-----'),

                        Forms\Components\Placeholder::make('saml_callback')
                            ->label('ACS (Callback) URL')
                            ->content(fn (?SsoProvider $record) => $record?->callbackUrl() ?? 'Save the provider first to see the callback URL'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'saml'),

                Forms\Components\Section::make('OIDC Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('metadata_url')
                            ->label('Discovery URL')
                            ->url()
                            ->maxLength(2000)
                            ->placeholder('https://accounts.google.com')
                            ->helperText('Will auto-append /.well-known/openid-configuration if needed'),

                        Forms\Components\TextInput::make('client_id')
                            ->label('Client ID')
                            ->maxLength(500),

                        Forms\Components\TextInput::make('client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->maxLength(500),

                        Forms\Components\Placeholder::make('oidc_callback')
                            ->label('Redirect (Callback) URL')
                            ->content(fn (?SsoProvider $record) => $record?->callbackUrl() ?? 'Save the provider first to see the callback URL'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'oidc'),

                Forms\Components\Section::make('User Provisioning')
                    ->schema([
                        Forms\Components\Toggle::make('auto_provision')
                            ->label('Auto-create users')
                            ->default(true)
                            ->helperText('Automatically create user accounts on first SSO login'),

                        Forms\Components\Select::make('default_role')
                            ->options([
                                'member' => 'Member',
                                'editor' => 'Editor',
                                'admin' => 'Admin',
                            ])
                            ->default('member')
                            ->helperText('Default organization role for auto-provisioned users'),

                        Forms\Components\TagsInput::make('allowed_domains')
                            ->placeholder('example.com')
                            ->helperText('Restrict login to these email domains (leave empty to allow all)'),

                        Forms\Components\KeyValue::make('claim_mapping')
                            ->label('Claim Mapping (overrides)')
                            ->helperText('Map IdP attribute names to Orkestr fields: email, name, first_name, last_name'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state) => match ($state) {
                        'saml' => 'info',
                        'oidc' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSsoProviders::route('/'),
            'create' => Pages\CreateSsoProvider::route('/create'),
            'edit' => Pages\EditSsoProvider::route('/{record}/edit'),
        ];
    }
}
