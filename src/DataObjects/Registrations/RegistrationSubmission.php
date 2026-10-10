<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use InvalidArgumentException;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Support\RegistrationInput;
use SensitiveParameter;

/** Validates the frozen SubmissionRequest transport shape; published offer rules stay server-side. */
final readonly class RegistrationSubmission
{
    private const FIELDS = [
        'submission_key', 'offer_version_id', 'submission_ticket', 'website', 'email',
        'remark', 'desired_joining_date', 'people', 'participants', 'proofs',
    ];

    private const STRING_ATTRIBUTES = [
        'firstname', 'lastname', 'date_of_birth', 'name_at_birth', 'date_of_wedding',
        'salutation', 'title', 'gender', 'address_addition', 'address_street', 'address_zip',
        'address_city', 'country', 'department', 'parttime',
    ];

    /** @param array<string, mixed> $values */
    private function __construct(
        public string $submissionKey,
        public int $offerVersionId,
        private array $values,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(#[SensitiveParameter] array $values): self
    {
        try {
            return self::validated($values);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid registration submission input.');
        }
    }

    /** @param array<string, mixed> $values */
    private static function validated(#[SensitiveParameter] array $values): self
    {
        RegistrationInput::keys($values, self::FIELDS);
        $key = Data::string($values, 'submission_key');
        RegistrationInput::uuid($key);
        $version = Data::int($values, 'offer_version_id');
        if ($version < 1 || Data::string($values, 'submission_ticket') === '') {
            throw new InvalidArgumentException('Registration submission requires a version and opaque ticket.');
        }
        foreach (['website', 'email', 'remark'] as $field) {
            $value = Data::nullableString($values, $field);
            if ($value !== null && (($field === 'email' && RegistrationInput::length($value) > 255) || ($field === 'remark' && RegistrationInput::length($value) > 1000))) {
                throw new InvalidArgumentException('Invalid optional registration input.');
            }
        }
        if (($values['email'] ?? null) !== null) {
            RegistrationInput::email(Data::string($values, 'email'));
        }

        RegistrationInput::joiningDate(Data::nullableString($values, 'desired_joining_date'));

        $people = Data::objectList($values, 'people');
        $participants = Data::objectList($values, 'participants');
        if (count($people) < 1 || count($people) > 2 || count($participants) < 1 || count($participants) > 2) {
            throw new InvalidArgumentException('Registration needs one or two people and participants.');
        }
        foreach ($people as $person) {
            RegistrationInput::keys($person, ['attributes', 'contacts']);
            $attributes = Data::object($person, 'attributes');
            RegistrationInput::keys($attributes, [...self::STRING_ATTRIBUTES, 'service_class_id', 'salary_bracket_id', 'trainee', 'retired']);
            foreach ($attributes as $field => $value) {
                if ($value === null) {
                    continue;
                }
                $valid = match ($field) {
                    'service_class_id', 'salary_bracket_id' => is_int($value) && $value > 0,
                    'trainee', 'retired' => in_array($value, [true, false, 0, 1, '0', '1'], true),
                    'salutation' => in_array($value, ['mr', 'mrs', 'company', 'family'], true),
                    'gender' => in_array($value, ['male', 'female', 'divers'], true),
                    'parttime' => in_array($value, ['fulltime', 'parttime-1', 'parttime-2', 'parttime-3'], true),
                    default => is_string($value) && (in_array($field, ['date_of_birth', 'date_of_wedding'], true) || RegistrationInput::length($value) <= 255),
                };
                if (! $valid) {
                    throw new InvalidArgumentException('Invalid registration person attributes.');
                }
            }
            $contacts = array_key_exists('contacts', $person) ? Data::objectList($person, 'contacts') : [];
            if (count($contacts) > 10) {
                throw new InvalidArgumentException('Too many registration contacts.');
            }
            foreach ($contacts as $contact) {
                RegistrationInput::keys($contact, ['type', 'value']);
                if (! in_array(Data::string($contact, 'type'), ['private_phone', 'private_mobilephone', 'private_fax', 'private_email', 'job_phone', 'job_mobilephone', 'job_fax', 'job_email', 'club_email'], true)
                    || Data::string($contact, 'value') === '' || RegistrationInput::length(Data::string($contact, 'value')) > 190) {
                    throw new InvalidArgumentException('Invalid registration contact.');
                }
            }
        }
        foreach ($participants as $participant) {
            RegistrationInput::keys($participant, ['person_index', 'option_ids']);
            $index = Data::int($participant, 'person_index');
            $ids = array_key_exists('option_ids', $participant) ? Data::intList($participant, 'option_ids') : [];
            if ($index < 0 || $index > 1 || count($ids) > 50 || array_filter($ids, static fn (int $id): bool => $id < 1) !== []) {
                throw new InvalidArgumentException('Invalid registration participant.');
            }
        }
        $proofs = array_key_exists('proofs', $values) ? Data::objectList($values, 'proofs') : [];
        if (count($proofs) > 100) {
            throw new InvalidArgumentException('Too many registration proofs.');
        }
        foreach ($proofs as $proof) {
            RegistrationInput::keys($proof, ['participant_index', 'requirement_id', 'shown_content_hash']);
            $index = Data::int($proof, 'participant_index');
            $hash = Data::string($proof, 'shown_content_hash');
            if ($index < 0 || $index > 1 || Data::int($proof, 'requirement_id') < 1 || $hash === '' || RegistrationInput::length($hash) > 64) {
                throw new InvalidArgumentException('Invalid registration proof.');
            }
        }

        return new self($key, $version, $values);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $values = $this->values;
        // Attributes are a JSON object even when no optional person values were entered.
        $people = Data::objectList($values, 'people');
        foreach ($people as &$person) {
            if ($person['attributes'] === []) {
                $person['attributes'] = (object) [];
            }
        }
        unset($person);
        $values['people'] = $people;

        return $values;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
