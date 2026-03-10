<?php

use App\Services\SkillFileParser;

beforeEach(function () {
    $this->parser = new SkillFileParser;
});

it('parses a skill file with all fields', function () {
    $content = <<<'MD'
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-20250514
max_tokens: 1000
tools: []
---

You are a precise document summarizer.
MD;

    $result = $this->parser->parseContent($content);

    expect($result['frontmatter'])->toMatchArray([
        'id' => 'summarize-doc',
        'name' => 'Summarize Document',
        'description' => 'Summarizes any document to key bullet points',
        'tags' => ['summarization', 'documents'],
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 1000,
        'tools' => [],
    ]);

    expect($result['body'])->toBe('You are a precise document summarizer.');
});

it('parses a skill file with only required fields', function () {
    $content = <<<'MD'
---
id: simple
name: Simple Skill
---

Do something simple.
MD;

    $result = $this->parser->parseContent($content);

    expect($result['frontmatter'])->toBe(['id' => 'simple', 'name' => 'Simple Skill']);
    expect($result['body'])->toBe('Do something simple.');
});

it('handles content with no frontmatter', function () {
    $content = 'Just a body with no frontmatter.';

    $result = $this->parser->parseContent($content);

    expect($result['frontmatter'])->toBe([]);
    expect($result['body'])->toBe('Just a body with no frontmatter.');
});

it('round-trips render then parse to identical output', function () {
    $frontmatter = [
        'id' => 'round-trip',
        'name' => 'Round Trip Test',
        'description' => 'Tests round-trip parsing',
        'tags' => ['test', 'parsing'],
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 2000,
    ];
    $body = "You are a helpful assistant.\n\nBe concise and clear.";

    $rendered = $this->parser->renderFile($frontmatter, $body);
    $parsed = $this->parser->parseContent($rendered);

    expect($parsed['frontmatter'])->toBe($frontmatter);
    expect($parsed['body'])->toBe($body);
});

it('validates frontmatter with missing required fields', function () {
    $errors = $this->parser->validateFrontmatter([]);

    expect($errors)->toContain('Missing required field: id');
    expect($errors)->toContain('Missing required field: name');
});

it('validates frontmatter with all required fields', function () {
    $errors = $this->parser->validateFrontmatter(['id' => 'test', 'name' => 'Test']);

    expect($errors)->toBeEmpty();
});

it('parses a file from disk', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'skill_');
    file_put_contents($tmpFile, "---\nid: disk-test\nname: Disk Test\n---\n\nBody from disk.");

    $result = $this->parser->parseFile($tmpFile);

    expect($result['frontmatter']['id'])->toBe('disk-test');
    expect($result['body'])->toBe('Body from disk.');

    unlink($tmpFile);
});
