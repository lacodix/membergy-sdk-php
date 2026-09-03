@if ($dynamic instanceof \Lacodix\MembergySdk\DataObjects\DynamicIncludes\PostCategoryInclude)
    <x-membergy::post-list :posts="$dynamic->posts" />
@endif
