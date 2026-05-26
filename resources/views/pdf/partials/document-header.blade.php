@php
    $headerText = trim((string) ($documentHeader?->text ?? ''));
    $headerLogoPath = null;

    if (filled($documentHeader?->logo_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($documentHeader->logo_path)) {
        $headerLogoPath = \Illuminate\Support\Facades\Storage::disk('public')->path($documentHeader->logo_path);
    }
@endphp

@if ($headerLogoPath || filled($headerText))
    <div class="document-header">
        @if ($headerLogoPath)
            <img class="document-header-logo" src="{{ $headerLogoPath }}" alt="Logo">
        @endif

        @if (filled($headerText))
            <div class="document-header-text">{!! nl2br(e($headerText)) !!}</div>
        @endif
    </div>
@endif
