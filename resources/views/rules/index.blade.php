@php
    use App\Services\Rules\RuleEngine;
    use App\Support\Format;
@endphp

<x-app-layout title="قواعد طراحی">
    <x-page-header title="قواعد طراحی"
        subtitle="دانش خیاطی سامانه به‌صورت قاعده ذخیره شده است؛ می‌توانید قاعده‌های خودتان را هم اضافه کنید.">
        <x-slot:actions>
            <x-button href="{{ route('rules.create') }}" icon="plus">قاعده جدید</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-stat label="قواعد سراسری سامانه" :value="Format::number($counts['global'])" icon="star" color="clay"
            hint="فقط خواندنی" />
        <x-stat label="قواعد این کارگاه" :value="Format::number($counts['mine'])" icon="check-circle" color="emerald" />
    </div>

    {{-- زبانه دامنه‌ها --}}
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('rules.index') }}"
            class="rounded-xl border px-3 py-1.5 text-sm font-semibold transition {{ $scope ? 'border-stone-300 bg-white text-stone-600 hover:bg-stone-50' : 'border-transparent bg-brand-600 text-white' }}">
            همه
        </a>
        @foreach ($scopes as $key => $label)
            <a href="{{ route('rules.index', ['scope' => $key]) }}"
                class="rounded-xl border px-3 py-1.5 text-sm font-semibold transition {{ $scope === $key ? 'border-transparent bg-brand-600 text-white' : 'border-stone-300 bg-white text-stone-600 hover:bg-stone-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($rules->isEmpty())
        <x-empty-state icon="check-circle" title="قاعده‌ای در این دامنه نیست"
            description="قواعد به سامانه می‌گویند برای هر پارچه و هر مدل چه پیشنهادی به شما بدهد.">
            <x-slot:action>
                <x-button href="{{ route('rules.create') }}" icon="plus">ساخت قاعده</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-6">
            @foreach ($grouped as $groupScope => $groupRules)
                <x-card :title="$scopes[$groupScope] ?? $groupScope" :subtitle="Format::number($groupRules->count()).' قاعده'"
                    icon="check-circle" padding="p-0">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($groupRules as $rule)
                            @php($isMine = $rule->workshop_id === $workshopId)

                            <li class="flex flex-wrap items-start gap-3 px-5 py-4">
                                <div class="min-w-56 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-stone-800">{{ $rule->name }}</p>
                                        <x-badge :color="match ($rule->severity) {
                                            'warning' => 'rose',
                                            'info' => 'sky',
                                            default => 'brand',
                                        }">
                                            {{ $rule->severityLabel() }}
                                        </x-badge>
                                        @if ($isMine)
                                            <x-badge color="emerald">قاعده کارگاه شما</x-badge>
                                        @else
                                            <x-badge color="gray" icon="star">سراسری</x-badge>
                                        @endif
                                        @if (! $rule->is_active)
                                            <x-badge color="amber">غیرفعال</x-badge>
                                        @endif
                                    </div>

                                    <p class="mt-1.5 text-xs leading-6 text-stone-500">
                                        اگر
                                        @foreach ($rule->conditions['all'] ?? [] as $condition)
                                            <span class="font-medium text-stone-700">
                                                {{ RuleEngine::fieldLabel($condition['field'] ?? '') }}
                                                {{ RuleEngine::OPERATORS[$condition['op'] ?? '='] ?? '' }}
                                                {{ is_array($condition['value'] ?? null) ? implode('، ', $condition['value']) : ($condition['value'] ?? '') }}
                                            </span>
                                            @if (! $loop->last)
                                                <span class="text-stone-400">و</span>
                                            @endif
                                        @endforeach
                                        @foreach ($rule->conditions['any'] ?? [] as $condition)
                                            <span class="font-medium text-stone-700">
                                                {{ RuleEngine::fieldLabel($condition['field'] ?? '') }}
                                                {{ RuleEngine::OPERATORS[$condition['op'] ?? '='] ?? '' }}
                                                {{ is_array($condition['value'] ?? null) ? implode('، ', $condition['value']) : ($condition['value'] ?? '') }}
                                            </span>
                                            @if (! $loop->last)
                                                <span class="text-stone-400">یا</span>
                                            @endif
                                        @endforeach
                                        <span class="text-stone-400">→</span>
                                        @foreach ($rule->actions ?? [] as $action)
                                            <span class="font-medium text-brand-700">
                                                {{ RuleEngine::actionLabel($action['type'] ?? '') }}
                                            </span>
                                            @if (! $loop->last)
                                                <span class="text-stone-400">،</span>
                                            @endif
                                        @endforeach
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($isMine)
                                        <x-button href="{{ route('rules.edit', $rule) }}" variant="ghost" size="sm"
                                            icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('rules.destroy', $rule)"
                                            :message="'قاعده «'.$rule->name.'» حذف شود؟'" label="" />
                                    @else
                                        <span class="px-2 text-xs text-stone-400">قابل ویرایش نیست</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>
