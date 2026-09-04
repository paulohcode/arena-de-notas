@if($student->hasCharacterClass())
    <span class="class-badge class-badge--{{ $student->character_class }}">
        {{ $student->characterClassIcon() }} {{ $student->characterClassLabel() }}
    </span>
@else
    <span class="text-amber-100/55">Sem classe</span>
@endif
