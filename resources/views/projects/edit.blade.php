<x-app-layout title="ویرایش پروژه">
    <div class="mx-auto max-w-2xl">
        <x-page-header title="ویرایش پروژه" :subtitle="$project->name" :back="route('projects.show', $project)" />

        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf
            @method('PUT')

            <x-card>
                <div class="space-y-5">
                    <x-field label="نام پروژه" name="name" required>
                        <x-input name="name" :value="$project->name" />
                    </x-field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="وضعیت" name="status" required>
                            <x-select name="status" :options="\App\Models\Project::STATUSES" :selected="$project->status" />
                        </x-field>

                        <x-field label="مشتری" name="customer_id">
                            <x-select name="customer_id" placeholder="بدون مشتری"
                                :options="$customers->pluck('name', 'id')->all()" :selected="$project->customer_id" />
                        </x-field>
                    </div>

                    <x-field label="یادداشت" name="notes" hint="هر چیزی که موقع دوخت باید یادتان باشد.">
                        <x-textarea name="notes" :value="$project->notes" rows="5" />
                    </x-field>

                    <div class="flex flex-wrap gap-2">
                        <x-button type="submit" icon="check">ذخیره</x-button>
                        <x-button href="{{ route('projects.show', $project) }}" variant="secondary">انصراف</x-button>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
