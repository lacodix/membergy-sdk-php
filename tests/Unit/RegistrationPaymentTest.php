<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\RegistrationOffer;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationPayment;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Enums\RegistrationPaymentMethodType;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Exceptions\RegistrationValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

it('hydrates the public payment presentation including nullable mandate metadata', function () {
    // Synthetic consumer inputs following RegistrationOfferController; not captured responses.
    $payment = RegistrationPayment::fromArray([
        'required_if_billable' => true,
        'methods' => [
            ['id' => 11, 'title' => 'SEPA', 'type' => 'directdebit'],
            ['id' => 12, 'title' => 'Überweisung', 'type' => 'banktransfer'],
        ],
        'mandate_text' => null, 'mandate_text_version' => null,
    ]);
    expect($payment->requiredIfBillable)->toBeTrue()
        ->and($payment->methods[0]->type)->toBe(RegistrationPaymentMethodType::DirectDebit)
        ->and($payment->methods[1]->type)->toBe(RegistrationPaymentMethodType::BankTransfer)
        ->and($payment->mandateText)->toBeNull()
        ->and($payment->mandateTextVersion)->toBeNull();
});

it('hydrates payment from the actual committed backend offer recording', function () {
    $offer = RegistrationOffer::fromArray(Data::object(Fixtures::payload('offer-guest'), 'data'));
    expect($offer->payment->requiredIfBillable)->toBeTrue()
        ->and($offer->payment->methods[0]->id)->toBe(70001)
        ->and($offer->payment->methods[0]->title)->toBe('Contract bank transfer')
        ->and($offer->payment->methods[0]->type)->toBe(RegistrationPaymentMethodType::BankTransfer);
});

it('hydrates and serializes the native captured required-debit offer and accepted guest payment', function () {
    $offer = RegistrationOffer::fromArray(Data::object(Fixtures::payload('offer-sepa-required'), 'data'));
    $input = Data::object(Fixtures::wire('submitted-sepa-guest'), 'request');
    $payment = Data::object($input, 'payment');
    expect($offer->version->sepaMode)->toBe('required')
        ->and($offer->payment->methods[0]->type)->toBe(RegistrationPaymentMethodType::DirectDebit)
        ->and($offer->payment->methods[0]->id)->toBe($payment['payment_method_id'])
        ->and($offer->payment->mandateText)->toContain('Contract Club', 'Gläubiger ID: DE98ZZZ09999999999')
        ->and($offer->payment->mandateTextVersion)->toBe($payment['shown_mandate_text_version']);
    $mock = new MockClient([function (PendingRequest $pending) use ($input): MockResponse {
        $body = $pending->body();
        assert($body instanceof Stringable);

        expect(json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR))->toBe($input);

        return Fixtures::response('submitted-sepa-guest');
    }]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $application = MembergyClient::fromConnector($connector)->registrationApplications()->submit(
        'membership', Fixtures::submission('submitted-sepa-guest'),
    );
    expect($application->status)->toBe('submitted');
    $mock->assertSentCount(1);
});

it('hydrates the complete native offer schedule and serializes its captured bank name and job fields', function (bool $privacy) {
    $offerFixture = $privacy ? 'offer-privacy-sepa' : 'offer-complete-sepa';
    $submissionFixture = $privacy ? 'submitted-privacy-sepa' : 'submitted-complete-sepa';
    $input = Data::object(Fixtures::wire($submissionFixture), 'request');
    $mock = new MockClient([
        function (PendingRequest $pending) use ($offerFixture): MockResponse {
            expect($pending->headers()->get('Accept'))->toBe('application/vnd.membergy.registration-v1+json');

            return Fixtures::response($offerFixture);
        },
        function (PendingRequest $pending) use ($input, $submissionFixture): MockResponse {
            $body = $pending->body();
            assert($body instanceof Stringable);

            expect(json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR))->toBe($input);

            return Fixtures::response($submissionFixture);
        },
    ]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);
    $offer = $client->registrationOffers()->find('membership', includePaymentSchedule: true);
    expect($offer->payment->methods[0]->collectionIntervalMonths)->toBe(3)
        ->and($offer->payment->mandateTextVersion)->toBe($input['payment']['shown_mandate_text_version'])
        ->and(collect($offer->formSchema)->keyBy('name')['parttime']->options)->toHaveKey('parttime-1');
    $submission = RegistrationSubmission::fromArray($input);
    if ($privacy) {
        $field = collect($offer->formSchema)->keyBy('name')['privacy_enabled'];
        expect($field->type)->toBe('boolean')->and($field->required)->toBeFalse()
            ->and($field->note)->toContain('verwaltenden Personen')
            ->and($submission->toArray()['people'][0]['attributes']['privacy_enabled'])->toBeTrue();
    }
    ob_start();
    var_dump($submission);
    $dump = ob_get_clean();
    expect($dump)->not->toContain($input['payment']['bank_name'], $input['payment']['iban'], $input['payment']['account_holder']);
    expect($client->registrationApplications()->submit('membership', $submission)->status)->toBe('submitted');
    $mock->assertSentCount(2);
})->with(['existing complete offer' => [false], 'native privacy opt-in' => [true]]);

it('maps the native captured debit refusals while preserving their exact request transport', function (string $fixture, string $field) {
    $input = Data::object(Fixtures::wire($fixture), 'request');
    $mock = new MockClient([Fixtures::response($fixture)]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    try {
        MembergyClient::fromConnector($connector)->registrationApplications()->submit('membership', Fixtures::submission($fixture));
        throw new LogicException('Expected the recorded debit refusal.');
    } catch (RegistrationValidationException $exception) {
        expect($exception->fields)->toBe([$field])
            ->and($exception->getPrevious())->toBeNull()
            ->and((string) $exception)->not->toContain('Erwachsener Zahler-Qa', 'DE89370400440532013000', 'COBADEFFXXX');
    }
    $mock->assertSent(fn (SubmitRegistrationApplicationRequest $request): bool => $request->body()->all() === $input);
    $mock->assertSentCount(1);
})->with([
    ['validation-sepa-consent', 'payment.consent'],
    ['validation-sepa-version', 'payment.shown_mandate_text_version'],
]);

/** @return array<string, mixed> Synthetic SEPA consumer input, not a recorded backend submission. */
function syntheticSepaPayment(): array
{
    return [
        'payment_method_id' => 17,
        'account_holder' => 'Synthetic Private Holder',
        'bank_name' => 'Synthetic Private Bank',
        'iban' => 'DE89370400440532013000',
        'bic' => 'COBADEFFXXX',
        'consent' => true,
        'shown_mandate_text_version' => 'synthetic-text-reference',
    ];
}

it('serializes synthetic payment transport through the real request without normalizing optional BIC', function (string $bic) {
    $payment = syntheticSepaPayment();
    if ($bic === 'bank null') {
        $payment['bank_name'] = null;
    } elseif ($bic === 'bank omitted') {
        unset($payment['bank_name']);
    }
    if ($bic === 'omitted') {
        unset($payment['bic']);
    } elseif ($bic === 'null') {
        $payment['bic'] = null;
    }
    $input = Data::object(Fixtures::wire('submitted-adult'), 'request');
    $input['payment'] = $payment;
    $submission = RegistrationSubmission::fromArray($input);
    $mock = new MockClient([function (PendingRequest $pending) use ($input): MockResponse {
        $body = $pending->body();
        assert($body instanceof Stringable);

        expect(json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR))->toBe($input)
            ->and($pending->query()->all())->toBe([]);

        return Fixtures::response('submitted-adult');
    }]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $request = new SubmitRegistrationApplicationRequest('contract-club', 'membership', $submission);
    ob_start();
    var_dump($submission, $request);
    $dump = ob_get_clean();
    expect($dump)->not->toContain('Synthetic Private Holder', 'Synthetic Private Bank', 'DE89370400440532013000', 'COBADEFFXXX', 'synthetic-text-reference');
    MembergyClient::fromConnector($connector)->registrationApplications()->submit('membership', $submission);
    $mock->assertSentCount(1);
})->with(['present', 'null', 'omitted', 'bank null', 'bank omitted']);

it('rejects malformed synthetic payment fields without leaking banking input in exception traces', function (string $case) {
    $input = Data::object(Fixtures::wire('submitted-adult'), 'request');
    $payment = syntheticSepaPayment();
    match ($case) {
        'method string' => $payment['payment_method_id'] = '17',
        'method zero' => $payment['payment_method_id'] = 0,
        'iban type' => $payment['iban'] = ['secret' => 'DE89370400440532013000'],
        'holder length' => $payment['account_holder'] = str_repeat('x', 256),
        'bank length' => $payment['bank_name'] = str_repeat('x', 256),
        'bank type' => $payment['bank_name'] = ['Synthetic Private Bank'],
        'iban length' => $payment['iban'] = str_repeat('x', 51),
        'bic length' => $payment['bic'] = str_repeat('x', 12),
        'version length' => $payment['shown_mandate_text_version'] = str_repeat('x', 191),
        'consent string' => $payment['consent'] = 'true',
        'consent integer' => $payment['consent'] = 1,
        'nested account' => $payment['bank_account'] = ['bank_iban' => 'DE89370400440532013000'],
        'nested confirmation' => $payment['sepa_confirmation'] = ['accepted' => true],
        'derived flag' => $payment['reused_from_sepa_mandate_id'] = 1,
        default => throw new LogicException('Unknown synthetic payment mutation.'),
    };
    $input['payment'] = $payment;
    $old = ini_set('zend.exception_ignore_args', '0');
    try {
        try {
            RegistrationSubmission::fromArray($input);
            throw new LogicException('Expected invalid payment input.');
        } catch (InvalidArgumentException $exception) {
            expect((string) $exception)->not->toContain('Synthetic Private Holder', 'DE89370400440532013000', 'COBADEFFXXX', 'synthetic-text-reference')
                ->and($exception->getPrevious())->toBeNull();
            foreach ($exception->getTrace() as $frame) {
                if (str_starts_with($frame['class'] ?? '', 'Lacodix\\MembergySdk\\')) {
                    foreach ($frame['args'] ?? [] as $argument) {
                        expect($argument)->not->toBeArray();
                    }
                }
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', $old === false ? '1' : $old);
    }
})->with(['method string', 'method zero', 'iban type', 'holder length', 'bank length', 'bank type', 'iban length', 'bic length', 'version length', 'consent string', 'consent integer', 'nested account', 'nested confirmation', 'derived flag']);

it('leaves missing consent to the authoritative resolver and exposes only safe payment error paths', function () {
    $input = Data::object(Fixtures::wire('submitted-adult'), 'request');
    $input['payment'] = syntheticSepaPayment();
    unset($input['payment']['consent']);
    $submission = RegistrationSubmission::fromArray($input);
    // Synthetic failure injection: no debit HTTP recording is claimed by this test.
    $mock = new MockClient([MockResponse::make(['errors' => [
        'payment.consent' => ['DE89370400440532013000'],
        'payment.iban' => ['Synthetic Private Holder'],
        'payment.reusable' => ['private'],
        'payment.iban.DE89370400440532013000' => ['private'],
    ]], 422)]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    try {
        MembergyClient::fromConnector($connector)->registrationApplications()->submit('membership', $submission);
        throw new LogicException('Expected resolver refusal.');
    } catch (RegistrationValidationException $exception) {
        expect($exception->fields)->toBe(['payment.consent', 'payment.iban'])
            ->and((string) $exception)->not->toContain('DE89370400440532013000', 'Synthetic Private Holder');
    }
    $mock->assertSentCount(1);
});

it('preserves free submissions without payment and explicit method-only reuse or transfer', function () {
    $input = Data::object(Fixtures::wire('submitted-adult'), 'request');
    unset($input['payment']);
    expect(RegistrationSubmission::fromArray($input)->toArray())->not->toHaveKey('payment');
    $input['payment'] = ['payment_method_id' => 17];
    expect(RegistrationSubmission::fromArray($input)->toArray()['payment'])->toBe(['payment_method_id' => 17]);
});

it('rejects payment method types outside the backend enum', function () {
    expect(fn () => RegistrationPayment::fromArray([
        'required_if_billable' => true,
        'methods' => [['id' => 11, 'title' => 'Invalid', 'type' => 'debitinternal']],
        'mandate_text' => null, 'mandate_text_version' => null,
    ]))->toThrow(HydrationException::class);
});
