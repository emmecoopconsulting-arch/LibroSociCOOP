@php
    $columns = $livewire->availableColumns();
    $fields = $livewire->importFields();
@endphp

@if ($columns === [])
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Nessuna colonna disponibile. Torna allo step File e ricarica il foglio.
    </div>
@else
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($fields as $field => $label)
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950">
                    {{ $label }}
                    @if ($livewire->isRequiredImportField($field))
                        <span class="text-danger-600">*</span>
                    @endif
                </span>

                <select
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    wire:change="refreshPreview"
                    wire:model.live="data.mapping.{{ $field }}"
                >
                    <option value="">Non importare</option>

                    @foreach ($columns as $value => $columnLabel)
                        <option value="{{ $value }}">{{ $columnLabel }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
    </div>
@endif
