<?php

namespace App\Http\Controllers;

use App\Models\DesignRule;
use App\Services\Rules\RuleEngine;
use App\Support\Jalali;
use App\Support\WorkshopContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * قواعد طراحی.
 *
 * قواعد سراسری فقط خواندنی هستند؛ هر کارگاه می‌تواند قاعده خودش را با یک فرم
 * دوستانه بسازد (انتخاب ویژگی، عملگر، مقدار و نوع کنش) بدون نوشتن هیچ JSON.
 */
class DesignRuleController extends Controller
{
    public function __construct(protected WorkshopContext $context) {}

    public function index(Request $request)
    {
        Gate::authorize('manage');

        $scope = $request->query('scope');
        $workshopId = $this->context->id();

        $rules = DesignRule::query()
            ->availableTo($workshopId)
            ->when($scope && isset(DesignRule::SCOPES[$scope]), fn ($query) => $query->where('scope', $scope))
            ->orderBy('scope')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return view('rules.index', [
            'rules' => $rules,
            'grouped' => $rules->groupBy('scope'),
            'scopes' => DesignRule::SCOPES,
            'scope' => $scope,
            'workshopId' => $workshopId,
            'counts' => [
                'global' => $rules->whereNull('workshop_id')->count(),
                'mine' => $rules->where('workshop_id', $workshopId)->count(),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize('manage');

        return view('rules.create', $this->formData(new DesignRule([
            'scope' => 'fabric',
            'severity' => 'suggestion',
            'priority' => 100,
            'is_active' => true,
        ])));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage');

        $data = $this->validateRule($request);

        $rule = new DesignRule($this->attributes($data));
        $rule->workshop_id = $this->context->id();
        $rule->code = $this->uniqueCode($data['name']);
        $rule->save();

        return redirect()->route('rules.index', ['scope' => $rule->scope])
            ->with('success', 'قاعده «'.$rule->name.'» ساخته شد.');
    }

    public function edit(DesignRule $designRule)
    {
        Gate::authorize('manage');
        $this->authorizeOwnership($designRule);

        return view('rules.edit', $this->formData($designRule));
    }

    public function update(Request $request, DesignRule $designRule)
    {
        Gate::authorize('manage');
        $this->authorizeOwnership($designRule);

        $designRule->update($this->attributes($this->validateRule($request)));

        return redirect()->route('rules.index', ['scope' => $designRule->scope])
            ->with('success', 'قاعده به‌روز شد.');
    }

    public function destroy(DesignRule $designRule)
    {
        Gate::authorize('manage');
        $this->authorizeOwnership($designRule);

        $designRule->delete();

        return redirect()->route('rules.index')->with('success', 'قاعده حذف شد.');
    }

    /** قواعد سراسری و قواعد کارگاه‌های دیگر برای این کارگاه وجود ندارند. */
    protected function authorizeOwnership(DesignRule $rule): void
    {
        abort_if($rule->workshop_id === null || $rule->workshop_id !== $this->context->id(), 404);
    }

    protected function formData(DesignRule $rule): array
    {
        $conditions = $rule->conditions ?? [];
        $match = isset($conditions['any']) && ! empty($conditions['any']) ? 'any' : 'all';
        $rows = $conditions[$match] ?? (array_is_list($conditions) ? $conditions : []);

        return [
            'rule' => $rule,
            'match' => $match,
            'conditionRows' => array_values(array_filter($rows, 'is_array')) ?: [['field' => '', 'op' => '>', 'value' => '']],
            'actionRows' => array_values(array_filter($rule->actions ?? [], 'is_array')) ?: [['type' => 'note', 'text' => '']],
            'scopes' => DesignRule::SCOPES,
            'severities' => DesignRule::SEVERITIES,
            'factGroups' => RuleEngine::factGroups(),
            'factFields' => RuleEngine::FACT_FIELDS,
            'operators' => RuleEngine::OPERATORS,
            'actionTypes' => RuleEngine::ACTION_TYPES,
        ];
    }

    protected function validateRule(Request $request): array
    {
        // ردیف‌های خالی فرم پیش از اعتبارسنجی کنار گذاشته می‌شوند
        $request->merge([
            'conditions' => array_values(array_filter(
                (array) $request->input('conditions', []),
                fn ($row) => is_array($row) && ! empty($row['field']),
            )),
            'actions' => array_values(array_filter(
                (array) $request->input('actions', []),
                fn ($row) => is_array($row) && ! empty($row['type']),
            )),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(array_keys(DesignRule::SCOPES))],
            'severity' => ['required', Rule::in(array_keys(DesignRule::SEVERITIES))],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'match' => ['nullable', Rule::in(['all', 'any'])],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.field' => ['required', Rule::in(array_keys(RuleEngine::FACT_FIELDS))],
            'conditions.*.op' => ['required', Rule::in(array_keys(RuleEngine::OPERATORS))],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', Rule::in(array_keys(RuleEngine::ACTION_TYPES))],
        ], [
            'conditions.required' => 'حداقل یک شرط لازم است.',
            'actions.required' => 'حداقل یک کنش لازم است.',
        ], [
            'name' => 'نام قاعده',
            'scope' => 'دامنه',
            'severity' => 'شدت',
            'priority' => 'اولویت',
        ]);
    }

    /** تبدیل ورودی فرم دوستانه به ساختار داده‌ای قاعده. */
    protected function attributes(array $data): array
    {
        $match = $data['match'] ?? 'all';
        $conditions = [];

        foreach ($data['conditions'] as $row) {
            $conditions[] = [
                'field' => $row['field'],
                'op' => $row['op'],
                'value' => $this->conditionValue($row['field'], $row['op'], $row['value'] ?? null),
            ];
        }

        return [
            'name' => $data['name'],
            'scope' => $data['scope'],
            'severity' => $data['severity'],
            'priority' => (int) ($data['priority'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'conditions' => [$match => $conditions],
            'actions' => $this->actionPayload($data['actions']),
        ];
    }

    protected function actionPayload(array $rows): array
    {
        $actions = [];

        foreach ($rows as $row) {
            $type = (string) $row['type'];
            $action = ['type' => $type];

            foreach (RuleEngine::ACTION_TYPES[$type]['inputs'] ?? [] as $key => $input) {
                $value = $row[$key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $action[$key] = ($input['type'] ?? 'text') === 'number'
                    ? (float) Jalali::latinDigits((string) $value)
                    : (string) $value;
            }

            $actions[] = $action;
        }

        return $actions;
    }

    /** مقدار شرط بر پایه نوع ویژگی انتخاب‌شده و عملگر. */
    protected function conditionValue(string $field, string $op, mixed $raw): mixed
    {
        $type = RuleEngine::FACT_FIELDS[$field]['type'] ?? 'text';

        if ($op === 'in') {
            $items = is_array($raw)
                ? $raw
                : preg_split('/\s*[,،]\s*/u', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

            return array_values(array_map(fn ($item) => $this->cast($type, $item), $items ?: []));
        }

        if ($op === 'between') {
            $items = is_array($raw)
                ? $raw
                : preg_split('/\s*[-,،]\s*/u', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

            $items = array_values(array_map(fn ($item) => $this->cast('number', $item), array_slice($items ?: [], 0, 2)));

            return count($items) === 2 ? $items : [0, 0];
        }

        return $this->cast($type, $raw);
    }

    protected function cast(string $type, mixed $value): mixed
    {
        $value = is_string($value) ? trim((string) Jalali::latinDigits($value)) : $value;

        return match ($type) {
            'ratio', 'percent', 'number' => (float) $value,
            'bool' => (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }

    protected function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'rule';
        $workshopId = $this->context->id();
        $code = Str::limit($base, 48, '');
        $suffix = 1;

        while (DesignRule::query()->where('workshop_id', $workshopId)->where('code', $code)->exists()) {
            $code = Str::limit($base, 48, '').'-'.(++$suffix);
        }

        return $code;
    }
}
