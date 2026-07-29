# قرارداد «دوختِ سه‌بعدی» — از قطعه‌های الگو تا پارچه روی مانکن

نمای سه‌بعدی تا امروز لباس را **پارامتری** می‌ساخت: از مقطع بدن، آزادی هر ناحیه و
یک ردهٔ فرم (چسبان/راسته/خط A/کلوش) یک پوسته درمی‌آمد. تناسب و افتادگی درست بود،
اما آنچه دیده می‌شد دوختِ خودِ الگو نبود؛ درز پرنسسی، ساسون، پیلی و فرم یقه در آن
پوسته وجود نداشتند.

این سند قرارداد نسخهٔ تازه است: **قطعه‌های واقعی الگو مثلث‌بندی می‌شوند، دور بدن
چیده می‌شوند، درزها به هم دوخته می‌شوند و همان حل‌کنندهٔ PBD آن‌ها را روی بدن
می‌نشاند.** دو طرف این قرارداد مستقل ساخته می‌شوند و فقط از راه همین سند به هم
وصل‌اند.

## واحدها و دستگاه مختصات

| جا | واحد | محور |
|---|---|---|
| بسته (payload) | سانتی‌متر | x به راست، y به **پایین** (همان دستگاه الگو) |
| مرورگر | متر | x به راست، y به **بالا**، z به سمت بیننده (دستگاه three.js) |

تبدیل در سمت مرورگر انجام می‌شود: `metre = cm / 100` و `y_three = -y_pattern`.

## بستهٔ سرور

`App\Services\Simulation\DrapePayloadService::payload(Pattern $pattern): array`

```jsonc
{
  "scale": 0.01,                    // ضریب تبدیل سانتی‌متر به متر
  "pieces": [
    {
      "id": "front-bodice#0",       // یکتا در کل بسته: code#instance
      "code": "front-bodice",
      "name": "تنه جلو",
      "role": "torso",              // torso | sleeve | skirt | leg | collar | detail
      "side": "front",              // front | back | left | right | null
      "instance": 0,                // ۰ تا quantity-1
      "mirrored": false,            // آیا این نمونه آینه‌شدهٔ نمونهٔ اول است
      "layer": "outer",             // outer | lining | interfacing …
      "polygon": [[x, y], …],       // خط شکستهٔ بسته، پادساعتگرد، سانتی‌متر
      "edges": [                    // یک درایه به ازای هر لبهٔ اصلیِ الگو
        { "tag": "shoulder", "start": 0, "end": 1, "length": 13.4 }
      ],
      "darts": [
        { "legs": [[x, y], [x, y]], "apex": [x, y], "intake": 3.2,
          "on_edge": 0, "start": 4, "end": 9 }
      ],
      "placement": {
        "zone": "torso_front",      // torso_front | torso_back | sleeve | skirt_front |
                                    // skirt_back | leg_front | leg_back | collar | detail
        "u0": -1.35, "u1": 1.35,    // بازهٔ زاویه‌ای دور بدن (رادیان، ۰ = مرکز جلو)
        "y_top": 0.82,              // ارتفاع لبهٔ بالای قطعه، ضریبی از قد بدن
        "radius_hint": "bust",      // کدام تراز بدن شعاعِ چیدن اولیه را می‌دهد
        "flip": false               // برای نمونهٔ آینه‌شده
      }
    }
  ],
  "seams": [
    {
      "a": { "piece": "front-bodice#0", "edge": 3 },   // edge = اندیس در آرایهٔ edges
      "b": { "piece": "back-bodice#0",  "edge": 1 },
      "label": "درز پهلو",
      "reverse": true,      // لبهٔ b در جهت مخالف a پیموده می‌شود
      "ease": 0.4           // اختلاف طول دو لبه به سانتی‌متر (b − a)
    }
  ],
  "budget": { "target_edge": 3.0, "max_vertices": 6000 }
}
```

### قاعده‌های بستهٔ سرور

۱. **قطعهٔ روی تای پارچه باز می‌شود.** `PieceOps::unfold` پیش از هر چیز اجرا
   می‌شود، پس `polygon` همان قطعهٔ کاملِ بریده‌شده است، نه نصفش.
۲. **هر برش یک نمونه است.** `cut_quantity` دو با `mirror` یعنی دو درایه در
   `pieces` با `instance` صفر و یک، که دومی آینه‌شده و `side` آن `left`/`right`
   است. سرور خودش آینه می‌کند؛ مرورگر `polygon` را همان‌طور که هست می‌گیرد.
۳. **`polygon` خط شکسته است** (`Geometry::flatten`)، نه مسیر با منحنی.
۴. **`edges` پل میان دو دنیاست**: `start` و `end` اندیس رأس در `polygon`اند، پس
   مرورگر می‌تواند یک درز را با طول کمانی نمونه‌برداری کند و جفتش را پیدا کند.
۵. **ساسون** اگر روی مسیر بریده شده باشد با `start`/`end` می‌آید (دو ساق روی
   مرز)، وگرنه با `legs`/`apex`. در هر دو حال دو ساق باید به هم دوخته شوند —
   همین است که ساسون را در سه‌بعدی دیدنی می‌کند.
۶. **آستر و لایی جدا می‌آیند** ولی `layer` دارند تا مرورگر بتواند خاموششان کند.
۷. `seams` از `SewingRelationBuilder::suggest` می‌آید و به شمارهٔ لبهٔ همین بسته
   ترجمه می‌شود؛ رابطه‌ای که یک سرش پیدا نشود **دور ریخته نمی‌شود، گزارش می‌شود**
   (`meta.unmatched`).

## سمت مرورگر

`resources/js/lib/pattern-drape.js` (تازه) — بدون وابستگی به three.js:

```js
buildDrape(payload, body, options) → {
  patches: [ { id, patch: TriPatch, mesh: {positions, indices, uv}, piece } ],
  seams:   [ SeamSet ],
  stats:   { vertices, triangles, dropped }
}
```

- `triangulate(polygon, { target })` → `{ positions, indices, boundary }`
  که `boundary[i]` اندیس رأسِ نقطهٔ `polygon[i]` است.
- چیدن اولیه: هر قطعه روی استوانه‌ای با شعاعِ `radius_hint` و بازهٔ `u0..u1`
  پیچیده می‌شود، لبهٔ بالا روی `y_top`. این فقط نقطهٔ شروع است؛ درزها بقیه را
  جمع می‌کنند.
- `body` همان جدول مقطعی است که `garment-viewer` برای مانکن می‌سازد
  (`{ level, radii, profile, armTable, armLength }`).

`resources/js/lib/cloth-solver.js` (افزوده):

- `export class TriPatch` — مثل `ClothPatch` رفتار می‌کند (`predict`, `project`,
  `collide`, `finish`, `rest`, `remember`, `drift`) ولی قیدهایش از مثلث‌های یک مش
  دلخواه می‌آید: هر یال یک قید فاصله، هر جفت مثلث همسایه یک قید خمش.
- `export class SeamSet` — جفت‌های رأس میان دو تکه، با `strength` که هنگام
  «دوختن» از صفر به یک می‌رسد تا قطعه‌ها بی‌پرش به هم برسند.
- `ClothWorld.addSeam(seamSet)` و اجرای درزها در همان حلقهٔ `project`.

## قاعده‌های سمت مرورگر

۱. **قطعی بودن**: هیچ `Math.random`؛ برای شکستن تقارن از `hash(i)` موجود.
۲. **بودجه**: مجموع رأس‌ها از `budget.max_vertices` بیشتر نشود؛ اگر شد،
   `target_edge` بزرگ‌تر می‌شود تا جا شود. چیزی بی‌صدا حذف نمی‌شود؛ در `stats`
   گزارش می‌شود.
۳. **افت‌پذیری**: روی دستگاه کند اول تکرارها کم می‌شوند، بعد زیرگام‌ها.
۴. **بازگشت به عقب**: اگر بسته `drape` نداشت یا مثلث‌بندی شکست خورد، نمای
   پارامتریِ امروز سر جایش می‌ماند. نمای سه‌بعدی هرگز نباید سفید بماند.
