<?php

use App\Models\SkillEvalPrompt;
use App\Services\EvalScoring\KeywordScorer;
use App\Services\EvalScoring\LlmJudgeScorer;
use App\Services\EvalScoring\ScorerFactory;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\LLMProviderInterface;

function makePrompt(string $expected = '', string $promptText = 'Tell me about widgets'): SkillEvalPrompt
{
    return new SkillEvalPrompt([
        'id' => 1,
        'eval_suite_id' => 1,
        'prompt' => $promptText,
        'expected_behavior' => $expected,
        'grading_criteria' => ['Must mention widgets'],
        'sort_order' => 1,
    ]);
}

it('KeywordScorer: returns 0 when expected_behavior is empty', function () {
    $scorer = new KeywordScorer();
    $result = $scorer->score(makePrompt(''), 'any response');

    expect($result['score'])->toBe(0)
        ->and($result['signals']['scorer'])->toBe('keyword');
});

it('KeywordScorer: returns 0 when response is empty', function () {
    $scorer = new KeywordScorer();
    $result = $scorer->score(makePrompt('Must mention summarization and bullets'), '');

    expect($result['score'])->toBe(0);
});

it('KeywordScorer: returns 100 when all expected keywords present', function () {
    $scorer = new KeywordScorer();
    $result = $scorer->score(
        makePrompt('Response must include widgets and gears'),
        'The widgets interlock with the gears precisely.',
    );

    expect($result['score'])->toBe(100)
        ->and($result['signals']['matched'])->toContain('widgets')
        ->and($result['signals']['matched'])->toContain('gears')
        ->and($result['signals']['missing'])->toBeEmpty();
});

it('KeywordScorer: returns partial score when some keywords missing', function () {
    $scorer = new KeywordScorer();
    $result = $scorer->score(
        makePrompt('Response must mention widgets gears sprockets cogs'),
        'The widgets and gears are here.',
    );

    expect($result['score'])->toBeGreaterThan(0)
        ->and($result['score'])->toBeLessThan(100)
        ->and($result['signals']['missing'])->not->toBeEmpty();
});

it('KeywordScorer: stopwords are ignored', function () {
    $scorer = new KeywordScorer();
    // 'the', 'and', 'of' are stopwords, leaving just 'widgets'
    $result = $scorer->score(
        makePrompt('The response must have widgets and the like of it'),
        'widgets are mentioned.',
    );

    expect($result['signals']['expected_keywords'])->toContain('widgets')
        ->and($result['signals']['expected_keywords'])->not->toContain('the')
        ->and($result['signals']['expected_keywords'])->not->toContain('and');
});

function fakeProvider(string $textResponse): LLMProviderInterface
{
    return new class ($textResponse) implements LLMProviderInterface {
        public function __construct(protected string $response) {}

        public function chat(string $systemPrompt, array $messages, string $model, int $maxTokens, array $tools = []): array
        {
            return [
                'content' => [['type' => 'text', 'text' => $this->response]],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            ];
        }

        public function stream(string $systemPrompt, array $messages, string $model, int $maxTokens): \Generator
        {
            yield ['type' => 'text', 'text' => $this->response];
            yield ['type' => 'done'];
        }

        public function models(): array
        {
            return [];
        }
    };
}

function fakeFactory(LLMProviderInterface $provider): LLMProviderFactory
{
    return new class ($provider) extends LLMProviderFactory {
        public function __construct(protected LLMProviderInterface $provider) {}

        public function make(string $model): LLMProviderInterface
        {
            return $this->provider;
        }
    };
}

it('LlmJudgeScorer: parses valid JSON judge output', function () {
    $factory = fakeFactory(fakeProvider('{"score": 85, "reasoning": "Clear match."}'));
    $scorer = new LlmJudgeScorer($factory);

    $result = $scorer->score(makePrompt('Mention widgets'), 'widgets and gears');

    expect($result['score'])->toBe(85)
        ->and($result['reasoning'])->toBe('Clear match.')
        ->and($result['signals']['scorer'])->toBe('llm_judge');
});

it('LlmJudgeScorer: handles unparseable judge output gracefully', function () {
    $factory = fakeFactory(fakeProvider('not even close to JSON'));
    $scorer = new LlmJudgeScorer($factory);

    $result = $scorer->score(makePrompt('Mention widgets'), 'some response');

    expect($result['score'])->toBe(0)
        ->and($result['reasoning'])->toContain('unparseable');
});

it('ScorerFactory resolves keyword by default', function () {
    $factory = app(ScorerFactory::class);
    $suite = new \App\Models\SkillEvalSuite(['scorer' => 'keyword']);

    expect($factory->forSuite($suite))->toBeInstanceOf(KeywordScorer::class);
});

it('ScorerFactory resolves llm_judge when suite asks for it', function () {
    $factory = app(ScorerFactory::class);
    $suite = new \App\Models\SkillEvalSuite(['scorer' => 'llm_judge']);

    expect($factory->forSuite($suite))->toBeInstanceOf(LlmJudgeScorer::class);
});
