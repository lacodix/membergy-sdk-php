@props(['menu', 'resolve'])
<nav {{ $attributes->class('membergy-menu') }} aria-label="{{ $menu->title }}">
    @include('membergy::components.menu-items', ['items' => $menu->items, 'resolve' => $resolve])
</nav>
