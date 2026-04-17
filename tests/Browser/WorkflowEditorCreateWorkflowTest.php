<?php

declare(strict_types=1);

use Pest\Browser\Playwright\Playwright;

it('cria um workflow pela UI do editor e abre o painel AI Builder', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    Playwright::setTimeout(20_000);

    $name = 'Browser WF '.uniqid();

    $page = visit('/workflow-editor');

    $page->assertTitle('Workflow Editor')
        ->assertSee('Workflows')
        ->click('New Workflow')
        ->type('input[placeholder="My Workflow"]', $name)
        ->click('Create');

    $page->assertSee($name);

    $path = parse_url($page->url(), PHP_URL_PATH);
    expect(is_string($path))->toBeTrue();
    expect($path)->toMatch('#^/workflow-editor/\d+$#');

    $page->click('button[title="AI Builder"]')
        ->assertSee('AI Builder');
});
