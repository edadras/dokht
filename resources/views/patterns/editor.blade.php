<x-app-layout :title="'ویرایشگر '.$pattern->name" wide>
    <x-page-header title="ویرایشگر دوبعدی" :subtitle="$pattern->name" :back="route('patterns.show', $pattern)" />

    <div x-data="patternEditor(@js($config))" class="space-y-4">
        {{-- نوار ابزار --}}
        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-1">
                <button type="button" x-on:click="zoomOut()" class="rounded-lg p-2 text-stone-500 hover:bg-stone-100"
                    title="کوچک‌نمایی">
                    <x-icon name="search" class="h-5 w-5" />
                </button>
                <span class="w-14 text-center text-xs font-semibold text-stone-500"
                    x-text="Math.round(zoom * 100) + '٪'"></span>
                <button type="button" x-on:click="zoomIn()" class="rounded-lg p-2 text-stone-500 hover:bg-stone-100"
                    title="بزرگ‌نمایی">
                    <x-icon name="plus" class="h-5 w-5" />
                </button>
                <button type="button" x-on:click="fit()" class="rounded-lg p-2 text-stone-500 hover:bg-stone-100"
                    title="نمای کامل">
                    <x-icon name="refresh" class="h-5 w-5" />
                </button>
            </div>

            <span class="mx-1 h-6 w-px bg-stone-200"></span>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                @foreach (['seam' => 'خط برش', 'grainline' => 'راستای پارچه', 'notches' => 'نشانه‌ها', 'darts' => 'ساسون', 'markers' => 'خطوط راهنما'] as $key => $label)
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-stone-50 px-2.5 py-1.5">
                        <input type="checkbox" x-model="show.{{ $key }}"
                            class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <span class="mx-1 h-6 w-px bg-stone-200"></span>

            <button type="button" x-on:click="undo()" x-bind:disabled="! canUndo"
                class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 disabled:opacity-40" title="بازگشت">
                <x-icon name="arrow-right" class="h-5 w-5" />
            </button>
            <button type="button" x-on:click="redo()" x-bind:disabled="! canRedo"
                class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 disabled:opacity-40" title="جلو">
                <x-icon name="arrow-left" class="h-5 w-5" />
            </button>

            <div class="ms-auto flex items-center gap-2">
                <span class="text-xs text-stone-500">نسخه <span x-text="version"></span></span>
                <span x-show="dirty" x-cloak class="text-xs font-semibold text-amber-600">ذخیره نشده</span>
                <x-button type="button" x-on:click="save()" x-bind:disabled="saving" icon="check">
                    <span x-text="saving ? 'در حال ذخیره…' : 'ذخیره'"></span>
                </x-button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-4">
            {{-- بوم رسم --}}
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-stone-50">
                    <svg x-ref="canvas" class="h-[36rem] w-full touch-none select-none"
                        x-bind:viewBox="viewBox()" preserveAspectRatio="xMidYMid meet"
                        x-on:pointerdown="startPan($event)" x-on:pointermove="onMove($event)"
                        x-on:pointerup="endDrag()" x-on:pointerleave="endDrag()" x-on:wheel.prevent="onWheel($event)">
                        <template x-for="(piece, index) in pieces" x-bind:key="piece.id">
                            <g x-bind:transform="`translate(${offsets[index]?.x || 0} ${offsets[index]?.y || 0})`"
                                x-bind:data-piece-id="piece.id">
                                {{-- خط برش --}}
                                <path x-show="show.seam" x-bind:d="seamPath(piece)" fill="none" stroke="#a78bfa"
                                    stroke-width="0.12" stroke-dasharray="1.2 0.8" />

                                {{-- خط دوخت --}}
                                <path x-bind:d="path(piece)" x-on:click="selectPiece(index)"
                                    x-bind:fill="index === selected ? '#ede9fe' : '#ffffff'" fill-opacity="0.9"
                                    x-bind:stroke="index === selected ? '#7c4ddb' : '#44403c'" stroke-width="0.2"
                                    stroke-linejoin="round" class="cursor-pointer" />

                                {{-- خطوط راهنما --}}
                                <template x-for="marker in (show.markers ? (piece.markers || []) : [])">
                                    <line x-bind:x1="marker.from?.x" x-bind:y1="marker.from?.y"
                                        x-bind:x2="marker.to?.x" x-bind:y2="marker.to?.y" stroke="#a8a29e"
                                        stroke-width="0.1" stroke-dasharray="1 1" />
                                </template>

                                {{-- ساسون‌ها --}}
                                <template x-for="dart in (show.darts ? (piece.darts || []) : [])">
                                    <g>
                                        <line x-bind:x1="dart.legs?.[0]?.x" x-bind:y1="dart.legs?.[0]?.y"
                                            x-bind:x2="dart.apex?.x" x-bind:y2="dart.apex?.y" stroke="#7c4ddb"
                                            stroke-width="0.12" />
                                        <line x-bind:x1="dart.legs?.[1]?.x" x-bind:y1="dart.legs?.[1]?.y"
                                            x-bind:x2="dart.apex?.x" x-bind:y2="dart.apex?.y" stroke="#7c4ddb"
                                            stroke-width="0.12" />
                                    </g>
                                </template>

                                {{-- راستای پارچه --}}
                                <line x-show="show.grainline && piece.grainline" x-bind:x1="piece.grainline?.from?.x"
                                    x-bind:y1="piece.grainline?.from?.y" x-bind:x2="piece.grainline?.to?.x"
                                    x-bind:y2="piece.grainline?.to?.y" stroke="#57534e" stroke-width="0.14" />

                                {{-- نشانه‌های جفت‌شدن --}}
                                <template x-for="notch in (show.notches ? (piece.notches || []) : [])">
                                    <circle x-bind:cx="notch.x" x-bind:cy="notch.y" r="0.3" fill="none"
                                        stroke="#d4573e" stroke-width="0.12" />
                                </template>

                                {{-- دستگیره نقطه‌ها --}}
                                <template x-for="(point, pointIndex) in piece.outline" x-bind:key="pointIndex">
                                    <g>
                                        <line x-show="point.curve && index === selected" x-bind:x1="point.x"
                                            x-bind:y1="point.y" x-bind:x2="point.cx" x-bind:y2="point.cy"
                                            stroke="#0ea5e9" stroke-width="0.08" stroke-dasharray="0.6 0.4" />

                                        <circle x-show="point.curve && index === selected" x-bind:cx="point.cx"
                                            x-bind:cy="point.cy" r="0.34" fill="#0ea5e9"
                                            class="cursor-move"
                                            x-on:pointerdown="startDrag($event, index, pointIndex, 'control')" />

                                        <circle x-bind:cx="point.x" x-bind:cy="point.y"
                                            x-bind:r="index === selected ? 0.42 : 0.28"
                                            x-bind:fill="index === selected && pointIndex === selectedPoint ? '#d4573e' : '#7c4ddb'"
                                            class="cursor-move"
                                            x-on:pointerdown="startDrag($event, index, pointIndex, 'point')" />
                                    </g>
                                </template>

                                {{-- نام قطعه --}}
                                <text x-bind:x="0" x-bind:y="-1" font-size="1.6" fill="#57534e"
                                    x-text="piece.name"></text>
                            </g>
                        </template>
                    </svg>
                </div>

                <p class="mt-2 text-xs text-stone-500">
                    برای جابه‌جایی نقشه، روی فضای خالی بکشید. نقطه‌ها با گام نیم سانتی‌متر جابه‌جا می‌شوند.
                </p>
            </div>

            {{-- ستون کنار --}}
            <div class="space-y-4">
                <x-card title="قطعه‌ها" icon="layers" padding="p-2">
                    <div class="max-h-56 space-y-1 overflow-y-auto thin-scroll">
                        <template x-for="(piece, index) in pieces" x-bind:key="'list-'+piece.id">
                            <button type="button" x-on:click="selectPiece(index)"
                                x-bind:class="index === selected ? 'bg-brand-50 text-brand-700' : 'hover:bg-stone-50 text-stone-600'"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-start text-sm">
                                <span class="flex-1 truncate" x-text="piece.name"></span>
                                <span class="text-xs text-stone-400" x-text="piece.cut_quantity + '×'"></span>
                            </button>
                        </template>
                    </div>
                </x-card>

                <template x-if="piece">
                    <div class="space-y-4">
                        <x-card title="اندازه قطعه" icon="ruler">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-xl bg-stone-50 px-3 py-2">
                                    <p class="text-xs text-stone-500">پهنا</p>
                                    <p class="font-semibold" x-text="info.width + ' سانتی‌متر'"></p>
                                </div>
                                <div class="rounded-xl bg-stone-50 px-3 py-2">
                                    <p class="text-xs text-stone-500">بلندی</p>
                                    <p class="font-semibold" x-text="info.height + ' سانتی‌متر'"></p>
                                </div>
                                <div class="rounded-xl bg-stone-50 px-3 py-2">
                                    <p class="text-xs text-stone-500">مساحت</p>
                                    <p class="font-semibold" x-text="info.area"></p>
                                </div>
                                <div class="rounded-xl bg-stone-50 px-3 py-2">
                                    <p class="text-xs text-stone-500">نقطه‌ها</p>
                                    <p class="font-semibold" x-text="info.points"></p>
                                </div>
                            </div>
                        </x-card>

                        <x-advanced-section title="جای دوخت هر لبه" description="مقدار پیش‌فرض از تنظیم الگو می‌آید.">
                            <div class="space-y-2">
                                <template x-for="edge in Array.from({ length: edgeCount(piece) }, (v, i) => i)"
                                    x-bind:key="'edge-'+edge">
                                    <div class="flex items-center gap-2">
                                        <span class="flex-1 text-xs text-stone-600"
                                            x-text="edgeLabel(piece, edge) + (isFoldEdge(piece, edge) ? ' (روی تا)' : '')"></span>
                                        <input type="number" step="0.1" min="0" max="10"
                                            x-bind:value="allowanceOf(piece, edge)"
                                            x-on:change="setAllowance(edge, $event.target.value)"
                                            class="w-20 rounded-lg border-stone-300 px-2 py-1 text-sm">
                                    </div>
                                </template>
                            </div>
                        </x-advanced-section>

                        <template x-if="(piece.darts || []).length">
                            <x-advanced-section title="عمق ساسون‌ها" description="پاهای ساسون خودکار جابه‌جا می‌شوند.">
                                <div class="space-y-2">
                                    <template x-for="(dart, dartIndex) in piece.darts" x-bind:key="'dart-'+dartIndex">
                                        <div class="flex items-center gap-2">
                                            <span class="flex-1 text-xs text-stone-600" x-text="dart.label"></span>
                                            <input type="number" step="0.25" min="0" max="20"
                                                x-bind:value="dart.intake"
                                                x-on:change="setDartIntake(dartIndex, $event.target.value)"
                                                class="w-20 rounded-lg border-stone-300 px-2 py-1 text-sm">
                                        </div>
                                    </template>
                                </div>
                            </x-advanced-section>
                        </template>
                    </div>
                </template>

                <x-alert type="tip">
                    نقطه‌ها را بکشید تا شکل قطعه تغییر کند؛ نقطه‌های آبی، کنترل منحنی‌اند. هر بار ذخیره، یک نسخه تازه
                    ثبت می‌شود و می‌توانید به عقب برگردید.
                </x-alert>
            </div>
        </div>

        {{-- پیام --}}
        <div x-show="toast" x-cloak class="fixed bottom-6 start-6 z-50">
            <div x-bind:class="toastType === 'error' ? 'bg-rose-600' : 'bg-emerald-600'"
                class="rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg" x-text="toast"></div>
        </div>
    </div>
</x-app-layout>
