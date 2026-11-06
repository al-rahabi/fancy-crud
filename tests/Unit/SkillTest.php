<?php

declare(strict_types=1);

use FancyCrud\FancyCrudServiceProvider;
use Illuminate\Support\ServiceProvider;

it('ships the skill with valid frontmatter', function () {
    $skill = file_get_contents(__DIR__.'/../../resources/skills/fancy-crud/SKILL.md');

    expect($skill)->toStartWith("---\nname: fancy-crud\ndescription: ")
        ->and(file_exists(__DIR__.'/../../resources/skills/fancy-crud/references/templates.md'))->toBeTrue();
});

it('links every reference the skill mentions', function () {
    $dir = __DIR__.'/../../resources/skills/fancy-crud/';
    preg_match_all('/\]\((references\/[^)]+)\)/', file_get_contents($dir.'SKILL.md'), $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $path) {
        expect(file_exists($dir.$path))->toBeTrue();
    }
});

it('can be published to .claude/skills with the fancy-crud-skills tag', function () {
    $paths = ServiceProvider::pathsToPublish(FancyCrudServiceProvider::class, 'fancy-crud-skills');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(base_path('.claude/skills/fancy-crud'))
        ->and(is_dir(array_key_first($paths)))->toBeTrue();
});
