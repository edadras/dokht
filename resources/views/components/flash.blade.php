@if (session('status') || session('error') || $errors->any())
    <div class="mb-5 space-y-3 no-print">
        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any() && ! session('error'))
            <x-alert type="error">
                لطفاً موارد مشخص‌شده را اصلاح کنید.
                @if ($errors->count() === 1)
                    <span class="font-normal">{{ $errors->first() }}</span>
                @endif
            </x-alert>
        @endif
    </div>
@endif
