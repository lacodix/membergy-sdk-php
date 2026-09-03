<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

final class MeResource extends AbstractResource
{
    public function person(): PersonResource
    {
        return new PersonResource($this->connector);
    }

    public function newsletter(): MemberNewsletterResource
    {
        return new MemberNewsletterResource($this->connector);
    }
}
