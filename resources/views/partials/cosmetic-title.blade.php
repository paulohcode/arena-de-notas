@php
    use App\Support\CosmeticCatalog;

    $titleKey = $titleKey ?? null;
    if ($titleKey === null && isset($enrollment) && $enrollment) {
        $titleKey = is_array($enrollment)
            ? ($enrollment['title'] ?? null)
            : ($enrollment->equipped_title ?? null);
    } elseif ($titleKey === null && isset($student) && isset($student->pivot)) {
        $titleKey = $student->pivot->equipped_title ?? null;
    }

    $label = CosmeticCatalog::titleLabel($titleKey);
    $inline = ($inline ?? true);
@endphp
@if($label)
    <span class="cosmetic-title {{ $inline ? 'cosmetic-title--inline' : '' }}">{{ $label }}</span>
@endif
