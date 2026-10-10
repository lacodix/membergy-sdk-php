<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

use Saloon\Http\Response;
use SensitiveParameter;

class RegistrationException extends MembergyException
{
    private const INPUT_FIELDS = [
        'submission_key', 'offer_version_id', 'submission_ticket', 'website', 'email',
        'remark', 'desired_joining_date', 'people', 'participants', 'proofs', 'token', 'public_id',
    ];

    private const PERSON_ATTRIBUTES = [
        'firstname', 'lastname', 'date_of_birth', 'name_at_birth', 'date_of_wedding',
        'salutation', 'title', 'gender', 'address_addition', 'address_street', 'address_zip',
        'address_city', 'country', 'department', 'service_class_id', 'salary_bracket_id',
        'trainee', 'retired', 'parttime',
    ];

    public function __construct(string $message, public readonly ?int $status = null)
    {
        // Never chain Saloon/Guzzle exceptions: they retain credentials, URLs and response bodies.
        parent::__construct($message);
    }

    public static function fromResponse(#[SensitiveParameter] Response $response): self
    {
        return match ($response->status()) {
            401 => new RegistrationAuthenticationException('Registration requires an authenticated account.', 401),
            404 => new RegistrationNotFoundException('The registration resource or credential is unavailable.', 404),
            409 => new RegistrationConflictException('Registration changed. Reload the offer and confirm its current requirements.', 409),
            410 => new RegistrationExpiredException('The registration credential is expired or no longer usable.', 410),
            422 => new RegistrationValidationException(self::fields($response)),
            429 => new RegistrationRateLimitException(self::retryAfter($response)),
            default => new self('The registration request failed.', $response->status()),
        };
    }

    /** @return list<string> Allowlisted request paths with schema-bounded indexes; no server messages. */
    private static function fields(#[SensitiveParameter] Response $response): array
    {
        try {
            $data = $response->json();
        } catch (\Throwable) {
            return [];
        }
        if (! is_array($data) || ! is_array($data['errors'] ?? null)) {
            return [];
        }
        $fields = [];
        foreach (array_keys($data['errors']) as $field) {
            if (is_string($field) && self::isInputField($field)) {
                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    private static function isInputField(string $field): bool
    {
        // Every documented path fits this bound. Reject oversized/hostile keys before splitting.
        if (strlen($field) > 64) {
            return false;
        }
        $parts = explode('.', $field);
        if (count($parts) === 1) {
            return in_array($field, self::INPUT_FIELDS, true);
        }
        $index = $parts[1];

        return match ($parts[0]) {
            'people' => self::isIndex($index, 1) && (
                (count($parts) === 3 && in_array($parts[2], ['attributes', 'contacts'], true))
                || (count($parts) === 4 && $parts[2] === 'attributes' && in_array($parts[3], self::PERSON_ATTRIBUTES, true))
                || (count($parts) === 5 && $parts[2] === 'contacts' && self::isIndex($parts[3], 9) && in_array($parts[4], ['type', 'value'], true))
            ),
            'participants' => self::isIndex($index, 1) && (
                (count($parts) === 3 && in_array($parts[2], ['person_index', 'option_ids'], true))
                || (count($parts) === 4 && $parts[2] === 'option_ids' && self::isIndex($parts[3], 49))
            ),
            'proofs' => self::isIndex($index, 99) && count($parts) === 3 && in_array($parts[2], ['participant_index', 'requirement_id', 'shown_content_hash'], true),
            default => false,
        };
    }

    private static function isIndex(string $index, int $maximum): bool
    {
        return preg_match('/\A(?:0|[1-9][0-9]?)\z/', $index) === 1 && (int) $index <= $maximum;
    }

    private static function retryAfter(#[SensitiveParameter] Response $response): ?int
    {
        $value = $response->getPsrResponse()->getHeaderLine('Retry-After');

        return ctype_digit($value) && strlen($value) <= 9 ? (int) $value : null;
    }
}
