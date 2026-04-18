<?php

use App\Models\Skill;
use App\Services\PromptLinter;

beforeEach(function () {
    $this->linter = new PromptLinter;
});

it('flags missing_summary when summary is empty', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate a structured summary of the input document.',
        'summary' => '',
        'body' => 'You are a helpful assistant.',
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->toContain('missing_summary');
});

it('does not flag missing_summary when summary is present', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate a structured summary of the input document.',
        'summary' => 'Summarizes documents.',
        'body' => 'You are a helpful assistant.',
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->not->toContain('missing_summary');
});

it('flags missing_description when description is empty', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => '',
        'summary' => 'Does a thing.',
        'body' => 'You are a helpful assistant.',
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->toContain('missing_description');
});

it('flags missing_description when description contains vague words', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Handles various stuff for the user.',
        'summary' => 'x',
        'body' => 'You are a helpful assistant.',
    ]);

    $issues = $this->linter->lintSkill($skill);
    $descriptionIssues = array_filter($issues, fn ($i) => $i['rule'] === 'missing_description');

    expect($descriptionIssues)->not->toBeEmpty();
});

it('flags missing_description when description lacks action verb', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'A specific helper that works on user input nicely.',
        'summary' => 'x',
        'body' => 'You are a helpful assistant.',
    ]);

    $messages = array_map(
        fn ($i) => $i['message'],
        array_filter($this->linter->lintSkill($skill), fn ($i) => $i['rule'] === 'missing_description')
    );

    expect(implode(' ', $messages))->toContain('actionable verb');
});

it('does not flag missing_description when description is specific and actionable', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate a structured summary of technical documents for review.',
        'summary' => 'x',
        'body' => 'You are a helpful assistant.',
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->not->toContain('missing_description');
});

it('flags no_progressive_disclosure when long body has no headings', function () {
    $body = str_repeat('This is a long instruction without any section headings at all. ', 20);

    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate structured output from input data.',
        'summary' => 'x',
        'body' => $body,
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect(strlen($body))->toBeGreaterThan(500)
        ->and($rules)->toContain('no_progressive_disclosure');
});

it('does not flag no_progressive_disclosure when body has ## heading in first 40 lines', function () {
    $body = "## Overview\n\n" . str_repeat('Long detailed instruction content goes here on this line. ', 20);

    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate structured output from input data.',
        'summary' => 'x',
        'body' => $body,
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->not->toContain('no_progressive_disclosure');
});

it('does not flag no_progressive_disclosure when body is short', function () {
    $skill = new Skill([
        'name' => 'Test',
        'description' => 'Generate structured output from input data.',
        'summary' => 'x',
        'body' => 'Short body with no headings.',
    ]);

    $rules = array_column($this->linter->lintSkill($skill), 'rule');

    expect($rules)->not->toContain('no_progressive_disclosure');
});
