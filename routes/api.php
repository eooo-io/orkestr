<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\SkillsShController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\SkillGenerateController;
use App\Http\Controllers\SkillTestController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Projects
Route::get('/projects', [ProjectController::class, 'index']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::get('/projects/{project}', [ProjectController::class, 'show']);
Route::put('/projects/{project}', [ProjectController::class, 'update']);
Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
Route::post('/projects/{project}/scan', [ProjectController::class, 'scan']);
Route::post('/projects/{project}/sync', [ProjectController::class, 'sync']);
Route::post('/projects/{project}/sync/preview', [ProjectController::class, 'syncPreview']);
Route::get('/projects/{project}/git-log', [ProjectController::class, 'gitLog']);
Route::get('/projects/{project}/git-diff', [ProjectController::class, 'gitDiff']);

// Skills (nested under project for create/index)
Route::get('/projects/{project}/skills', [SkillController::class, 'index']);
Route::post('/projects/{project}/skills', [SkillController::class, 'store']);

// Skills (standalone for show/update/delete/duplicate)
Route::get('/skills/{skill}', [SkillController::class, 'show']);
Route::put('/skills/{skill}', [SkillController::class, 'update']);
Route::delete('/skills/{skill}', [SkillController::class, 'destroy']);
Route::post('/skills/{skill}/duplicate', [SkillController::class, 'duplicate']);
Route::get('/skills/{skill}/lint', [SkillController::class, 'lint']);

// Live Test Runner (SSE)
Route::post('/skills/{skill}/test', SkillTestController::class);
Route::post('/playground', [SkillTestController::class, 'playground']);

// AI Skill Generation
Route::post('/skills/generate', SkillGenerateController::class);

// Versions
Route::get('/skills/{skill}/versions', [VersionController::class, 'index']);
Route::get('/skills/{skill}/versions/{versionNumber}', [VersionController::class, 'show']);
Route::post('/skills/{skill}/versions/{versionNumber}/restore', [VersionController::class, 'restore']);

// Tags
Route::get('/tags', [TagController::class, 'index']);
Route::post('/tags', [TagController::class, 'store']);
Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

// Search
Route::get('/search', SearchController::class);

// Library
Route::get('/library', [LibraryController::class, 'index']);
Route::post('/library/{librarySkill}/import', [LibraryController::class, 'import']);

// Skills.sh
Route::post('/skills-sh/discover', [SkillsShController::class, 'discover']);
Route::post('/skills-sh/preview', [SkillsShController::class, 'preview']);
Route::post('/skills-sh/import', [SkillsShController::class, 'import']);

// Agents
Route::get('/agents', [AgentController::class, 'index']);
Route::get('/projects/{project}/agents', [AgentController::class, 'projectAgents']);
Route::put('/projects/{project}/agents/{agent}/toggle', [AgentController::class, 'toggle']);
Route::put('/projects/{project}/agents/{agent}/instructions', [AgentController::class, 'updateInstructions']);
Route::put('/projects/{project}/agents/{agent}/skills', [AgentController::class, 'assignSkills']);
Route::get('/projects/{project}/agents/{agent}/compose', [AgentController::class, 'compose']);
Route::get('/projects/{project}/agents/compose', [AgentController::class, 'composeAll']);

// Bundles (Export/Import)
Route::post('/projects/{project}/export', [BundleController::class, 'export']);
Route::post('/projects/{project}/import-bundle', [BundleController::class, 'import']);

// Settings
Route::get('/settings', SettingsController::class);
