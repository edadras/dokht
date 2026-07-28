<x-app-layout title="انواع لباس" wide>
    <x-page-header title="انواع لباس" subtitle="اجزا، آزادی پیش‌فرض و پارچه مناسب هر مدل.">
        <x-slot:actions>
            <x-button href="{{ route('admin.garment-types.create') }}" icon="plus">نوع لباس تازه</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.partials.nav')

    <form method="GET" action="{{ route('admin.garment-types.index') }}"
        class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="نام یا شناسه مدل…" />
        </x-field>

        <x-field label="دسته" name="category">
            <x-select name="category" placeholder="همه" :selected="$category" :options="\App\Models\GarmentType::CATEGORIES" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('admin.garment-types.index') }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($types->isEmpty())
        <x-empty-state icon="shirt" title="نوع لباسی پیدا نشد">
            <x-slot:action>
                <x-button href="{{ route('admin.garment-types.create') }}" icon="plus">نوع لباس تازه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">مدل</th>
                            <th class="px-4 py-3 text-start font-medium">دسته</th>
                            <th class="px-4 py-3 text-start font-medium">اجزا</th>
                            <th class="px-4 py-3 text-start font-medium">الگوی پایه</th>
                            <th class="px-4 py-3 text-start font-medium">الگوی ساخته‌شده</th>
                            <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($types as $type)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">{{ $type->name_fa }}</span>
                                    <span class="block text-xs text-stone-400" dir="ltr">{{ $type->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-stone-600">{{ $type->categoryLabel() }}</td>
                                <td class="max-w-xs px-4 py-3 text-xs text-stone-600">
                                    {{ implode('، ', array_slice($type->partLabels(), 0, 4)) }}
                                    @if (count($type->parts ?? []) > 4)
                                        <span class="text-stone-400">
                                            و {{ \App\Support\Jalali::digits(count($type->parts) - 4) }} جزء دیگر
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($type->pattern_templates_count) }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($type->patterns_count) }}</td>
                                <td class="px-4 py-3">
                                    @if ($type->is_active)
                                        <x-badge color="emerald">فعال</x-badge>
                                    @else
                                        <x-badge color="slate">غیرفعال</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button href="{{ route('admin.garment-types.edit', $type) }}" size="sm"
                                            variant="ghost" icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('admin.garment-types.destroy', $type)"
                                            message="نوع لباس «{{ $type->name_fa }}» حذف شود؟" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-5">{{ $types->links() }}</div>
    @endif
</x-app-layout>
