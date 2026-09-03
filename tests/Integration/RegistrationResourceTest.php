<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\FormDefinition;
use Lacodix\MembergySdk\DataObjects\FormField;
use Lacodix\MembergySdk\DataObjects\FormSubmissionResult;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Forms\ShowRegisterFormRequest;
use Lacodix\MembergySdk\Requests\Forms\SubmitRegisterFormRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

function registrationClient(MockClient $mock): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('hydrates the dynamic registration form and submits its values', function () {
    $mock = new MockClient([
        ShowRegisterFormRequest::class => MockResponse::make(contractFixture('forms/register.json')),
        SubmitRegisterFormRequest::class => MockResponse::make(contractFixture('forms/submitted.json')),
    ]);
    $client = registrationClient($mock);
    $values = [
        'salutation' => 'mr',
        'firstname' => 'Sam',
        'lastname' => 'Taylor',
        'email' => 'sam@example.test',
        'password' => 'Secret-456-Ok',
        'password_confirmation' => 'Secret-456-Ok',
        'gender' => 'male',
        'address_street' => 'River Road 5',
    ];

    $form = $client->registration()->form();
    $result = $client->registration()->submit($values);

    expect($form)->toBeInstanceOf(FormDefinition::class)
        ->and($form->name)->toBe('register')
        ->and($form->fields[0])->toBeInstanceOf(FormField::class)
        ->and($form->fields[0]->type)->toBe('email')
        ->and($form->fields[5]->options[0]->value)->toBe('mr')
        ->and($form->fields[5]->options[0]->label)->toBe('Herr')
        ->and($form->values['firstname'])->toBe('')
        ->and($result)->toBeInstanceOf(FormSubmissionResult::class)
        ->and($result->status)->toBe('submitted');

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ShowRegisterFormRequest
            && $request->resolveEndpoint() === '/tenant/demo/forms/register';
    });
    $mock->assertSent(function (Request $request) use ($values): bool {
        return $request instanceof SubmitRegisterFormRequest
            && $request->resolveEndpoint() === '/tenant/demo/forms/register'
            && $request->body()->all() === $values;
    });
});

it('passes form validation errors through as a typed SDK exception', function () {
    $mock = new MockClient([
        SubmitRegisterFormRequest::class => MockResponse::make(
            contractFixture('forms/validation.json'),
            422,
        ),
    ]);

    try {
        registrationClient($mock)->registration()->submit(['custom_field' => 'invalid']);
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe([
            'custom_field' => ['Unknown form field.'],
        ]);

        return;
    }

    throw new RuntimeException('Expected a typed form validation exception.');
});

it('maps a disabled registration form to the stable SDK not-found exception', function () {
    $mock = new MockClient([
        ShowRegisterFormRequest::class => MockResponse::make(
            contractFixture('forms/not-found.json'),
            404,
        ),
    ]);

    registrationClient($mock)->registration()->form();
})->throws(ResourceNotFoundException::class, 'Registration form not found.');
