<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\FormDefinition;
use Lacodix\MembergySdk\DataObjects\PersonProfile;
use Lacodix\MembergySdk\Requests\Me\ShowPersonFormRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePersonRequest;
use Lacodix\MembergySdk\Support\Payload;

final class PersonResource extends AbstractResource
{
    public function get(): PersonProfile
    {
        $payload = Payload::fromResponse($this->send(
            new ShowPersonRequest($this->connector->tenant()),
        ));

        return PersonProfile::fromArray(Payload::data($payload));
    }

    public function form(): FormDefinition
    {
        $payload = Payload::fromResponse($this->send(
            new ShowPersonFormRequest($this->connector->tenant()),
        ));

        return FormDefinition::fromArray(Payload::data($payload));
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): PersonProfile
    {
        $payload = Payload::fromResponse($this->send(
            new UpdatePersonRequest($this->connector->tenant(), $values),
        ));

        return PersonProfile::fromArray(Payload::data($payload));
    }
}
