<x-app-layout title="بانک پارچه" wide>
    <x-page-header title="بانک پارچه" subtitle="شناسنامه انواع پارچه؛ مشترک بین همه کارگاه‌ها.">
        <x-slot:actions>
            <x-button href="{{ route('admin.fabric-types.create') }}" icon="plus">پارچه تازه</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.partials.nav')

    <form method="GET" action="{{ route('admin.fabric-types.index') }}"
        class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="نام یا شناسه پارچه…" />
        </x-field>

        <x-field label="دسته" name="category">
            <x-select name="category" placeholder="همه" :selected="$category" :options="\App\Models\FabricType::CATEGORIES" />
        </x-field>

        <x-field label="خانواده بافت" name="family">
            <x-select name="family" placeholder="همه" :selected="$family" :options="\App\Models\FabricType::FAMILIES" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('admin.fabric-types.index') }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($types->isEmpty())
        <x-empty-state icon="palette" title="پارچه‌ای پیدا نشد" description="با فیلتر دیگری جست‌وجو کنید یا پارچه تازه بسازید.">
            <x-slot:action>
                <x-button href="{{ route('admin.fabric-types.create') }}" icon="plus">پارچه تازه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">پارچه</th>
                            <th class="px-4 py-3 text-start font-medium">دسته / بافت</th>
                            <th class="px-4 py-3 text-start font-medium">ترکیب الیاف</th>
                            <th class="px-4 py-3 text-start font-medium">وزن و لختی</th>
                            <th class="px-4 py-3 text-start font-medium">استفاده</th>
                            <th class="px-4 py-3 text-start font-medium">وضعیت</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($types as $type)
                            @php $fabricProfile = $type->fabricProfile(); @endphp

                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-stone-800">{{ $type->name_fa }}</span>
                                    <span class="block text-xs text-stone-400" dir="ltr">{{ $type->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ $type->categoryLabel() }}
                                    <span class="block text-xs text-stone-400">{{ $type->familyLabel() ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-stone-600">{{ $type->compositionLabel() ?: '—' }}</td>
                                <td class="px-4 py-3 text-xs text-stone-600">
                                    {{ $fabricProfile->weightLabel() }} •
                                    {{ $fabricProfile->drapeLabel() }}
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits($type->fabrics_count) }} پارچه
                                </td>
                                <td class="px-4 py-3">
                                    @if ($type->is_active)
                                        <x-badge color="emerald">فعال</x-badge>
                                    @else
                                        <x-badge color="slate">غیرفعال</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button href="{{ route('admin.fabric-types.edit', $type) }}" size="sm"
                                            variant="ghost" icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('admin.fabric-types.destroy', $type)"
                                            message="پارچه «{{ $type->name_fa }}» حذف شود؟" />
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
