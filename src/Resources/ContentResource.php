<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\Connectors\MembergyConnector;

/**
 * Entry point for all /content/* endpoints of the Membergy API.
 *
 * Grouped here so the SDK surface reads naturally:
 *   $client->content()->posts()->find('slug')
 *   $client->content()->menus()->find($uuid)
 *   ...
 *
 * Additional resource groups (Resources/, Forms/, Auth/, ...) get
 * their own classes exposed on MembergyClient at the same level.
 */
class ContentResource
{
    public function __construct(
        private readonly MembergyConnector $connector,
    ) {}

    public function posts(): PostsResource
    {
        return new PostsResource($this->connector);
    }

    public function pages(): PagesResource
    {
        return new PagesResource($this->connector);
    }

    public function postCategories(): PostCategoriesResource
    {
        return new PostCategoriesResource($this->connector);
    }

    public function menus(): MenusResource
    {
        return new MenusResource($this->connector);
    }

    public function images(): ImagesResource
    {
        return new ImagesResource($this->connector);
    }

    public function files(): FilesResource
    {
        return new FilesResource($this->connector);
    }

    public function events(): EventsResource
    {
        return new EventsResource($this->connector);
    }

    public function boilerplates(): BoilerplatesResource
    {
        return new BoilerplatesResource($this->connector);
    }
}
