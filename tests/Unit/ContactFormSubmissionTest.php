<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;

it('omits null optional values and preserves intentionally empty input', function () {
    expect((new ContactFormSubmission('ticket'))->toArray())->toBe(['submission_ticket' => 'ticket'])
        ->and((new ContactFormSubmission('ticket', email: '', subject: '', message: '', website: ''))->toArray())
        ->toBe(['submission_ticket' => 'ticket', 'email' => '', 'subject' => '', 'message' => '', 'website' => '']);
});

it('rejects unsafe contact payload values with a safe local exception', function (string $field, string $value) {
    try {
        new ContactFormSubmission(...array_replace(['submissionTicket' => 'ticket'], [$field => $value]));
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('Invalid contact form submission.')
            ->and($exception->getPrevious())->toBeNull();

        return;
    }
    throw new RuntimeException('Expected contact payload validation.');
})->with([
    'empty ticket' => ['submissionTicket', ''],
    'invalid UTF-8' => ['message', "\xC3\x28"],
    'two mailboxes' => ['email', 'a@example.test,b@example.test'],
    'display name' => ['email', 'Person <a@example.test>'],
    'email newline' => ['email', "a@example.test\r\nBcc: bad@example.test"],
    'email too long' => ['email', str_repeat('a', 243).'@example.test'],
    'subject control' => ['subject', "Hello\nBcc"],
    'subject too long' => ['subject', str_repeat('a', 201)],
    'message control' => ['message', "a\0b"],
    'lone carriage return' => ['message', "a\rb"],
    'message too long' => ['message', str_repeat('a', 10001)],
    'filled honeypot' => ['website', 'https://bot.test'],
    'oversize JSON escapes' => ['message', str_repeat("\t", 10000)],
    'oversize JSON unicode' => ['message', str_repeat('€', 7000)],
]);

it('accepts transport boundaries while leaving configured required fields to the server', function () {
    $submission = new ContactFormSubmission(
        'ticket', email: 'visitor@example.test', subject: str_repeat('ä', 200), message: str_repeat('a', 10000),
    );
    expect($submission->toArray()['subject'])->toBe(str_repeat('ä', 200))
        ->and($submission->toArray()['message'])->toHaveLength(10000)
        ->and((new ContactFormSubmission('ticket'))->toArray())->toBe(['submission_ticket' => 'ticket']);
});
