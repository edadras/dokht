<x-app-layout title="ردّ کار">
    @php $digits = fn ($v) => \App\Support\Jalali::digits((string) $v); @endphp

    <x-page-header title="ردّ کار کارگاه"
        subtitle="چه کسی، چه وقت، روی چه چیزی چه کرد."
        :back="route('workshop.edit')" />

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="کاربر" name="user">
            <select name="user" class="rounded-xl border-stone-200 text-sm focus:border-brand-400 focus:ring-brand-300">
                <option value="">همه</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected(($filters['user'] ?? null) == $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="نوع رکورد" name="subject">
            <select name="subject" class="rounded-xl border-stone-200 text-sm focus:border-brand-400 focus:ring-brand-300">
                <option value="">همه</option>
                @foreach ($subjects as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['subject'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="کار" name="action">
            <select name="action" class="rounded-xl border-stone-200 text-sm focus:border-brand-400 focus:ring-brand-300">
                <option value="">همه</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['action'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

        <x-button type="submit" variant="secondary" icon="search">فیلتر</x-button>
    </form>

    @if ($activities->isEmpty())
        <x-empty-state icon="clock" title="هنوز چیزی ثبت نشده"
            description="هر ساخت، ویرایش و حذفی که از این پس انجام شود، همین‌جا می‌آید." />
    @else
        <x-card padding="p-0">
            <ul class="divide-y divide-stone-100">
                @foreach ($activities as $activity)
                    <li class="flex flex-wrap items-start gap-3 px-4 py-3">
                        <span @class([
                            'mt-0.5 rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $activity->action === 'created',
                            'bg-amber-50 text-amber-700' => $activity->action === 'updated',
                            'bg-rose-50 text-rose-700' => $activity->action === 'deleted',
                            'bg-stone-100 text-stone-600' => ! in_array($activity->action, ['created', 'updated', 'deleted'], true),
                        ])>{{ $activity->actionLabel() }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-stone-700">{{ $activity->sentence() }}</p>

                            @if (! empty($activity->changes))
                                <p class="mt-1 text-xs text-stone-500">
                                    @foreach (array_slice($activity->changes, 0, 4, true) as $field => $change)
                                        <span class="me-3" dir="ltr">{{ $field }}: {{ $change['from'] ?? '—' }} ← {{ $change['to'] ?? '—' }}</span>
                                    @endforeach
                                    @if (count($activity->changes) > 4)
                                        <span>و {{ $digits(count($activity->changes) - 4) }} فیلد دیگر</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        <span class="text-xs text-stone-400">{{ \App\Support\Jalali::dateTime($activity->created_at) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        @if ($activities->hasPages())
            <div class="mt-6">{{ $activities->links() }}</div>
        @endif
    @endif
</x-app-layout>
