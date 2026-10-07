<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\RegistrationOffer;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationVerificationRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;

it('rejects invalid submission transport fields without exposing the caller payload', function (string $change) {
    $values = Data::object(Fixtures::wire('submitted-adult'), 'request');
    switch ($change) {
        case 'missing key': unset($values['submission_key']);
            break;
        case 'invalid key': $values['submission_key'] = 'not-uuid';
            break;
        case 'non-v4 key': $values['submission_key'] = '10000000-0000-1000-8000-000000000001';
            break;
        case 'version': $values['offer_version_id'] = 0;
            break;
        case 'ticket': $values['submission_ticket'] = '';
            break;
        case 'website': $values['website'] = ['secret' => 'not-a-string'];
            break;
        case 'email': $values['email'] = 'invalid';
            break;
        case 'remark': $values['remark'] = str_repeat('x', 1001);
            break;
        case 'no people': $values['people'] = [];
            break;
        case 'too many people': $values['people'] = array_fill(0, 3, $values['people'][0]);
            break;
        case 'contact count': $values['people'][0]['contacts'] = array_fill(0, 11, ['type' => 'private_email', 'value' => 'qa@example.test']);
            break;
        case 'contact length': $values['people'][0]['contacts'] = [['type' => 'private_phone', 'value' => str_repeat('1', 191)]];
            break;
        case 'unknown attribute': $values['people'][0]['attributes']['password'] = 'secret-password';
            break;
        case 'choice type': $values['people'][0]['attributes']['service_class_id'] = '70001';
            break;
        case 'boolean type': $values['people'][0]['attributes']['retired'] = 'yes';
            break;
        case 'no participants': $values['participants'] = [];
            break;
        case 'participant index': $values['participants'][0]['person_index'] = 2;
            break;
        case 'option id': $values['participants'][0]['option_ids'] = [0];
            break;
        case 'proof index': $values['proofs'][0]['participant_index'] = 2;
            break;
        case 'proof hash': $values['proofs'][0]['shown_content_hash'] = str_repeat('x', 65);
            break;
        case 'payment': $values['payment'] = ['iban' => 'not-a-contract-field'];
            break;
        default: throw new LogicException('Unknown input mutation.');
    }
    $old = ini_set('zend.exception_ignore_args', '0');
    try {
        try {
            RegistrationSubmission::fromArray($values);
            throw new LogicException('Expected input failure.');
        } catch (InvalidArgumentException $error) {
            expect($error->getPrevious())->toBeNull()->and((string) $error)->not->toContain('anna.qa@example.test', 'synthetic-opaque-submission-ticket', 'secret-password');
            foreach ($error->getTrace() as $frame) {
                if (! str_starts_with($frame['class'] ?? '', 'Lacodix\\MembergySdk\\')) {
                    continue;
                }
                foreach ($frame['args'] ?? [] as $argument) {
                    expect($argument)->not->toBeArray();
                }
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', $old === false ? '1' : $old);
    }
})->with([
    'missing key', 'invalid key', 'non-v4 key', 'version', 'ticket', 'website', 'email', 'remark',
    'no people', 'too many people', 'contact count', 'contact length', 'unknown attribute', 'choice type',
    'boolean type', 'no participants', 'participant index', 'option id', 'proof index', 'proof hash', 'payment',
]);

it('preserves the frozen optional-field semantics and encodes empty attributes as an object', function () {
    $values = Data::object(Fixtures::wire('submitted-adult'), 'request');
    unset($values['email'], $values['remark'], $values['proofs'], $values['website']);
    $values['people'] = [['attributes' => []]];
    $submission = RegistrationSubmission::fromArray($values);
    $body = $submission->toArray();
    expect($body)->not->toHaveKeys(['email', 'remark', 'proofs', 'website'])
        ->and($body['people'][0]['attributes'])->toBeInstanceOf(stdClass::class)
        ->and(json_encode($body, JSON_THROW_ON_ERROR))->toContain('"attributes":{}');
});

it('keeps resume and submission secrets out of debug output', function () {
    $resume = Fixtures::resume();
    $submission = Fixtures::submission();
    $offer = RegistrationOffer::fromArray(Data::object(Fixtures::payload('offer-account'), 'data'));
    ob_start();
    var_dump($resume, $submission, $offer);
    $dump = ob_get_clean();
    expect($dump)->not->toContain(str_repeat('a', 64), 'anna.qa@example.test', 'account.qa@example.test', 'synthetic-opaque-submission-ticket');
});

it('rejects numeric tenant aliases, path traversal and malformed landing tokens locally', function (string $tenant, string $slug) {
    expect(fn () => new ShowRegistrationOfferRequest($tenant, $slug))->toThrow(InvalidArgumentException::class);
})->with([['70001', 'membership'], ['contract-club', '..'], ['contract-club', 'a/b'], ['contract-club', 'a?b']]);

it('requires the real landing token syntax without disclosing malformed token values', function () {
    expect(fn () => new ShowRegistrationVerificationRequest('private-email@example.test'))->toThrow(InvalidArgumentException::class, 'Invalid registration link token.');
});

it('applies schema length bounds to Unicode code points without changing caller values', function () {
    $values = Data::object(Fixtures::wire('submitted-adult'), 'request');
    $values['people'][0]['attributes']['firstname'] = str_repeat('é', 255);
    $values['people'][0]['contacts'] = [['type' => 'private_phone', 'value' => str_repeat('é', 190)]];
    $values['remark'] = str_repeat("é\n", 500);
    // Proof validity remains server-side; its static transport limit is 64 code points.
    $values['proofs'][0]['shown_content_hash'] = str_repeat('é', 64);
    expect(RegistrationSubmission::fromArray($values)->toArray())->toBe($values);
    $values['people'][0]['attributes']['firstname'] .= 'é';
    expect(fn () => RegistrationSubmission::fromArray($values))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid UTF-8 person values before JSON transport without retaining the payload', function () {
    $values = Data::object(Fixtures::wire('submitted-adult'), 'request');
    $values['people'][0]['attributes']['firstname'] = "\xff";
    expect(fn () => RegistrationSubmission::fromArray($values))->toThrow(InvalidArgumentException::class, 'Invalid registration submission input.');
});
