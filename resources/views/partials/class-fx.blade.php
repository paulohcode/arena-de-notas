@php
    $fxClass = $characterClass ?? ($student->character_class ?? null);
@endphp
@if(filled($fxClass) && isset(\App\Models\User::CHARACTER_CLASSES[$fxClass]))
    <span class="class-fx class-fx--{{ $fxClass }}" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </span>
@endif
