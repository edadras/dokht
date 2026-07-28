{{--
    فرم دوستانه ساخت قاعده.
    کاربر هیچ JSON نمی‌نویسد: یک ویژگی از فهرست، یک عملگر، یک مقدار و بعد نوع کنش.
--}}
@php
    $isEdit = $rule->exists;
    $builder = [
        'fields' => $factFields,
        'actionTypes' => $actionTypes,
        'conditions' => collect(old('conditions', $conditionRows))->map(fn ($row) => [
            'field' => $row['field'] ?? '',
            'op' => $row['op'] ?? '>',
            'value' => is_array($row['value'] ?? null) ? implode('، ', $row['value']) : (string) ($row['value'] ?? ''),
        ])->values()->all(),
        'actions' => collect(old('actions', $actionRows))->map(function ($row) {
            $out = ['type' => $row['type'] ?? 'note'];

            foreach (['text', 'key', 'value', 'edge'] as $payloadKey) {
                $out[$payloadKey] = (string) ($row[$payloadKey] ?? '');
            }

            return $out;
        })->values()->all(),
    ];
@endphp

<form method="POST" action="{{ $isEdit ? route('rules.update', $rule) : route('rules.store') }}" class="space-y-6"
    x-data="{
        fields: @js($builder['fields']),
        actionTypes: @js($builder['actionTypes']),
        conditions: @js($builder['conditions']),
        actions: @js($builder['actions']),
        addCondition() { this.conditions.push({ field: '', op: '>', value: '' }) },
        removeCondition(index) {
            this.conditions.splice(index, 1)
            if (this.conditions.length === 0) this.addCondition()
        },
        addAction() { this.actions.push({ type: 'note', text: '', key: '', value: '', edge: '' }) },
        removeAction(index) {
            this.actions.splice(index, 1)
            if (this.actions.length === 0) this.addAction()
        },
        definition(field) { return this.fields[field] || { type: 'text', options: null } },
        inputs(type) { return (this.actionTypes[type] || {}).inputs || {} },
    }">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card title="این قاعده چیست؟" icon="check-circle">
        <div class="space-y-4">
            <x-field label="نام قاعده" name="name" required hint="یک جمله کوتاه که خودتان بفهمید؛ مثلاً «پارچه لیز را با کاغذ ببر».">
                <x-input name="name" :value="$rule->name" required placeholder="مثلاً پارچه شفاف آستر لازم دارد" />
            </x-field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="کجا نشان داده شود؟" name="scope" required>
                    <x-select name="scope" :options="$scopes" :selected="$rule->scope" />
                </x-field>

                <x-field label="شدت" name="severity" required>
                    <x-select name="severity" :options="$severities" :selected="$rule->severity" />
                </x-field>
            </div>
        </div>
    </x-card>

    <x-card title="چه وقت اجرا شود؟" subtitle="شرط‌ها را بگذارید؛ ویژگی‌ها از شناسنامه پارچه و مدل لباس خوانده می‌شود."
        icon="search">
        <div class="space-y-4">
            <x-field label="ترکیب شرط‌ها" name="match">
                <x-select name="match" :options="['all' => 'همه شرط‌ها با هم برقرار باشند', 'any' => 'حداقل یکی از شرط‌ها برقرار باشد']" :selected="$match" />
            </x-field>

            <template x-for="(row, index) in conditions" :key="index">
                <div class="grid gap-3 rounded-xl border border-stone-200 bg-stone-50 p-3 sm:grid-cols-[2fr,1fr,1fr,auto]">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-500">ویژگی</label>
                        <select :name="`conditions[${index}][field]`" x-model="row.field"
                            class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                            <option value="">انتخاب کنید…</option>
                            @foreach ($factGroups as $groupLabel => $groupFields)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach ($groupFields as $field => $label)
                                        <option value="{{ $field }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-500">مقایسه</label>
                        <select :name="`conditions[${index}][op]`" x-model="row.op"
                            class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                            @foreach ($operators as $op => $label)
                                <option value="{{ $op }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-500">مقدار</label>

                        <template x-if="definition(row.field).type === 'option' && row.op !== 'in'">
                            <select :name="`conditions[${index}][value]`" x-model="row.value"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                <template x-for="(label, value) in definition(row.field).options || {}" :key="value">
                                    <option :value="value" x-text="label"></option>
                                </template>
                            </select>
                        </template>

                        <template x-if="definition(row.field).type === 'bool'">
                            <select :name="`conditions[${index}][value]`" x-model="row.value"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                <option value="1">بله</option>
                                <option value="0">خیر</option>
                            </select>
                        </template>

                        <template x-if="! ['bool'].includes(definition(row.field).type)
                            && ! (definition(row.field).type === 'option' && row.op !== 'in')">
                            <input :name="`conditions[${index}][value]`" x-model="row.value" type="text"
                                :placeholder="row.op === 'in' ? 'مقدارها را با ، جدا کنید' : (definition(row.field).type === 'ratio' ? 'بین ۰ و ۱' : 'مقدار')"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                        </template>
                    </div>

                    <div class="flex items-end">
                        <button type="button" @click="removeCondition(index)"
                            class="rounded-xl p-2 text-stone-400 transition hover:bg-rose-50 hover:text-rose-600"
                            title="حذف شرط">
                            <x-icon name="trash" class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </template>

            <x-button type="button" variant="soft" size="sm" icon="plus" x-on:click="addCondition()">
                افزودن شرط
            </x-button>

            @error('conditions')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </x-card>

    <x-card title="چه کاری انجام شود؟" subtitle="نتیجه قاعده؛ می‌توانید چند کنش با هم بگذارید." icon="sparkles">
        <div class="space-y-4">
            <template x-for="(row, index) in actions" :key="index">
                <div class="space-y-3 rounded-xl border border-stone-200 bg-stone-50 p-3">
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="mb-1 block text-xs font-medium text-stone-500">نوع کنش</label>
                            <select :name="`actions[${index}][type]`" x-model="row.type"
                                class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                @foreach ($actionTypes as $type => $definition)
                                    <option value="{{ $type }}">{{ $definition['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" @click="removeAction(index)"
                            class="rounded-xl p-2 text-stone-400 transition hover:bg-rose-50 hover:text-rose-600"
                            title="حذف کنش">
                            <x-icon name="trash" class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="(input, key) in inputs(row.type)" :key="key">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-stone-500" x-text="input.label"></label>

                                <template x-if="input.type === 'choice'">
                                    <select :name="`actions[${index}][${key}]`" x-model="row[key]"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                        <template x-for="(label, value) in input.options || {}" :key="value">
                                            <option :value="value" x-text="label"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="input.type !== 'choice'">
                                    <input :name="`actions[${index}][${key}]`" x-model="row[key]"
                                        :type="input.type === 'number' ? 'number' : 'text'" step="0.1"
                                        class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <x-button type="button" variant="soft" size="sm" icon="plus" x-on:click="addAction()">
                افزودن کنش
            </x-button>

            @error('actions')
                <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </x-card>

    <x-advanced-section title="تنظیمات پیشرفته" description="اولویت اجرا و فعال بودن قاعده.">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="اولویت" name="priority" hint="عدد کمتر یعنی زودتر بررسی می‌شود.">
                <x-input type="number" name="priority" :value="$rule->priority ?: 100" min="1" max="9999" />
            </x-field>

            <label class="flex items-center gap-2 self-end text-sm text-stone-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->exists ? $rule->is_active : true))
                    class="h-4 w-4 rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                این قاعده فعال است
            </label>
        </div>
    </x-advanced-section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-button href="{{ route('rules.index') }}" variant="ghost">بازگشت</x-button>
        <x-button type="submit" size="lg" icon="check">{{ $isEdit ? 'ذخیره تغییرات' : 'ساخت قاعده' }}</x-button>
    </div>
</form>
