<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Execution\CostCalculator;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\LLMProviderInterface;
use App\Services\LLM\ModelRouter;
use App\Services\LLM\ProviderHealthMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Model Routing Test', 'path' => '/tmp/model-routing']);
    $this->agent = Agent::create([
        'name' => 'Routing Agent',
        'slug' => 'routing-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// --- Migration verification ---

test('migration adds model routing and attribution columns', function () {
    // If we got here with RefreshDatabase, the migration ran successfully.
    // Verify columns exist by inserting records with the new fields.
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    expect($run->model_used)->toBe('claude-sonnet-4-6');
});

// --- ExecutionStep model attribution ---

test('ExecutionStep can store model_used and model_requested', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'reason',
        'model_used' => 'claude-sonnet-4-6',
        'model_requested' => 'claude-opus-4-6',
    ]);

    expect($step->model_used)->toBe('claude-sonnet-4-6');
    expect($step->model_requested)->toBe('claude-opus-4-6');

    // Verify persistence
    $step->refresh();
    expect($step->model_used)->toBe('claude-sonnet-4-6');
    expect($step->model_requested)->toBe('claude-opus-4-6');
});

test('ExecutionStep model fields are nullable', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'perceive',
    ]);

    expect($step->model_used)->toBeNull();
    expect($step->model_requested)->toBeNull();
});

// --- ExecutionRun model attribution ---

test('ExecutionRun can store model_used', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'model_used' => 'gpt-5.4',
    ]);

    expect($run->model_used)->toBe('gpt-5.4');

    $run->refresh();
    expect($run->model_used)->toBe('gpt-5.4');
});

// --- Agent model routing fields ---

test('Agent can store fallback_models as array and routing_strategy', function () {
    $agent = Agent::create([
        'name' => 'Fallback Agent',
        'slug' => 'fallback-agent',
        'role' => 'assistant',
        'model' => 'claude-opus-4-6',
        'base_instructions' => 'Test.',
        'fallback_models' => ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
        'routing_strategy' => 'cost_optimized',
    ]);

    expect($agent->fallback_models)->toBe(['claude-sonnet-4-6', 'claude-haiku-4-5-20251001']);
    expect($agent->routing_strategy)->toBe('cost_optimized');

    $agent->refresh();
    expect($agent->fallback_models)->toBe(['claude-sonnet-4-6', 'claude-haiku-4-5-20251001']);
    expect($agent->routing_strategy)->toBe('cost_optimized');
});

test('Agent routing_strategy defaults to default', function () {
    $agent = Agent::create([
        'name' => 'Default Agent',
        'slug' => 'default-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);

    expect($agent->routing_strategy)->toBe('default');
});

// --- WorkflowStep model override ---

test('WorkflowStep can store model_override', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test Workflow',
        'slug' => 'test-workflow',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'agent_id' => $this->agent->id,
        'type' => 'agent',
        'name' => 'Step 1',
        'position_x' => 0,
        'position_y' => 0,
        'sort_order' => 1,
        'model_override' => 'gpt-5.4',
    ]);

    expect($step->model_override)->toBe('gpt-5.4');

    $step->refresh();
    expect($step->model_override)->toBe('gpt-5.4');
});

test('WorkflowStep model_override is null when not set', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Null Override Workflow',
        'slug' => 'null-override-workflow',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'agent_id' => $this->agent->id,
        'type' => 'agent',
        'name' => 'Step No Override',
        'position_x' => 0,
        'position_y' => 0,
        'sort_order' => 1,
    ]);

    expect($step->model_override)->toBeNull();

    $step->refresh();
    expect($step->model_override)->toBeNull();
});

test('WorkflowStep model_override can be updated', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Update Override Workflow',
        'slug' => 'update-override-workflow',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'agent_id' => $this->agent->id,
        'type' => 'agent',
        'name' => 'Step Update Override',
        'position_x' => 0,
        'position_y' => 0,
        'sort_order' => 1,
        'model_override' => 'claude-sonnet-4-6',
    ]);

    expect($step->model_override)->toBe('claude-sonnet-4-6');

    $step->update(['model_override' => 'gpt-5.4']);
    $step->refresh();
    expect($step->model_override)->toBe('gpt-5.4');

    $step->update(['model_override' => null]);
    $step->refresh();
    expect($step->model_override)->toBeNull();
});

// --- CostCalculator uses model_used ---

test('CostCalculator aggregateStats uses model_used when available', function () {
    $run1 = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'model_used' => 'gpt-5.4',
        'total_tokens' => 1000,
        'total_cost_microcents' => 500,
        'total_duration_ms' => 200,
    ]);

    $run2 = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'model_used' => null, // Should fall back to agent model
        'total_tokens' => 2000,
        'total_cost_microcents' => 1000,
        'total_duration_ms' => 300,
    ]);

    $calculator = new CostCalculator;
    $stats = $calculator->aggregateStats([$run1, $run2]);

    expect($stats['total_runs'])->toBe(2);
    expect($stats['total_tokens'])->toBe(3000);

    // run1 should be grouped under 'gpt-5.4' (from model_used)
    expect($stats['by_model'])->toHaveKey('gpt-5.4');
    expect($stats['by_model']['gpt-5.4']['tokens'])->toBe(1000);

    // run2 should fall back to agent model 'claude-sonnet-4-6'
    expect($stats['by_model'])->toHaveKey('claude-sonnet-4-6');
    expect($stats['by_model']['claude-sonnet-4-6']['tokens'])->toBe(2000);
});

// --- ProviderHealthMonitor ---

test('ProviderHealthMonitor records success and returns healthy', function () {
    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');

    $monitor->recordSuccess('anthropic', 150);

    $status = $monitor->getStatus('anthropic');
    expect($status['status'])->toBe('healthy');
    expect($status['error_count'])->toBe(0);
    expect($status['avg_latency_ms'])->toBe(150);
    expect($status['last_success_at'])->not->toBeNull();
    expect($status['last_error'])->toBeNull();
});

test('ProviderHealthMonitor marks degraded after 3 failures', function () {
    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('openai');

    $monitor->recordFailure('openai', 'timeout');
    $monitor->recordFailure('openai', 'timeout');
    $monitor->recordFailure('openai', 'timeout');

    $status = $monitor->getStatus('openai');
    expect($status['status'])->toBe('degraded');
    expect($status['error_count'])->toBe(3);
    expect($status['last_error'])->toBe('timeout');
});

test('ProviderHealthMonitor marks down after 5 failures', function () {
    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('gemini');

    for ($i = 0; $i < 5; $i++) {
        $monitor->recordFailure('gemini', 'connection refused');
    }

    $status = $monitor->getStatus('gemini');
    expect($status['status'])->toBe('down');
    expect($status['error_count'])->toBe(5);
});

test('ProviderHealthMonitor resets on success after failures', function () {
    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('ollama');

    $monitor->recordFailure('ollama', 'error 1');
    $monitor->recordFailure('ollama', 'error 2');
    $monitor->recordFailure('ollama', 'error 3');

    expect($monitor->getStatus('ollama')['status'])->toBe('degraded');

    $monitor->recordSuccess('ollama', 100);

    $status = $monitor->getStatus('ollama');
    expect($status['status'])->toBe('healthy');
    expect($status['error_count'])->toBe(0);
    expect($status['last_error'])->toBeNull();
});

test('isHealthy returns false when down', function () {
    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');

    for ($i = 0; $i < 5; $i++) {
        $monitor->recordFailure('anthropic', 'server error');
    }

    expect($monitor->isHealthy('anthropic'))->toBeFalse();
    expect($monitor->isDegraded('anthropic'))->toBeFalse(); // down, not degraded
});

test('GET /api/provider-health returns provider statuses', function () {
    $monitor = app(ProviderHealthMonitor::class);

    // Reset all providers to clean state
    foreach (['anthropic', 'openai', 'gemini', 'ollama'] as $provider) {
        $monitor->reset($provider);
    }

    $monitor->recordSuccess('anthropic', 200);
    $monitor->recordFailure('openai', 'rate limited');

    $response = $this->getJson('/api/provider-health');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'anthropic' => ['status', 'error_count', 'last_error', 'last_success_at', 'avg_latency_ms', 'updated_at'],
            'openai' => ['status', 'error_count', 'last_error', 'last_success_at', 'avg_latency_ms', 'updated_at'],
            'gemini' => ['status', 'error_count', 'last_error', 'last_success_at', 'avg_latency_ms', 'updated_at'],
            'ollama' => ['status', 'error_count', 'last_error', 'last_success_at', 'avg_latency_ms', 'updated_at'],
        ],
    ]);
    $response->assertJsonPath('data.anthropic.status', 'healthy');
    $response->assertJsonPath('data.openai.error_count', 1);
});

// --- CostCalculator cost tiers ---

test('CostCalculator getModelCostTier returns economy for cheap models', function () {
    expect(CostCalculator::getModelCostTier('claude-haiku-4-5-20251001'))->toBe('economy');
    expect(CostCalculator::getModelCostTier('gemini-3-flash'))->toBe('economy');
    expect(CostCalculator::getModelCostTier('gpt-5-mini'))->toBe('economy');
    expect(CostCalculator::getModelCostTier('gemini-3.1-flash-lite'))->toBe('economy');
});

test('CostCalculator getModelCostTier returns standard for mid-tier models', function () {
    expect(CostCalculator::getModelCostTier('claude-sonnet-4-6'))->toBe('standard');
    expect(CostCalculator::getModelCostTier('gpt-5.4'))->toBe('standard');
    expect(CostCalculator::getModelCostTier('gemini-3.1-pro'))->toBe('standard');
});

test('CostCalculator getModelCostTier returns premium for expensive models', function () {
    expect(CostCalculator::getModelCostTier('claude-opus-4-6'))->toBe('premium');
    expect(CostCalculator::getModelCostTier('o3'))->toBe('premium');
});

test('CostCalculator getModelCostTier returns standard for unknown models', function () {
    expect(CostCalculator::getModelCostTier('unknown-model'))->toBe('standard');
});

// --- CostCalculator rankByCost ---

test('CostCalculator rankByCost returns models cheapest first', function () {
    $models = ['claude-opus-4-6', 'gemini-3-flash', 'claude-sonnet-4-6'];
    $ranked = CostCalculator::rankByCost($models);

    expect($ranked[0])->toBe('gemini-3-flash');       // input: 2
    expect($ranked[1])->toBe('claude-sonnet-4-6');    // input: 30
    expect($ranked[2])->toBe('claude-opus-4-6');      // input: 150
});

test('CostCalculator getPricing returns correct pricing for known model', function () {
    $pricing = CostCalculator::getPricing('claude-sonnet-4-6');
    expect($pricing)->toBe(['input' => 30, 'output' => 150]);
});

test('CostCalculator getPricing returns default pricing for unknown model', function () {
    $pricing = CostCalculator::getPricing('unknown-model');
    expect($pricing)->toBe(['input' => 30, 'output' => 150]);
});

// --- CostOptimizedRouter ---

test('CostOptimizedRouter selectModels with default keeps original order', function () {
    $router = new \App\Services\LLM\CostOptimizedRouter;

    $result = $router->selectModels('claude-opus-4-6', ['claude-sonnet-4-6', 'gemini-3-flash'], 'default');

    expect($result[0])->toBe('claude-opus-4-6');
    expect($result[1])->toBe('claude-sonnet-4-6');
    expect($result[2])->toBe('gemini-3-flash');
});

test('CostOptimizedRouter selectModels with cost_optimized sorts by cost', function () {
    $router = new \App\Services\LLM\CostOptimizedRouter;

    $result = $router->selectModels('claude-opus-4-6', ['claude-sonnet-4-6', 'gemini-3-flash'], 'cost_optimized');

    expect($result[0])->toBe('gemini-3-flash');       // cheapest
    expect($result[1])->toBe('claude-sonnet-4-6');    // mid
    expect($result[2])->toBe('claude-opus-4-6');      // most expensive
});

test('CostOptimizedRouter selectModels with performance sorts most expensive first', function () {
    $router = new \App\Services\LLM\CostOptimizedRouter;

    $result = $router->selectModels('gemini-3-flash', ['claude-sonnet-4-6', 'claude-opus-4-6'], 'performance');

    expect($result[0])->toBe('claude-opus-4-6');      // most expensive
    expect($result[1])->toBe('claude-sonnet-4-6');    // mid
    expect($result[2])->toBe('gemini-3-flash');       // cheapest
});

test('CostOptimizedRouter deduplicates models', function () {
    $router = new \App\Services\LLM\CostOptimizedRouter;

    $result = $router->selectModels('claude-sonnet-4-6', ['claude-sonnet-4-6', 'gemini-3-flash'], 'default');

    expect($result)->toHaveCount(2);
    expect($result)->toContain('claude-sonnet-4-6');
    expect($result)->toContain('gemini-3-flash');
});

// --- ModelRouter ---

test('ModelRouter tries primary model first and returns response with model_used', function () {
    $mockProvider = Mockery::mock(LLMProviderInterface::class);
    $mockProvider->shouldReceive('chat')
        ->once()
        ->with('system', [['role' => 'user', 'content' => 'hello']], 'claude-sonnet-4-6', 1024, [])
        ->andReturn([
            'content' => [['type' => 'text', 'text' => 'Hi']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]);

    $factory = Mockery::mock(LLMProviderFactory::class);
    $factory->shouldReceive('providerName')->with('claude-sonnet-4-6')->andReturn('anthropic');
    $factory->shouldReceive('make')->with('claude-sonnet-4-6')->andReturn($mockProvider);

    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');

    $router = new ModelRouter($factory, $monitor);
    $response = $router->chatWithFallback(
        'system',
        [['role' => 'user', 'content' => 'hello']],
        'claude-sonnet-4-6',
        1024,
    );

    expect($response['model_used'])->toBe('claude-sonnet-4-6');
    expect($response['content'][0]['text'])->toBe('Hi');
});

test('ModelRouter falls back when primary model throws exception', function () {
    $failingProvider = Mockery::mock(LLMProviderInterface::class);
    $failingProvider->shouldReceive('chat')->once()->andThrow(new \RuntimeException('API down'));

    $fallbackProvider = Mockery::mock(LLMProviderInterface::class);
    $fallbackProvider->shouldReceive('chat')
        ->once()
        ->andReturn([
            'content' => [['type' => 'text', 'text' => 'Fallback response']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]);

    $factory = Mockery::mock(LLMProviderFactory::class);
    $factory->shouldReceive('providerName')->with('claude-opus-4-6')->andReturn('anthropic');
    $factory->shouldReceive('providerName')->with('gpt-5.4')->andReturn('openai');
    $factory->shouldReceive('make')->with('claude-opus-4-6')->andReturn($failingProvider);
    $factory->shouldReceive('make')->with('gpt-5.4')->andReturn($fallbackProvider);

    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');
    $monitor->reset('openai');

    $router = new ModelRouter($factory, $monitor);
    $response = $router->chatWithFallback(
        'system',
        [['role' => 'user', 'content' => 'test']],
        'claude-opus-4-6',
        1024,
        [],
        ['gpt-5.4'],
    );

    expect($response['model_used'])->toBe('gpt-5.4');
    expect($response['content'][0]['text'])->toBe('Fallback response');
});

test('ModelRouter skips unhealthy providers in fallback chain', function () {
    $failingProvider = Mockery::mock(LLMProviderInterface::class);
    $failingProvider->shouldReceive('chat')->once()->andThrow(new \RuntimeException('Primary down'));

    $healthyProvider = Mockery::mock(LLMProviderInterface::class);
    $healthyProvider->shouldReceive('chat')
        ->once()
        ->andReturn([
            'content' => [['type' => 'text', 'text' => 'Gemini response']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
        ]);

    $factory = Mockery::mock(LLMProviderFactory::class);
    $factory->shouldReceive('providerName')->with('claude-sonnet-4-6')->andReturn('anthropic');
    $factory->shouldReceive('providerName')->with('gpt-5.4')->andReturn('openai');
    $factory->shouldReceive('providerName')->with('gemini-3-flash')->andReturn('gemini');
    $factory->shouldReceive('make')->with('claude-sonnet-4-6')->andReturn($failingProvider);
    // gpt-5.4 should NOT be called because openai is down
    $factory->shouldReceive('make')->with('gemini-3-flash')->andReturn($healthyProvider);

    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');
    $monitor->reset('gemini');

    // Mark openai as down (5 failures)
    $monitor->reset('openai');
    for ($i = 0; $i < 5; $i++) {
        $monitor->recordFailure('openai', 'server error');
    }

    $router = new ModelRouter($factory, $monitor);
    $response = $router->chatWithFallback(
        'system',
        [['role' => 'user', 'content' => 'test']],
        'claude-sonnet-4-6',
        1024,
        [],
        ['gpt-5.4', 'gemini-3-flash'],
    );

    expect($response['model_used'])->toBe('gemini-3-flash');
});

test('ModelRouter throws when all models fail', function () {
    $failingProvider1 = Mockery::mock(LLMProviderInterface::class);
    $failingProvider1->shouldReceive('chat')->once()->andThrow(new \RuntimeException('Primary failed'));

    $failingProvider2 = Mockery::mock(LLMProviderInterface::class);
    $failingProvider2->shouldReceive('chat')->once()->andThrow(new \RuntimeException('Fallback failed'));

    $factory = Mockery::mock(LLMProviderFactory::class);
    $factory->shouldReceive('providerName')->with('claude-sonnet-4-6')->andReturn('anthropic');
    $factory->shouldReceive('providerName')->with('gpt-5.4')->andReturn('openai');
    $factory->shouldReceive('make')->with('claude-sonnet-4-6')->andReturn($failingProvider1);
    $factory->shouldReceive('make')->with('gpt-5.4')->andReturn($failingProvider2);

    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');
    $monitor->reset('openai');

    $router = new ModelRouter($factory, $monitor);

    expect(fn () => $router->chatWithFallback(
        'system',
        [['role' => 'user', 'content' => 'test']],
        'claude-sonnet-4-6',
        1024,
        [],
        ['gpt-5.4'],
    ))->toThrow(\RuntimeException::class, 'Fallback failed');
});

test('ModelRouter records health on success and failure', function () {
    $failingProvider = Mockery::mock(LLMProviderInterface::class);
    $failingProvider->shouldReceive('chat')->once()->andThrow(new \RuntimeException('API error'));

    $successProvider = Mockery::mock(LLMProviderInterface::class);
    $successProvider->shouldReceive('chat')
        ->once()
        ->andReturn([
            'content' => [['type' => 'text', 'text' => 'OK']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
        ]);

    $factory = Mockery::mock(LLMProviderFactory::class);
    $factory->shouldReceive('providerName')->with('claude-sonnet-4-6')->andReturn('anthropic');
    $factory->shouldReceive('providerName')->with('gpt-5.4')->andReturn('openai');
    $factory->shouldReceive('make')->with('claude-sonnet-4-6')->andReturn($failingProvider);
    $factory->shouldReceive('make')->with('gpt-5.4')->andReturn($successProvider);

    $monitor = app(ProviderHealthMonitor::class);
    $monitor->reset('anthropic');
    $monitor->reset('openai');

    $router = new ModelRouter($factory, $monitor);
    $router->chatWithFallback(
        'system',
        [['role' => 'user', 'content' => 'test']],
        'claude-sonnet-4-6',
        1024,
        [],
        ['gpt-5.4'],
    );

    // Anthropic should have recorded a failure
    $anthropicStatus = $monitor->getStatus('anthropic');
    expect($anthropicStatus['error_count'])->toBe(1);
    expect($anthropicStatus['last_error'])->toBe('API error');

    // OpenAI should have recorded a success
    $openaiStatus = $monitor->getStatus('openai');
    expect($openaiStatus['status'])->toBe('healthy');
    expect($openaiStatus['error_count'])->toBe(0);
    expect($openaiStatus['avg_latency_ms'])->toBeGreaterThanOrEqual(0);
});
