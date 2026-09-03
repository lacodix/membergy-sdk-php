<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\FormDefinition;
use Lacodix\MembergySdk\DataObjects\FormSubmissionResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Forms\ShowRegisterFormRequest;
use Lacodix\MembergySdk\Requests\Forms\SubmitRegisterFormRequest;
use Lacodix\MembergySdk\Support\Payload;

final class RegistrationResource extends AbstractResource
{
    public function form(): FormDefinition
    {
        try {
            $response = $this->send(new ShowRegisterFormRequest($this->connector->tenant()));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException('Registration form not found.', previous: $exception);
        }

        return FormDefinition::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    /** @param array<string, mixed> $values */
    public function submit(array $values): FormSubmissionResult
    {
        try {
            $response = $this->send(new SubmitRegisterFormRequest(
                $this->connector->tenant(),
                $values,
            ));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException('Registration form not found.', previous: $exception);
        }

        return FormSubmissionResult::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
