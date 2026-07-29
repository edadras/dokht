@php
    use App\Support\Format;
@endphp

<x-app-layout title="متعلقات">
    <x-page-header title="متعلقات" subtitle="نخ، دکمه، زیپ، لایی، آستر و کشی؛ هرچه در کشوی کارگاه دارید.">
        <x-slot:actions>
            <x-button href="{{ route('materials.create') }}" icon="plus">ثبت متعلقات</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-stat label="ارزش انبار متعلقات" :value="Format::money($totalValue)" icon="money" color="emerald" />
        <x-stat label="تعداد قلم" :value="Format::number($grouped->flatten()->count())" icon="box" />
    </div>

    <form method="GET" action="{{ route('materials.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field label="جست‌وجو" name="q" class="min-w-56 flex-1">
            <x-input name="q" :value="$q" placeholder="نام، مشخصات یا رنگ…" />
        </x-field>
        <x-button type="submit" variant="secondary" icon="search">جست‌وجو</x-button>
    </form>

    @if ($grouped->isEmpty())
        <x-empty-state icon="box" title="هنوز متعلقاتی ثبت نکرده‌اید"
            description="با ثبت نخ‌ها، دکمه‌ها و زیپ‌ها می‌توانید هزینه هر سفارش را دقیق حساب کنید.">
            <x-slot:action>
                <x-button href="{{ route('materials.create') }}" icon="plus">ثبت اولین قلم</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="space-y-6">
            @foreach ($kinds as $kind => $kindLabel)
                @continue(! $grouped->has($kind))

                <x-card :title="$kindLabel" :subtitle="Format::number($grouped[$kind]->count()).' قلم'" icon="box"
                    padding="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-sm">
                            <thead class="bg-stone-50 text-xs text-stone-500">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium">نام</th>
                                    <th class="px-4 py-2 text-start font-medium">مشخصات</th>
                                    <th class="px-4 py-2 text-start font-medium">رنگ</th>
                                    <th class="px-4 py-2 text-start font-medium">موجودی</th>
                                    <th class="px-4 py-2 text-start font-medium">قیمت</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($grouped[$kind] as $material)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-stone-800">{{ $material->name }}</td>
                                        <td class="px-4 py-3 text-stone-600">{{ $material->spec ?: '—' }}</td>
                                        <td class="px-4 py-3 text-stone-600">{{ $material->color ?: '—' }}</td>
                                        <td class="px-4 py-3 text-stone-700">
                                            {{ Format::number($material->stock, 1) }} {{ $material->unit }}
                                        </td>
                                        <td class="px-4 py-3 text-stone-700">{{ Format::money($material->price) }}</td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-button href="{{ route('materials.edit', $material) }}" variant="ghost"
                                                    size="sm" icon="edit">ویرایش</x-button>
                                                <x-confirm-delete :action="route('materials.destroy', $material)"
                                                    :message="'«'.$material->name.'» حذف شود؟'" label="" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach

        @if ($materials->hasPages())
            <div class="mt-6">{{ $materials->links() }}</div>
        @endif
        </div>
    @endif
</x-app-layout>
