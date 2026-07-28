<x-app-layout title="الگوهای پایه" wide>
    <x-page-header title="الگوهای پایه" subtitle="بلوک‌های پارامتریک مشترک همه کارگاه‌ها.">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.create') }}" icon="plus">الگوی پایه تازه</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.partials.nav')

    <form method="GET" action="{{ route('admin.templates.index') }}"
        class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="نام، شناسه یا تولیدکننده…" />
        </x-field>

        <x-field label="نوع لباس" name="garment_type">
            <x-select name="garment_type" placeholder="همه" :selected="$garmentTypeId"
                :options="$garmentTypes->pluck('name_fa', 'id')->all()" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('admin.templates.index') }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($templates->isEmpty())
        <x-empty-state icon="ruler" title="الگوی پایه‌ای پیدا نشد">
            <x-slot:action>
                <x-button href="{{ route('admin.templates.create') }}" icon="plus">الگوی پایه تازه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">پیش‌نمایش</th>
                            <th class="px-4 py-3 text-start font-medium">الگو</th>
                            <th class="px-4 py-3 text-start font-medium">نوع لباس</th>
                            <th class="px-4 py-3 text-start font-medium">تولیدکننده</th>
                            <th class="px-4 py-3 text-start font-medium">پارامترها</th>
                            <th class="px-4 py-3 text-start font-medium">دامنه</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($templates as $template)
                            <tr>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-xl border border-stone-200 bg-stone-50 p-1">
                                        @if ($template->preview_svg)
                                            <div class="max-h-full [&>svg]:max-h-12 [&>svg]:w-auto">
                                                {!! $template->preview_svg !!}
                                            </div>
                                        @else
                                            <x-icon name="ruler" class="h-5 w-5 text-stone-300" />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">{{ $template->name_fa }}</span>
                                    <span class="block text-xs text-stone-400" dir="ltr">{{ $template->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-stone-600">{{ $template->garmentType?->name_fa ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-stone-600" dir="ltr">{{ $template->generator }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits(count($template->default_params ?? [])) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($template->workshop_id)
                                        <x-badge color="clay">{{ $template->workshop?->name ?? 'کارگاه' }}</x-badge>
                                    @elseif ($template->is_public)
                                        <x-badge color="emerald">عمومی</x-badge>
                                    @else
                                        <x-badge color="slate">پنهان</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button href="{{ route('admin.templates.edit', $template) }}" size="sm"
                                            variant="ghost" icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('admin.templates.destroy', $template)"
                                            message="الگوی پایه «{{ $template->name_fa }}» حذف شود؟" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-5">{{ $templates->links() }}</div>
    @endif
</x-app-layout>
