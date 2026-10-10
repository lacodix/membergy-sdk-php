<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Requests\Auth\CompleteTwoFactorRequest;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Content\ConfirmNewsletterSubscriptionRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Requests\Content\SubscribeNewsletterRequest;
use Lacodix\MembergySdk\Requests\Content\UnsubscribeAllNewsletterRequest;
use Lacodix\MembergySdk\Requests\Me\ConfirmPasswordRequest;
use Lacodix\MembergySdk\Requests\Me\IssueManagementLoginRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePasswordRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePersonRequest;
use Lacodix\MembergySdk\Requests\Registrations\AbstractRegistrationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ResumeRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationResumeRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationVerificationRequest;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\VerifyRegistrationApplicationRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Helpers\Debugger;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Symfony\Component\VarDumper\VarDumper;

/** @return array<string, mixed> */
function debugResumePayload(): array
{
    $data = Data::object(Fixtures::payload('resume-exchange'), 'data');
    $data['resume_context'] = str_repeat('c', 64);

    return $data;
}

function sensitiveDebugRequest(string $kind): Request
{
    $submission = Fixtures::submission()->toArray();
    $submission['submission_ticket'] = 'synthetic-submission-ticket';

    return match ($kind) {
        'resume landing' => new ShowRegistrationResumeRequest(str_repeat('b', 64)),
        'verification landing' => new ShowRegistrationVerificationRequest(str_repeat('b', 64)),
        'resume exchange' => new ResumeRegistrationApplicationRequest('synthetic-exchange-token'),
        'verification exchange' => new VerifyRegistrationApplicationRequest('synthetic-exchange-token'),
        'resumed offer' => new ShowRegistrationOfferRequest('contract-club', 'membership', RegistrationResume::fromArray(debugResumePayload())),
        'submission' => new SubmitRegistrationApplicationRequest('contract-club', 'membership', RegistrationSubmission::fromArray($submission)),
        'own application' => new ShowRegistrationApplicationRequest('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
        'newsletter confirmation' => new ConfirmNewsletterSubscriptionRequest('contract-club', 'synthetic-newsletter-token'),
        'newsletter unsubscribe' => new UnsubscribeAllNewsletterRequest('contract-club', 'synthetic-newsletter-token'),
        'auth credentials' => new CreateTokenRequest('synthetic@example.test', 'synthetic-password-marker', 'Website'),
        'auth two-factor' => new CompleteTwoFactorRequest('synthetic-challenge-marker', recoveryCode: 'synthetic-recovery-marker'),
        'password confirmation' => new ConfirmPasswordRequest('contract-club', 'synthetic-password-marker'),
        'password update' => new UpdatePasswordRequest('contract-club', 'synthetic-current-password', 'synthetic-new-password', 'synthetic-new-password'),
        'management login' => new IssueManagementLoginRequest('contract-club'),
        'person update' => new UpdatePersonRequest('contract-club', ['firstname' => 'SyntheticPrivatePerson']),
        'ordinary page' => new ShowPageRequest('contract-club', 'ordinary-page'),
        'newsletter subscription' => new SubscribeNewsletterRequest('contract-club', [], 'synthetic@example.test'),
        default => throw new InvalidArgumentException('Unknown debug scenario.'),
    };
}

dataset('sensitive debug requests', [
    'resume landing URL' => ['resume landing', str_repeat('b', 64)],
    'verification landing URL' => ['verification landing', str_repeat('b', 64)],
    'POST resume exchange' => ['resume exchange', 'synthetic-exchange-token'],
    'POST verification exchange' => ['verification exchange', 'synthetic-exchange-token'],
    'resume header' => ['resumed offer', str_repeat('c', 64)],
    'submission body' => ['submission', 'synthetic-submission-ticket'],
    'own application URL' => ['own application', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],
    'newsletter confirmation body' => ['newsletter confirmation', 'synthetic-newsletter-token'],
    'newsletter unsubscribe body' => ['newsletter unsubscribe', 'synthetic-newsletter-token'],
]);

it('suppresses sensitive request and response hooks and object dumps without changing the wire', function (string $kind, string $marker, string $level, string $mode) {
    $request = sensitiveDebugRequest($kind);
    $mock = new MockClient([MockResponse::make(['secret' => 'synthetic-response-secret'], headers: ['X-Debug-Secret' => 'synthetic-response-header'])]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient($mock);
    $owner = $level === 'connector' ? $connector : $request;
    $calls = 0;
    $oldHandler = VarDumper::setHandler(static function (mixed $value) use (&$calls): void {
        $calls++;
        var_dump($value);
    });
    ob_start();
    try {
        if ($mode === 'callback') {
            $owner->debugRequest(static function (PendingRequest $pending, RequestInterface $message) use (&$calls): void {
                $calls++;
                echo $message->getUri(), json_encode($message->getHeaders()), $message->getBody();
            })->debugResponse(static function (Response $response, ResponseInterface $message) use (&$calls): void {
                $calls++;
                echo json_encode($message->getHeaders()), $response->body();
            });
        } else {
            $owner->debug();
        }
        $response = $connector->send($request);
        var_dump($request);
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        VarDumper::setHandler($oldHandler);
    }

    $wire = $response->getPsrRequest();
    expect((string) $wire->getUri().json_encode($wire->getHeaders()).(string) $wire->getBody())->toContain($marker)
        ->and($response->body())->toContain('synthetic-response-secret')
        ->and($calls)->toBe(0)
        ->and($output)->not->toContain($marker, 'synthetic-response-secret', 'synthetic-response-header');
    $mock->assertSentCount(1);
})->with('sensitive debug requests')->with(['connector', 'request'])->with(['callback', 'stdout']);

it('preserves ordinary request and response debugging', function (string $level, string $mode) {
    $request = new ShowPageRequest('contract-club', 'ordinary-page');
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient(new MockClient([MockResponse::make(['title' => 'ordinary-response'])]));
    $owner = $level === 'connector' ? $connector : $request;
    $oldHandler = VarDumper::setHandler(static function (mixed $value): void {
        var_dump($value);
    });
    ob_start();
    try {
        if ($mode === 'callback') {
            $owner->debugRequest(static function (PendingRequest $pending, RequestInterface $message): void {
                echo $message->getUri();
            })->debugResponse(static function (Response $response, ResponseInterface $message): void {
                echo $response->body();
            });
        } else {
            $owner->debug();
        }
        $connector->send($request);
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        VarDumper::setHandler($oldHandler);
    }

    expect($output)->toContain('ordinary-page', 'ordinary-response');
})->with(['connector', 'request'])->with(['callback', 'stdout']);

it('keeps the resume exchange and header out of debugging on a subsequent ordinary request', function (string $mode) {
    $mock = new MockClient([
        MockResponse::make(['data' => debugResumePayload()]),
        MockResponse::make(['secret' => 'synthetic-registration-response']),
        MockResponse::make(['title' => 'ordinary-response']),
    ]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient($mock);
    $oldHandler = VarDumper::setHandler(static function (mixed $value): void {
        var_dump($value);
    });
    ob_start();
    try {
        if ($mode === 'callback') {
            $connector->debugRequest(static function (PendingRequest $pending, RequestInterface $message): void {
                echo $message->getUri(), json_encode($message->getHeaders()), $message->getBody();
            })->debugResponse(static function (Response $response, ResponseInterface $message): void {
                echo $response->body();
            });
        } else {
            $connector->debug();
        }
        $exchange = $connector->send(new ResumeRegistrationApplicationRequest('synthetic-exchange-token'));
        $resume = RegistrationResume::fromArray(Data::object($exchange->json(), 'data'));
        $offer = $connector->send(new ShowRegistrationOfferRequest('contract-club', 'membership', $resume));
        $ordinary = $connector->send(new ShowPageRequest('contract-club', 'ordinary-page'));
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        VarDumper::setHandler($oldHandler);
    }

    expect($offer->getPendingRequest()->headers()->get(AbstractRegistrationRequest::RESUME_HEADER))->toBe(str_repeat('c', 64))
        ->and($ordinary->getPendingRequest()->headers()->get(AbstractRegistrationRequest::RESUME_HEADER))->toBeNull()
        ->and($output)->toContain('ordinary-page', 'ordinary-response')
        ->and($output)->not->toContain('synthetic-exchange-token', str_repeat('c', 64), 'synthetic-registration-response');
    $mock->assertSentCount(3);
})->with(['callback', 'stdout']);

it('stops a sensitive POST before transmission when request debugging uses die', function (string $mode) {
    $submission = Fixtures::submission()->toArray();
    $submission['submission_ticket'] = 'synthetic-submission-ticket';
    $submission['email'] = 'synthetic-die@example.test';
    $request = new SubmitRegistrationApplicationRequest('contract-club', 'membership', RegistrationSubmission::fromArray($submission));
    $mock = new MockClient([MockResponse::make(['secret' => 'synthetic-response-secret'])]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient($mock);
    $sentinel = new LogicException('Debugger stopped before transmission.');
    $oldDieHandler = Debugger::$dieHandler;
    $calls = 0;
    $oldDumpHandler = VarDumper::setHandler(static function (mixed $value) use (&$calls): void {
        $calls++;
        var_dump($value);
    });
    $stopped = false;
    Debugger::$dieHandler = static function () use ($sentinel, $mock): never {
        $mock->assertSentCount(0);
        throw $sentinel;
    };
    ob_start();
    try {
        if ($mode === 'callback') {
            $connector->debugRequest(static function (PendingRequest $pending, RequestInterface $message) use (&$calls): void {
                $calls++;
                echo $message->getBody();
            }, die: true);
        } else {
            $connector->debugRequest(die: true);
        }
        try {
            $connector->send($request);
        } catch (LogicException $error) {
            expect($error)->toBe($sentinel);
            $stopped = true;
        }
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        Debugger::$dieHandler = $oldDieHandler;
        VarDumper::setHandler($oldDumpHandler);
    }

    expect($stopped)->toBeTrue()
        ->and($calls)->toBe(0)
        ->and($output)->toBe('')
        ->and($output)->not->toContain('synthetic-submission-ticket', 'synthetic-die@example.test');
    $mock->assertSentCount(0);
})->with(['callback', 'stdout']);

it('stops after a sensitive POST response when response debugging uses die without dumping secrets', function (string $mode) {
    $request = sensitiveDebugRequest('submission');
    $mock = new MockClient([MockResponse::make(['secret' => 'synthetic-response-secret', 'email' => 'synthetic-die@example.test'])]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient($mock);
    $sentinel = new LogicException('Debugger stopped after receipt.');
    $oldDieHandler = Debugger::$dieHandler;
    $calls = 0;
    $oldDumpHandler = VarDumper::setHandler(static function (mixed $value) use (&$calls): void {
        $calls++;
        var_dump($value);
    });
    $stopped = false;
    Debugger::$dieHandler = static function () use ($sentinel, $mock): never {
        $mock->assertSentCount(1);
        throw $sentinel;
    };
    ob_start();
    try {
        if ($mode === 'callback') {
            $connector->debugResponse(static function (Response $response, ResponseInterface $message) use (&$calls): void {
                $calls++;
                echo $response->body();
            }, die: true);
        } else {
            $connector->debug(die: true);
        }
        try {
            $connector->send($request);
        } catch (LogicException $error) {
            expect($error)->toBe($sentinel);
            $stopped = true;
        }
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        Debugger::$dieHandler = $oldDieHandler;
        VarDumper::setHandler($oldDumpHandler);
    }

    expect($stopped)->toBeTrue()
        ->and($calls)->toBe(0)
        ->and($output)->toBe('')
        ->and($output)->not->toContain('synthetic-submission-ticket', 'synthetic-response-secret', 'synthetic-die@example.test');
    $mock->assertSentCount(1);
})->with(['callback', 'debug stdout']);

it('preserves die for ordinary request and response debugging', function () {
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', tries: 1);
    $connector->withMockClient(new MockClient([MockResponse::make(['title' => 'ordinary-response'])]));
    $dies = 0;
    $oldHandler = Debugger::$dieHandler;
    Debugger::$dieHandler = static function () use (&$dies): void {
        $dies++;
    };
    try {
        $connector->debugRequest(static function (): void {}, die: true)->debugResponse(static function (): void {}, die: true);
        $connector->send(new ShowPageRequest('contract-club', 'ordinary-page'));
        expect($dies)->toBe(2);
    } finally {
        Debugger::$dieHandler = $oldHandler;
    }
});

function assertSafeDebugStop(Request $request, string $owner, string $phase, string $mode, bool $authenticated = false): void
{
    $expectedSends = $phase === 'request' ? 0 : 1;
    $authorizationPreserved = false;
    $mock = new MockClient([static function (PendingRequest $pending) use ($authenticated, &$authorizationPreserved): MockResponse {
        $authorizationPreserved = $pending->headers()->get('Authorization') === ($authenticated ? 'Bearer synthetic-bearer-marker' : null);

        return MockResponse::make(['secret' => 'synthetic-response-secret', 'email' => 'synthetic@example.test']);
    }]);
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', $authenticated ? 'synthetic-bearer-marker' : null, tries: 1);
    $connector->withMockClient($mock);
    $sentinel = new LogicException('Safe debugger stop.');
    $oldDieHandler = Debugger::$dieHandler;
    $calls = 0;
    $dies = 0;
    $stopped = false;
    $oldDumpHandler = VarDumper::setHandler(static function (mixed $value) use (&$calls): void {
        $calls++;
        var_dump($value);
    });
    Debugger::$dieHandler = static function () use ($sentinel, $mock, $expectedSends, &$dies): never {
        $dies++;
        $mock->assertSentCount($expectedSends);
        throw $sentinel;
    };
    $requestDebugger = static function (PendingRequest $pending, RequestInterface $message) use (&$calls): void {
        $calls++;
        echo $message->getUri(), json_encode($message->getHeaders()), $message->getBody();
    };
    $responseDebugger = static function (Response $response, ResponseInterface $message) use (&$calls): void {
        $calls++;
        echo json_encode($response->getPendingRequest()->headers()->all()), $response->body();
    };
    ob_start();
    try {
        $instances = match ($owner) {
            'request' => [$request], 'connector' => [$connector], default => [$connector, $request],
        };
        foreach ($instances as $instance) {
            if ($phase === 'combined') {
                $instance->debug(die: true);
            } else {
                $requestDie = $phase === 'request' && ($owner !== 'mixed' || $instance === $request);
                $instance->debugRequest($mode === 'callback' ? $requestDebugger : null, die: $requestDie)
                    ->debugResponse($mode === 'callback' ? $responseDebugger : null, die: $phase === 'response');
            }
        }
        if ($owner === 'mixed') {
            // A request stop must win even when the connector also stops after receipt.
            $connector->debugResponse($mode === 'callback' ? $responseDebugger : null, die: true);
        }
        try {
            $connector->send($request);
        } catch (LogicException $error) {
            expect($error)->toBe($sentinel);
            $stopped = true;
        }
        $output = (string) ob_get_contents();
    } finally {
        ob_end_clean();
        Debugger::$dieHandler = $oldDieHandler;
        VarDumper::setHandler($oldDumpHandler);
    }

    expect($stopped)->toBe($phase !== 'none')
        ->and($dies)->toBe($phase === 'none' ? 0 : 1)
        ->and($calls)->toBe(0)
        ->and($output)->toBe('')
        ->and($output)->not->toContain('synthetic-bearer-marker', 'synthetic-password-marker', 'synthetic-submission-ticket', 'synthetic-response-secret', 'synthetic@example.test', 'SyntheticPrivatePerson')
        ->and($authorizationPreserved)->toBeTrue();
    $mock->assertSentCount($expectedSends);
}

it('preserves direct sensitive request and response stop timing without invoking debug callbacks or dumps', function (string $kind, string $phase, string $mode) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'request', $phase, $mode);
})->with([
    'submission', 'resume exchange', 'newsletter confirmation', 'newsletter unsubscribe',
    'auth credentials', 'auth two-factor', 'password confirmation', 'password update', 'management login',
])->with(['request', 'response'])->with(['callback', 'stdout']);

it('keeps authenticated connector hooks silent while preserving stop timing and bearer authentication', function (string $kind, string $phase, string $mode) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'connector', $phase, $mode, authenticated: true);
})->with(['password confirmation', 'person update', 'newsletter subscription'])->with(['request', 'response', 'none'])->with(['callback', 'stdout']);

it('keeps ordinary request-level hooks silent on authenticated sends and preserves stop timing', function (string $kind, string $phase, string $mode) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'request', $phase, $mode, authenticated: true);
})->with(['ordinary page', 'person update'])->with(['request', 'response', 'none'])->with(['callback', 'stdout']);

it('keeps ordinary authenticated request combined debugging silent and stops after receipt', function (string $kind) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'request', 'combined', 'stdout', authenticated: true);
})->with(['ordinary page', 'person update']);

it('checks authentication per send when the same request debug hooks are reused', function () {
    $request = new ShowPageRequest('contract-club', 'ordinary-page');
    $request->debugRequest(static function (PendingRequest $pending, RequestInterface $message): void {
        echo $message->getUri(), json_encode($message->getHeaders());
    })->debugResponse(static function (Response $response, ResponseInterface $message): void {
        echo $response->body();
    });
    foreach ([false, true, false] as $authenticated) {
        $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', $authenticated ? 'synthetic-bearer-marker' : null, tries: 1);
        $mock = new MockClient([MockResponse::make(['title' => 'ordinary-response'])]);
        $connector->withMockClient($mock);
        ob_start();
        try {
            $response = $connector->send($request);
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        expect($response->getPsrRequest()->getHeaderLine('Authorization') === ($authenticated ? 'Bearer synthetic-bearer-marker' : ''))->toBeTrue();
        if ($authenticated) {
            expect($output)->toBe('');
        } else {
            expect($output)->toContain('ordinary-page', 'ordinary-response');
        }
        $mock->assertSentCount(1);
    }
});

it('preserves one safe stop with both connector and direct sensitive hooks registered', function (string $phase, string $mode) {
    assertSafeDebugStop(sensitiveDebugRequest('password confirmation'), 'both', $phase, $mode, authenticated: true);
})->with(['request', 'response'])->with(['callback', 'stdout']);

it('stops before transmission even when the authenticated connector also registers a response stop', function (string $mode) {
    assertSafeDebugStop(sensitiveDebugRequest('password confirmation'), 'mixed', 'request', $mode, authenticated: true);
})->with(['callback', 'stdout']);

it('preserves Saloon combined debug stop timing after receipt for sensitive requests', function (string $kind) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'request', 'combined', 'stdout');
})->with(['submission', 'newsletter confirmation', 'newsletter unsubscribe']);

it('keeps authenticated combined debugging silent and stops after receipt', function (string $kind) {
    assertSafeDebugStop(sensitiveDebugRequest($kind), 'connector', 'combined', 'stdout', authenticated: true);
})->with(['password confirmation', 'person update', 'newsletter subscription']);

it('stops once after receipt with both safe combined debuggers registered', function () {
    assertSafeDebugStop(sensitiveDebugRequest('password confirmation'), 'both', 'combined', 'stdout', authenticated: true);
});
