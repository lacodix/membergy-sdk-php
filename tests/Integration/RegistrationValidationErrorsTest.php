<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\RegistrationValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

/** @param array<string, mixed>|string $body */
function mockedRegistrationValidationError(#[SensitiveParameter] array|string $body): RegistrationValidationException
{
    // No captured backend response has nested error keys yet. This is deliberately a mock, not a wire fixture.
    $mock = new MockClient([MockResponse::make($body, 422, ['Content-Type' => 'application/json'])]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', retryInterval: 0);
    $connector->withMockClient($mock);
    try {
        MembergyClient::fromConnector($connector)->registrationApplications()->submit('membership', Fixtures::submission());
    } catch (RegistrationValidationException $error) {
        $mock->assertSentCount(1);
        $mock->assertSent(fn (SubmitRegistrationApplicationRequest $request, Response $response): bool => $request->getMethod()->value === 'POST'
            && $request->resolveEndpoint() === '/tenant/contract-club/registration-offers/membership/applications'
            && $request->body()->all() === Data::object(Fixtures::wire('submitted-adult'), 'request')
            && $response->getPendingRequest()->headers()->get('Authorization') === null);

        return $error;
    }

    throw new LogicException('The mocked registration validation error was not raised.');
}

it('maps schema-listed nested 422 paths at their supported bounds without exposing mocked private messages', function () {
    $schema = Data::object(['schema' => json_decode(file_get_contents(Fixtures::root().'/registration.schema.json') ?: '', true, flags: JSON_THROW_ON_ERROR)], 'schema');
    $definitions = Data::object($schema, 'definitions');
    $fields = [];
    foreach (['SubmissionRequest', 'TokenRequest', 'MagicRequest', 'ResendRequest'] as $name) {
        $fields = [...$fields, ...array_keys(Data::object(Data::object($definitions, $name), 'properties'))];
    }
    $attributes = array_keys(Data::object(Data::object($definitions, 'Attributes'), 'properties'));
    foreach ([0, 1] as $person) {
        $fields[] = "people.{$person}.attributes";
        $fields[] = "people.{$person}.contacts";
        foreach ($attributes as $attribute) {
            $fields[] = "people.{$person}.attributes.{$attribute}";
        }
        foreach ([0, 9] as $contact) {
            foreach (['type', 'value'] as $field) {
                $fields[] = "people.{$person}.contacts.{$contact}.{$field}";
            }
        }
        $fields[] = "participants.{$person}.person_index";
        $fields[] = "participants.{$person}.option_ids";
        foreach ([0, 49] as $option) {
            $fields[] = "participants.{$person}.option_ids.{$option}";
        }
    }
    foreach ([0, 99] as $proof) {
        foreach (['participant_index', 'requirement_id', 'shown_content_hash'] as $field) {
            $fields[] = "proofs.{$proof}.{$field}";
        }
    }
    $fields = array_values(array_unique($fields));
    $message = 'mock-private-email@example.test mock-private-token mock-backend-trace';
    $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    try {
        $error = mockedRegistrationValidationError([
            'message' => $message,
            'errors' => array_fill_keys($fields, [$message]),
            'exception' => $message,
            'trace' => [$message],
        ]);
        expect($error->fields)->toBe($fields)
            ->and($error->status)->toBe(422)
            ->and($error->getMessage())->toBe('The registration input was rejected.')
            ->and($error->getPrevious())->toBeNull();
        foreach (['mock-private-email@example.test', 'mock-private-token', 'mock-backend-trace'] as $private) {
            expect((string) $error)->not->toContain($private);
            expect(json_encode($error, JSON_THROW_ON_ERROR))->not->toContain($private);
        }
    } finally {
        ini_set('zend.exception_ignore_args', $ignoreArgs === false ? '1' : $ignoreArgs);
    }
});

it('drops forged, oversized and out-of-bounds mocked 422 paths', function () {
    $valid = 'people.0.attributes.date_of_birth';
    $hostile = [
        'password', 'trace', 'resume_context', 'people.0.attributes.email', 'people.0.attributes.password',
        'people.0.attributes.firstname.extra', 'people.0.contacts.0.email', 'people.0.contacts.0.value.extra',
        'people.2.attributes.date_of_birth', 'people.-1.attributes.date_of_birth', 'people.01.attributes.date_of_birth',
        'people.*.attributes.date_of_birth', 'people.١.attributes.date_of_birth', 'people..attributes.date_of_birth',
        'people.0.contacts.10.value', 'people.0.contacts.-1.value', 'people.0.contacts.00.value',
        'participants.2.person_index', 'participants.0.email', 'participants.0.option_ids.50',
        'participants.0.option_ids.-1', 'participants.0.option_ids.*', 'participants.0.option_ids.01',
        'proofs.100.shown_content_hash', 'proofs.-1.shown_content_hash', 'proofs.00.shown_content_hash',
        'proofs.0.secret', 'proofs.0.shown_content_hash.extra', 'people/0/attributes/firstname',
        'people.0.attributes.firstname@example.test', "people.0.attributes.firstname\n", "people.0.attributes.firstname\0",
        'people.'.str_repeat('9', 4096).'.attributes.firstname', str_repeat('email', 4096),
    ];
    $errors = array_fill_keys([$valid, ...$hostile], ['mocked confidential validation message']);
    $errors[0] = ['numeric keys are not request paths'];
    $error = mockedRegistrationValidationError(['message' => 'mocked private response', 'errors' => $errors]);

    expect($error->fields)->toBe([$valid])
        ->and($error->getMessage())->toBe('The registration input was rejected.')
        ->and($error->getPrevious())->toBeNull();
});

it('returns a repeated allowlisted path only once from a mocked JSON error object', function () {
    $error = mockedRegistrationValidationError('{"message":"mocked validation","errors":{"people.0.contacts.0.value":["first private message"],"people.0.contacts.0.value":["second private message"]}}');

    expect($error->fields)->toBe(['people.0.contacts.0.value']);
});
