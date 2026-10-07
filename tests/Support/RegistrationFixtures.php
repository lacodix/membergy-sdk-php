<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Support\Data;
use Saloon\Http\Faking\MockResponse;

final class RegistrationFixtures
{
    public static function root(): string
    {
        return dirname(__DIR__).'/Fixtures/Contracts/Registration/v1';
    }

    public static function bytes(string $name): string
    {
        $bytes = file_get_contents(self::root().'/responses/'.$name.($name === 'document' ? '.pdf' : '.json'));
        if ($bytes === false) {
            throw new \RuntimeException('Missing registration fixture.');
        }

        return $bytes;
    }

    /** @return array<string, mixed> */
    public static function wire(string $name): array
    {
        return Data::object(['wire' => json_decode(file_get_contents(self::root().'/wire/'.$name.'.json') ?: '', true, flags: JSON_THROW_ON_ERROR)], 'wire');
    }

    /** @return array<string, mixed> */
    public static function payload(string $name): array
    {
        return Data::object(['payload' => json_decode(self::bytes($name), true, flags: JSON_THROW_ON_ERROR)], 'payload');
    }

    public static function response(string $name): MockResponse
    {
        $wire = self::wire($name);

        return MockResponse::make(self::bytes($name), Data::int($wire, 'status'), Data::object($wire, 'response_headers'));
    }

    public static function submission(string $name = 'submitted-adult'): RegistrationSubmission
    {
        return RegistrationSubmission::fromArray(Data::object(self::wire($name), 'request'));
    }

    public static function resume(): RegistrationResume
    {
        return RegistrationResume::fromArray(Data::object(self::payload('resume-exchange'), 'data'));
    }
}
