@props(['tone' => 'blue'])
<span {{ $attributes->merge(['class' => 'pill '.$tone]) }}>{{ $slot }}</span>
