@props(['action', 'message' => 'آیا مطمئن هستید؟ این کار قابل بازگشت نیست.', 'label' => 'حذف', 'size' => 'sm'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($message))" class="inline">
    @csrf
    @method('DELETE')
    <x-button type="submit" variant="ghost" :size="$size" icon="trash" class="text-rose-600 hover:bg-rose-50">
        {{ $label }}
    </x-button>
</form>
