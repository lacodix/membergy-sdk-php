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
    ) {
    }

    public function posts(): PostsResource
    {
        return new PostsResource($this->connector);
    }

    // TODO: menus(), events(), boilerplates(), images(), files(),
    //       newsletter() — added as they are implemented.
}
