<?php

declare(strict_types=1);

use App\Support\WorkflowFormFieldRules;
use Illuminate\Support\Facades\Validator;

it('rulesForSubmit ignora campos inválidos e gera regras por key', function (): void {
    $rules = WorkflowFormFieldRules::rulesForSubmit([
        ['key' => 'email', 'type' => 'email', 'required' => true],
        ['nope' => true],
    ]);

    expect($rules)->toHaveKey('email');
    expect($rules)->not->toHaveKey('nope');
});

it('valida select com opções CSV', function (): void {
    $field = [
        'key' => 'forma',
        'type' => 'select',
        'required' => true,
        'options' => 'a, b, c',
    ];

    $rules = ['forma' => WorkflowFormFieldRules::rulesForSingleField($field)];

    expect(Validator::make(['forma' => 'a'], $rules)->passes())->toBeTrue();
    expect(Validator::make(['forma' => 'z'], $rules)->passes())->toBeFalse();
});

it('select sem opções cai para string', function (): void {
    $field = [
        'key' => 'forma',
        'type' => 'select',
        'required' => false,
        'options' => '',
    ];

    $rules = ['forma' => WorkflowFormFieldRules::rulesForSingleField($field)];
    expect(Validator::make(['forma' => null], $rules)->passes())->toBeTrue();
});

it('valida choice_cards com valores', function (): void {
    $field = [
        'key' => 'ingresso',
        'type' => 'choice_cards',
        'required' => true,
        'choices' => [
            ['value' => 'v1', 'label' => 'Um'],
            ['value' => 'v2', 'label' => 'Dois'],
        ],
    ];

    $rules = ['ingresso' => WorkflowFormFieldRules::rulesForSingleField($field)];

    expect(Validator::make(['ingresso' => 'v1'], $rules)->passes())->toBeTrue();
    expect(Validator::make(['ingresso' => 'x'], $rules)->passes())->toBeFalse();
});

it('choice_cards sem values cai para string', function (): void {
    $field = [
        'key' => 'ingresso',
        'type' => 'choice_cards',
        'required' => false,
        'choices' => [[], ['value' => '']],
    ];

    $rules = ['ingresso' => WorkflowFormFieldRules::rulesForSingleField($field)];
    expect(Validator::make(['ingresso' => null], $rules)->passes())->toBeTrue();
});

it('boolean required usa accepted; opcional aceita boolean', function (): void {
    $requiredField = ['key' => 'accept', 'type' => 'boolean', 'required' => true];
    $optionalField = ['key' => 'flag', 'type' => 'boolean', 'required' => false];

    $rules1 = ['accept' => WorkflowFormFieldRules::rulesForSingleField($requiredField)];
    $rules2 = ['flag' => WorkflowFormFieldRules::rulesForSingleField($optionalField)];

    expect(Validator::make(['accept' => true], $rules1)->passes())->toBeTrue();
    expect(Validator::make(['accept' => false], $rules1)->passes())->toBeFalse();

    expect(Validator::make(['flag' => false], $rules2)->passes())->toBeTrue();
});

