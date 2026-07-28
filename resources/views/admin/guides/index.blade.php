<x-app-layout title="آموزش" wide>
    <x-page-header title="آموزش" subtitle="راهنمای برش و دوخت برای هر پارچه و هر مدل لباس.">
        <x-slot:actions>
            <x-button href="{{ route('admin.guides.create') }}" icon="plus">راهنمای تازه</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.partials.nav')

    <form method="GET" action="{{ route('admin.guides.index') }}"
        class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <x-field label="جست‌وجو" name="q">
            <x-input name="q" :value="$term" placeholder="عنوان یا خلاصه…" />
        </x-field>

        <x-field label="نوع راهنما" name="scope">
            <x-select name="scope" placeholder="همه" :selected="$scope" :options="\App\Models\Guide::SCOPES" />
        </x-field>

        <div class="flex items-end gap-2">
            <x-button type="submit" icon="search">فیلتر</x-button>
            <x-button href="{{ route('admin.guides.index') }}" variant="ghost">پاک کردن</x-button>
        </div>
    </form>

    @if ($guides->isEmpty())
        <x-empty-state icon="book" title="راهنمایی پیدا نشد">
            <x-slot:action>
                <x-button href="{{ route('admin.guides.create') }}" icon="plus">راهنمای تازه</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto thin-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">عنوان</th>
                            <th class="px-4 py-3 text-start font-medium">نوع</th>
                            <th class="px-4 py-3 text-start font-medium">مربوط به</th>
                            <th class="px-4 py-3 text-start font-medium">نکته‌ها</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($guides as $guide)
                            <tr>
                                <td class="max-w-md px-4 py-3">
                                    <span class="font-medium text-stone-800">{{ $guide->title }}</span>
                                    @if ($guide->summary)
                                        <span class="block text-xs text-stone-500">{{ $guide->summary }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-stone-600">{{ $guide->scopeLabel() }}</td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ $guide->fabricType?->name_fa ?? $guide->garmentType?->name_fa ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ \App\Support\Jalali::digits(count($guide->tips ?? [])) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button href="{{ route('admin.guides.edit', $guide) }}" size="sm"
                                            variant="ghost" icon="edit">ویرایش</x-button>
                                        <x-confirm-delete :action="route('admin.guides.destroy', $guide)"
                                            message="راهنمای «{{ $guide->title }}» حذف شود؟" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-5">{{ $guides->links() }}</div>
    @endif
</x-app-layout>
