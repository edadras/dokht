<?php

namespace App\Services\Simulation;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SewingRelationBuilder;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Throwable;

/**
 * بسته «دوخت سه‌بعدی»: از قطعه‌های الگو تا پارچه‌ای که مرورگر روی مانکن می‌نشاند.
 *
 * شکل خروجی در docs/drape-contract.md نوشته شده و همان‌جا قرارداد دو سمت است.
 * کار این کلاس چهار تاست:
 *
 *   ۱. قطعه روی تای پارچه را باز می‌کند (وگرنه نصف لباس روی مانکن می‌رود).
 *   ۲. هر برش را به یک نمونه جدا تبدیل می‌کند و نمونه آینه‌ای را خودش آینه می‌کند.
 *   ۳. لبه‌های اصلی الگو را به بازه رأس روی خط شکسته ترجمه می‌کند — همین پل است
 *      که مرورگر با آن دو لبه درز را جفت می‌کند.
 *   ۴. رابطه‌های دوخت را به کمان‌های همین بسته برمی‌گرداند و هر رابطه‌ای را که
 *      سرش پیدا نشود در meta.unmatched گزارش می‌کند، نه اینکه دور بیندازد.
 *
 * همه طول‌ها سانتی‌متر است و محور y مثل خود الگو به پایین می‌رود؛ تبدیل به متر و
 * چرخاندن محور کار مرورگر است.
 */
class DrapePayloadService
{
    /** بیشترین رأسی که مرورگر برای همه قطعه‌ها با هم می‌پذیرد. */
    public const MAX_VERTICES = 6000;

    /** طول یال هدف مثلث‌بندی، سانتی‌متر. */
    public const TARGET_EDGE = 3.0;

    /** فاصله‌ای که کمتر از آن، یک نقطه «روی» رأس خط شکسته حساب می‌شود. */
    public const SNAP = 0.08;

    /** جریمه جفت‌شدن دو کمان از دو سمت بدن. */
    protected const SIDE_PENALTY = 1000.0;

    /**
     * جریمه جفت‌شدن رویه با آستر.
     *
     * از جریمهٔ سمت کمتر است: اگر واقعاً هیچ شریکِ هم‌لایه‌ای نبود، آستر بهتر از
     * بی‌دوخت ماندن است. ولی هر شریکِ هم‌لایه‌ای، هر قدر هم دورتر، بر آن می‌چربد.
     */
    protected const LAYER_PENALTY = 500.0;

    /**
     * لبه‌هایی که درزشان میان چند شریک تقسیم می‌شود، پس رأسِ میانی لازم دارند.
     *
     * درز روی رأس بریده می‌شود؛ لبه‌ای که رأسِ میانی ندارد نه قابلِ تقسیم است و
     * نه بیش از یک سوزن می‌گیرد. خط یقه و حلقه و سرشانه چون میان چند قطعه تقسیم
     * می‌شوند، و خط کمر چون کمربند میان همهٔ کمان‌هایش پخش می‌شود.
     *
     * شکستنِ همهٔ لبه‌های راست امتحان شد و بدتر بود: مثلث خراب از ۲۵ به ۵۱ رفت،
     * چون مرزِ لبهٔ بی‌درز هم ریز می‌شد.
     *
     * «پهلو» هم امتحان شد و ماند بیرون، با اینکه چهار لباس بهتر می‌شدند (ترنچ‌کت
     * مثلث خراب ۳۴ ← ۲۰، قپائو درز ۱۳٫۲ ← ۱۰٫۳ و پوستِ لخت ۵۰ ← ۳۹، لباس
     * ریش‌ریش درز ۲٫۲ ← ۱٫۸): دامنِ کلوش پوستِ لختش از ۰ به ۳۶ از ۹۶ می‌رفت.
     * علتش هم اندازه گرفته شد و «سنگینی» نبود — رأس ۴۷۰۲ ← ۴۷۱۴، طولِ یال و
     * شمارِ تلاشِ مثلث‌بندی دست‌نخورده. یعنی پوششِ آن دامن روی لبهٔ تیغ است و با
     * دوازده رأس این‌ور و آن‌ور می‌شود. تا آن ناپایداری درست نشود، این در نمی‌آید.
     */
    protected const SPLIT_TAGS = ['neck', 'armhole', 'shoulder', 'waist'];

    /**
     * گامِ شکستنِ هر برچسب، سانتی‌متر. برچسبی که این‌جا نیست گامِ پیش‌فرضِ ۵ می‌گیرد.
     *
     * خط کمر ریز می‌خواهد: کمربندِ دامنِ کلوش باید میان دوازده کمانِ خط کمر تقسیم
     * شود و نوارِ ۴۲٫۵ سانتی‌متری با گامِ ۵ تنها ۸ پاره می‌گیرد، پس splitArc
     * نمی‌تواند دوازده تکه بسازد و بی‌صدا رد می‌شود.
     *
     * درزِ پهلو برعکس، درشت می‌خواهد: هدف فقط بیرون‌آمدن از «یک سوزن روی ۵۰
     * سانتی‌متر» است و ریزکردنش بودجهٔ رأس را می‌خورد — با گامِ ۵ سانتی‌متری دامنِ
     * کلوش از حدِ رأس رد می‌شد و مثلث‌بندی درشت‌تر می‌گرفت: پوستِ لخت ۰ ← ۳۶ از ۹۶.
     */
    protected const SPLIT_STEPS = ['waist' => 2.0];

    /**
     * سهمِ کمانِ یک پنلِ آستین، حداکثر چند برابرِ سهمِ منصفانه‌اش.
     *
     * پنل‌ها دقیقاً کنار هم چیده نمی‌شوند: روی پارچه جای درز هست و پهنایشان از
     * دور بازو بیشتر است. اگر دقیقاً تقسیم شود، هر پنل فشرده می‌شود و آستین
     * کت‌وشلوار ۸ سانتی‌متر روی بازو سُر می‌خورد و تمام کت را پایین می‌کشد
     * (پوستِ لخت ۲ ← ۴۰). با اجازهٔ هم‌پوشانی، ۲ ← ۵.
     *
     * کمترش هم اندازه گرفته شد: با ۱٫۲ پوششِ آستینِ کت رسمی از ۱۹۵ به ۱۳۵ درجه
     * افتاد و بازوی لختش از ۳۲ به ۵۲ از ۳۱۲. بیشترش اثری ندارد — از ۱٫۵ به بعد
     * قیدِ دیگر (پهنای خودِ پنل روی استوانه) حرفِ آخر را می‌زند و ۱٫۸ همان
     * عددهای ۱٫۵ را داد.
     */
    protected const PANEL_OVERLAP = 1.5;

    /**
     * سرِ آستین چند برابرِ فاصلهٔ حلقه‌تا‌سرشانه بالاتر از تراز حلقه می‌نشیند.
     *
     * سرِ آستین همان کمانی است که به حلقه دوخته می‌شود، و بالاترین نقطهٔ حلقه
     * *سرشانه* است نه زیربغل. با تراز حلقه (یعنی صفر)، آستین ده سانتی‌متر
     * پایین‌تر می‌افتاد و سرشانهٔ گوشتی — بالای بازو، بیرون از بیضیِ تنه — لخت
     * می‌ماند. هیچ سنجه‌ای این را نمی‌دید چون همه‌شان روی *تنه* نقطه می‌گذاشتند و
     * سرِ بازو در هیچ ترازِ تنه‌ای نیست: فاصله تا نزدیک‌ترین مثلثِ تنه همه‌جا زیر
     * ۲٫۵ سانتی‌متر بود و باز هم در عکس پوست دیده می‌شد. کاربر همان گوه را روی
     * هر دو شانه دید. سنجهٔ armCapOf برای همین اضافه شد.
     *
     * چهار مقدار اندازه گرفته شد، جمعِ شش لباسِ آستین‌دار:
     *
     *   بلندکردن      ۰      ۰٫۵     ۰٫۷۵     ۱
     *   سرِ بازوی لخت  ۹۸۲    ۹۹۷     ۹۷۳    ۹۰۰
     *   مثلث خراب      ۲۰۲    ۱۷۱     ۲۱۹    ۲۰۹
     *   خطای درز      ۳۵٫۶   ۳۲٫۰    ۳۴٫۱   ۳۶٫۲
     *
     * ۰٫۵ بهترین است روی دو سنجهٔ سلامتِ پارچه، و گوهٔ پیراهن — همان که در عکس
     * دیده می‌شد — از ۶۰ به ۹ از ۲۸۸ می‌رسد. مقدارِ ۱ سرِ بازو را کمی بهتر
     * می‌کند ولی بدترین خطای درز را دارد.
     *
     * پس از عوض شدنِ ترتیبِ دوخت دوباره سنجیده شد، این بار با سنجهٔ بینایی:
     *
     *   ۰٫۵    پیراهن ۴٫۳٪  ترنچ‌کت ۳٫۴٪  راپ ۴٫۰٪  کت رسمی ۱۲٫۴٪
     *   ۰٫۷۵   پیراهن ۴٫۱٪  ترنچ‌کت ۳٫۰٪  راپ ۶٫۱٪  کت رسمی ۱۵٫۰٪
     *   ۱      پیراهن ۵٫۲٪  ترنچ‌کت *بی‌دوخت*  راپ ۵٫۹٪  کت رسمی ۱۸٫۱٪
     *
     * یعنی همان ۰٫۵ سرِ جایش می‌ماند: بالاتر بردنش شانه را کمی می‌پوشاند و در
     * عوض راپ و کت رسمی را باز می‌کند.
     *
     * و بار سوم، پس از برداشتنِ گنبدِ سرِ برخوردگرِ بازو (ببینید bodyColliders):
     *
     *   ۰٫۵    پیراهن ۴٫۵٪  کت ۳٫۶٪  ترنچ ۱٫۰٪  راپ ۱٫۴٪  کت رسمی ۱۰٫۴٪  (۳۹۰۴px)
     *   ۰٫۲۵   پیراهن ۳٫۸٪  کت ۳٫۸٪  ترنچ ۰٫۸٪  راپ ۱٫۴٪  کت رسمی  ۸٫۸٪  (۳۵۳۱px)
     *   ۰      پیراهن ۴٫۴٪  کت ۴٫۰٪  ترنچ ۰٫۹٪  راپ ۱٫۵٪  کت رسمی  ۸٫۵٪  (۳۶۶۴px)
     *
     * آن گنبد آستین را از پایین هُل می‌داد بالا، پس بلندکردنِ نصف لازم بود تا
     * سرِ آستین در حلقه بماند.
     *
     * و بار چهارم، پس از درست شدنِ چیدنِ سرشانه:
     *
     *   ۰٫۲۵   پیراهن ۳٫۵٪  کت ۱٫۷٪  ترنچ ۰٫۶٪  راپ ۱٫۰٪  کت رسمی ۸٫۱٪  (۲۸۲۸px)
     *   ۰      پیراهن ۲٫۹٪  کت ۰٫۳٪  ترنچ ۰٫۶٪  راپ ۱٫۱٪  کت رسمی ۷٫۷٪  (۲۳۵۸px)
     *   −۰٫۲۵  پیراهن ۵٫۳٪  کت ۰٫۳٪  ترنچ ۰٫۵٪  راپ ۱٫۷٪  کت رسمی ۸٫۷٪  (۳۱۵۸px)
     *   −۰٫۵   پیراهن ۵٫۴٪  کت ۰٫۴٪  ترنچ ۰٫۵٪  راپ ۱٫۷٪  کت رسمی ۸٫۷٪  (۳۱۷۹px)
     *
     * صفر است: زیربغلِ آستین روی خودِ حلقه می‌نشیند، نه بالاترش. هر بار که یک
     * خرابیِ دیگر رفع شد این عدد پایین‌تر آمد، و همین می‌گوید بلندکردن هیچ‌وقت
     * چیزی جز جبرانِ آن خرابی‌ها نبوده. پایین‌تر از صفر هم رفتیم و بدتر شد —
     * آستین از حلقه آویزان می‌ماند. بازوی لختِ کتِ اسپرت به ۰٫۳٪ رسید.
     *
     * و بارِ پنجم، پس از لنگردار شدنِ دوختِ بی‌وزنی (sewAnchored): تنه دیگر
     * در دوخت فرونمی‌نشیند و سرِ جای چیدنش می‌ماند — پس آستین باید *بالاتر*
     * چیده شود تا به همان تنهٔ بالامانده برسد، وگرنه دلتوئید لخت می‌ماند
     * (بیناییِ کت رسمی از ۵٫۵٪ به ۸٫۴٪ رفته بود). جاروبِ «بازوی لخت» بنچ:
     *
     *   سهم       بلیزر    ترنچ    پیراهن   کت رسمی        سُرخوردنِ کت رسمی
     *   ۰         ۸        ۲       ۵       ۲۴ از ۳۳۶      ۲٫۷cm
     *   ۰٫۳       ۳        ۱       ۳       ۱۶             ۰
     *   ۰٫۵       ۴        ۰       ۲       ۸              ۰
     *   ۰٫۷       ۰        ۱       ۱       ۸ از ۲۸۸       ۰
     *   ۱         ۰        ۲       ۱       ۰ (پوششِ ترنچ ۲۱۰° — بد)
     *
     * ۰٫۷: همه زیرِ ۱٪ و پوششِ هیچ مدلی نمی‌شکند. بینایی تأییدش کرد.
     */
    protected const SLEEVE_LIFT = 0.7;

    /**
     * وقتی پنل‌های یک آستین از زیربغل هم‌تراز می‌شوند، چه سهمی از اختلاف را
     * پنلِ کم‌عمق با *پایین* رفتن می‌دهد و چه سهمی پنلِ عمیق با *بالا* رفتن.
     *
     * صفر یعنی پنلِ کم‌عمق سرِ جایش می‌ماند و پنلِ کپ‌دار بالا می‌رود؛ یک یعنی
     * برعکس. هیچ‌کدامِ این دو سر خوب نیست: با یک، سرآستینِ کت رسمی سرِ جایش
     * می‌ماند ولی آستینِ زیر ۱۱٫۹ سانتی‌متر زیرِ حلقه می‌افتد و همان درز
     * ۶٫۵ سانتی‌متر باز می‌ماند (پوششِ دورِ بازو ۶۰ درجه)؛ با صفر، سرآستین
     * ۱۱٫۹ بالا می‌رود، هم‌ترازی درست می‌شود ولی چون spinFit اجازهٔ پایین رفتنِ
     * قطعهٔ روی اندام را تنها سه سانتی‌متر می‌دهد (VERTICAL_ROOM)، *تنه* بالا
     * کشیده می‌شود تا به آستین برسد و لباس از چانه بالاتر می‌زند.
     *
     * اندازه‌گیری، با همین کد:
     *
     *   سهم              ۰        ۰٫۰۵     ۰٫۱۲   ۰٫۱۵     ۰٫۲    ۰٫۲۵     ۱
     *   دروازهٔ کت رسمی ✗بالای‌سر ✗بالای‌سر  ✓    ✗بالای‌سر   ✓      ✓    ✗درزباز
     *   پوششِ کت رسمی    ۱۸۰°     ۱۶۵°    ۱۶۵°    ۱۶۵°    ۱۸۰°   ۱۵۰°    ۶۰°
     *   سوراخِ کت رسمی   ۱٫۶٪      —      ۱٫۶٪     —      ۱٫۶٪   ۱٫۶٪     —
     *   سوراخِ ترنچ‌کت    ۰٫۲٪      —      ۰٫۲٪     —      ۴٫۱٪   ۴٫۹٪     —
     *
     * ۰٫۱۲ برداشته شد: تنها مقداری که هم دروازه را رد می‌کند و هم ترنچ‌کت را
     * بدتر نمی‌کند.
     *
     * یک هشدار برای بعدی: خودِ دروازهٔ «بالای سر» این‌جا لبه‌ای است — ۰٫۱۵ ردش
     * می‌کند و ۰٫۱۲ و ۰٫۲ نه. علتش این است که سنجهٔ هندسی لباس را *بی بدن*
     * می‌دوزد (bench-drape.mjs: dress(0)) در حالی که نماگر با بدنِ کامل
     * (garment-solid.js: SEWING_BODY = 1)؛ لباسِ بی‌تن وا می‌رود و آن یکی دو
     * سانتی‌متر را چند برابر می‌کند. تا وقتی این دو یکی نشوند، عددِ بینایی
     * معتبرتر از دروازهٔ هندسی است. (آن هشدار امروز بی‌موضوع است: سنجهٔ هندسی
     * هم با بدنِ کامل می‌دوزد.)
     *
     * دوباره سنجیده شد، پس از رفعِ گنبدِ بازو و چیدنِ سرشانه و صفر شدنِ
     * SLEEVE_LIFT:
     *
     *   ۰٫۱۲  پیراهن ۲٫۸٪  کت ۰٫۳٪  ترنچ ۰٫۶٪  راپ ۱٫۱٪  کت رسمی ۷٫۷٪ (۲۳۸۳px)
     *   ۰٫۴   پیراهن ۲٫۸٪  کت ۰٫۳٪  ترنچ ۰٫۷٪  راپ ۱٫۱٪  کت رسمی ۷٫۷٪ (۲۴۳۵px)
     *   ۰٫۷   پیراهن ۲٫۸٪  کت ۰٫۴٪  ترنچ ۰٫۶٪  راپ ۱٫۱٪  کت رسمی ۶٫۹٪ (۲۳۱۲px)
     *   ۱     پیراهن ۲٫۸٪  کت ۰٫۶٪  ترنچ ۰٫۴٪  راپ ۱٫۱٪  کت رسمی ۹٫۱٪ (۲۶۹۸px)
     *
     * ۰٫۷ برداشته شد و دروازهٔ هندسی هم دست‌نخورده ماند. سهمِ بیشتر یعنی زیربغلِ
     * آستین پایین‌تر می‌نشیند، و آستینی که کپِ عمیق دارد — کت رسمی، ۱۱٫۹
     * سانتی‌متر — همان‌قدر پایین‌تر لازم دارد تا سرِ آستینش روی سرشانه بیفتد و
     * لبه‌اش به مچ برسد. شمارِ سوراخِ کت رسمی از ۶ به ۲ رسید.
     */
    protected const UNDERARM_DIP = 0.7;

    /** شعاع مرجع برای تبدیل اختلاف زاویه به فاصله (سانتی‌متر). */
    protected const REFERENCE_RADIUS = 15.0;

    /**
     * بیشترین ناهم‌طولیِ دو سرِ یک درز که با آزادیِ پارچه توضیح داده می‌شود.
     *
     * سرآستین را با ۵ تا ۱۰ درصد پُری می‌برند تا سر شانه بخوابد، و کمرِ چین‌دار
     * از این هم بیشتر. بالای این حد ولی توضیحِ دیگری دارد: شریکِ جامانده.
     */
    protected const EASE_SHARE = 0.18;

    /**
     * بسته کامل دوخت سه‌بعدی.
     *
     * @return array{scale: float, pieces: array, seams: array, budget: array, meta: array}
     */
    public function payload(Pattern $pattern): array
    {
        $body = new DrapeBody(Measurements::complete($pattern->measurements ?? []));
        $models = $pattern->relationLoaded('pieces') ? $pattern->pieces : $pattern->pieces()->get();

        $notes = [];
        $instances = [];
        $byCode = [];

        foreach ($models as $model) {
            // یک قطعه خراب نباید کل نمای سه‌بعدی را ببرد؛ گزارش می‌شود و بقیه
            // لباس ساخته می‌شود.
            try {
                $prepared = $this->prepare($model, $notes);

                if ($prepared === null) {
                    continue;
                }

                foreach ($this->instances($prepared, $body) as $instance) {
                    $instances[$instance['id']] = $instance;
                    $byCode[$instance['code']][] = $instance['id'];
                }
            } catch (Throwable $error) {
                $notes[] = "قطعه «{$model->code}» ساخته نشد: ".$error->getMessage();
            }
        }

        $this->arrange($instances);
        $this->arrangeSleeves($instances, $body);
        [$instances, $byCode] = $this->dedupe($instances, $notes);

        $relations = [];
        $unmatched = [];
        $seams = [];

        try {
            $relations = $this->relations($pattern);
            $seams = $this->seams($relations, $instances, $byCode, $unmatched);
        } catch (Throwable $error) {
            $notes[] = 'رابطه‌های دوخت خوانده نشد: '.$error->getMessage();
        }

        foreach ($instances as $instance) {
            foreach ($instance['dart_seams'] as $seam) {
                $seams[] = $seam;
            }
        }

        try {
            $seams = array_merge($seams, $this->closures($instances, $byCode));
        } catch (Throwable $error) {
            $notes[] = 'بستن مرکز جلو و پشت انجام نشد: '.$error->getMessage();
        }

        try {
            $seams = array_merge($seams, $this->adopt($instances, $seams));
        } catch (Throwable $error) {
            $notes[] = 'دوختن قطعه‌های جامانده انجام نشد: '.$error->getMessage();
        }

        try {
            $seams = $this->splice($instances, $seams);
        } catch (Throwable $error) {
            $notes[] = 'شریکِ جاماندهٔ درزها پیدا نشد: '.$error->getMessage();
        }

        return [
            'scale' => 0.01,
            'pieces' => array_values(array_map(fn (array $instance) => $instance['payload'], $instances)),
            'seams' => array_values($seams),
            'budget' => $this->budget($instances),
            'meta' => [
                'unmatched' => array_values($unmatched),
                'relations' => count($relations),
                'notes' => array_values($notes),
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  آماده‌سازی قطعه: باز کردن تا و نگه داشتن رد لبه‌های اصلی
     * ------------------------------------------------------------------- */

    /**
     * قطعه دیتابیسی را به قطعه آرایه‌ای «باز‌شده» تبدیل می‌کند.
     *
     * برای اینکه بعد از باز شدن تا بدانیم هر لبه تازه از کدام لبه اصلی آمده،
     * پیش از unfold برچسب هر لبه با یک نشانه یکتا («#۳») عوض می‌شود. خودِ unfold
     * برچسب‌ها را با مسیر جابه‌جا و قرینه می‌کند، پس نشانه‌ها همان نقشه‌ای را
     * می‌سازند که لازم داریم — بدون آنکه لازم باشد درون آن دست ببریم.
     *
     * @param  array<int, string>  $notes
     * @return array{model: PatternPiece, piece: array, origins: array<int, int|null>, tags: array<int, string>, unfolded: bool}|null
     */
    protected function prepare(PatternPiece $model, array &$notes): ?array
    {
        $outline = array_values($model->outline ?? []);
        $count = count($outline);

        if ($count < 3) {
            $notes[] = "قطعه «{$model->code}» مسیر بسته ندارد و در بسته نیامد.";

            return null;
        }

        $tags = Geometry::edgeTags(['outline' => $outline, 'meta' => $model->meta ?? []]);

        /*
         * کمربندِ دوتکه، یک‌تکه.
         *
         * کمربندِ شلوار دو نیمه است (هر یک نیم‌دور به‌اضافهٔ روی‌هم، دو بار
         * بریده) که در پهلو به هم می‌رسند. برای دوختنِ سه‌بعدی همان نوارِ کاملِ
         * دورِ کمر است — مثلِ مچ یا نوارِ یقه که یک‌تکه‌اند. پیش از این نیمهٔ
         * دوم به عنوانِ همتای هم‌جا حذف می‌شد و فقط نیم‌دورِ جلو نوار داشت؛ درزِ
         * کمرِ پشت به همان نیمه کشیده می‌شد (اندازه گرفته شد: جینِ کلوش).
         */
        $single = false;

        if (in_array($model->meta['part'] ?? null, ['waistband', 'elastic-band'], true)
            && (int) $model->cut_quantity === 2
            && ! $model->mirror
            && $count === 4
            && is_numeric($model->meta['band_girth'] ?? null)) {
            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);
            $width = (float) $model->meta['band_girth'] + (float) ($model->meta['band_overlap'] ?? 0);
            $outline = [
                Geometry::point($minX, $minY),
                Geometry::point($minX + $width, $minY),
                Geometry::point($minX + $width, $maxY),
                Geometry::point($minX, $maxY),
            ];
            $single = true;
        }

        $piece = [
            'code' => (string) $model->code,
            'name' => (string) $model->name,
            'outline' => $outline,
            'darts' => $model->darts ?? [],
            'notches' => $model->notches ?? [],
            'drills' => $model->drills ?? [],
            'pleats' => $model->pleats ?? [],
            'markers' => $model->markers ?? [],
            'meta' => $model->meta ?? [],
        ];

        $piece['meta']['edges'] = array_map(fn (int $index) => '#'.$index, range(0, $count - 1));

        $unfolded = false;

        if ($model->on_fold && ($piece['meta']['fold_edges'] ?? []) !== []) {
            $open = PieceOps::unfold($piece);

            if (count($open['outline'] ?? []) >= 3) {
                $piece = $open;
                $unfolded = true;
            } else {
                $notes[] = "قطعه «{$model->code}» روی تای پارچه است ولی باز نشد.";
            }
        } elseif ($model->on_fold) {
            $notes[] = "قطعه «{$model->code}» روی تای پارچه است ولی لبه تا ندارد؛ نیمه بریده‌شده به مرورگر رفت.";
        }

        $piece = $this->standUp($piece, $tags, $model);
        $origins = $this->origins($piece);
        $oriented = DrapeGeometry::orient($piece, $origins);

        return [
            'model' => $model,
            'piece' => Geometry::normalizePiece($oriented['piece']),
            'origins' => $oriented['per_edge'],
            'tags' => $tags,
            'unfolded' => $unfolded,
            'single' => $single,
        ];
    }

    /**
     * یقهٔ ایستاده روی تن سروته است.
     *
     * روی الگو، خط یقهٔ یقه بالای قطعه است و لبهٔ بیرونی پایینش — همان‌طور که
     * روی کاغذ کشیده می‌شود. روی تن ولی برعکس است: خط یقه پایین می‌نشیند (روی
     * خط یقهٔ لباس) و یقه از آن‌جا بالا می‌رود. تا وقتی همان ترتیبِ کاغذ را روی
     * بدن می‌گذاشتیم، لبهٔ بیرونیِ یقه ۷٫۵ سانتی‌متر *زیرِ* خط یقه چیده می‌شد و
     * قید درز باید نوار را از میان سوراخِ گردن بکشد بالا؛ نوار در همان کشیدن
     * سروته می‌شد. اندازه گرفتیم: پس از چیدن ۱۰۰٪ مثلث‌های یقه رو به بیرون بود،
     * پس از دوختن ۶٪. رویِ برگشتهٔ پارچه تیره سایه می‌زند و در عکس مثل شکافِ
     * دور گردن دیده می‌شد.
     *
     * شرطش دقیق است و به مدل گره نخورده: تنها یقه‌ای که خط یقه‌اش در نیمهٔ بالای
     * کادر خودش است برمی‌گردد. یقهٔ تختِ خوابیده (پیتر‌پن) خط یقه‌اش پایین است و
     * دست‌نخورده می‌ماند.
     *
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    protected function standUp(array $piece, array $tags, PatternPiece $model): array
    {
        if ($this->role($model) !== 'collar') {
            return $piece;
        }

        $outline = $piece['outline'] ?? [];
        [, $minY, , $maxY] = Geometry::bounds($outline);
        $height = $maxY - $minY;

        if ($height < 0.5) {
            return $piece;
        }

        $sum = 0.0;
        $seen = 0;

        foreach ($tags as $edge => $tag) {
            if ($tag !== 'neck' || ! isset($outline[$edge])) {
                continue;
            }

            $sum += (float) ($outline[$edge]['y'] ?? 0) + (float) ($outline[($edge + 1) % count($outline)]['y'] ?? 0);
            $seen += 2;
        }

        if ($seen === 0 || ($sum / $seen) > $minY + ($height / 2)) {
            return $piece; // خط یقه پایین است؛ یقه همان‌جا که هست درست است
        }

        $flip = $minY + $maxY;

        foreach (['outline', 'darts', 'notches', 'markers', 'drills'] as $key) {
            if (! isset($piece[$key]) || ! is_array($piece[$key])) {
                continue;
            }

            $piece[$key] = $this->mirrorY($piece[$key], $flip);
        }

        return $piece;
    }

    /**
     * قرینه کردن y هر نقطه‌ای که در یک ساختار تودرتو هست.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function mirrorY(array $data, float $flip): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mirrorY($value, $flip);

                continue;
            }

            if (($key === 'y' || $key === 'cy' || $key === 'y1' || $key === 'y2') && is_numeric($value)) {
                $data[$key] = $flip - (float) $value;
            }
        }

        return $data;
    }

    /**
     * شماره لبه اصلی هر لبه فعلی، از روی نشانه‌هایی که در برچسب لبه‌ها گذاشتیم.
     *
     * @return array<int, int|null>
     */
    protected function origins(array $piece): array
    {
        $origins = [];

        foreach (Geometry::edgeTags($piece) as $index => $tag) {
            $origins[$index] = preg_match('/^#(\d+)$/', (string) $tag, $found) === 1
                ? (int) $found[1]
                : null;
        }

        return $origins;
    }

    /* ---------------------------------------------------------------------
     |  نمونه‌ها
     * ------------------------------------------------------------------- */

    /**
     * هر برش یک نمونه: نمونه‌های آینه‌ای همین‌جا آینه می‌شوند.
     *
     * @param  array{model: PatternPiece, piece: array, origins: array, tags: array, unfolded: bool}  $prepared
     * @return array<int, array<string, mixed>>
     */
    protected function instances(array $prepared, DrapeBody $body): array
    {
        $model = $prepared['model'];
        // نوارِ یک‌تکه‌شده (ببینید prepare) یک نمونه دارد
        $quantity = ($prepared['single'] ?? false) ? 1 : max(1, (int) $model->cut_quantity);
        $mirror = (bool) $model->mirror;
        $out = [];

        for ($index = 0; $index < $quantity; $index++) {
            $piece = $prepared['piece'];
            $origins = $prepared['origins'];
            $mirrored = $mirror && ($index % 2 === 1);

            if ($mirrored) {
                $flipped = DrapeGeometry::mirrorPiece($piece, $origins);
                $piece = Geometry::normalizePiece($flipped['piece']);
                $origins = $flipped['per_edge'];
            }

            $out[] = $this->instance($prepared, $piece, $origins, $index, $mirrored, $quantity, $body);
        }

        return $out;
    }

    /**
     * یک نمونه کامل: خط شکسته، پل لبه‌ها، ساسون‌ها و چیدن اولیه.
     *
     * @return array<string, mixed>
     */
    protected function instance(
        array $prepared,
        array $piece,
        array $origins,
        int $index,
        bool $mirrored,
        int $quantity,
        DrapeBody $body,
    ): array {
        $model = $prepared['model'];
        /*
         * فقط لبه‌های دوختنیِ راست رأسِ میانی می‌گیرند.
         *
         * درز روی رأس بریده می‌شود، پس لبهٔ راستِ بی‌رأسِ میانی هرگز میان دو
         * شریک تقسیم نمی‌شود — خط یقهٔ یقهٔ پیراهن همین بود و همهٔ ۲۵٫۸
         * سانتی‌مترش روی خط یقهٔ ۱۴٫۴ سانتی‌متریِ یک تنه می‌رفت.
         *
         * ولی شکستنِ همهٔ لبه‌های راست را هم اندازه گرفتیم و بدتر بود: روی هشت
         * لباسِ سنجه شمار مثلث خراب از ۲۵ به ۵۱ رفت، چون مرزِ لبهٔ بی‌درز هم
         * ریزتر می‌شد و مثلث‌بندی و جفت‌کردنِ رأس‌ها را به‌هم می‌زد. پس همان‌جا
         * که لازم است: لبه‌ای که دوخته می‌شود.
         */
        $breakable = [];

        foreach ($origins as $edge => $origin) {
            $tag = $origin !== null ? ($prepared['tags'][$origin] ?? 'default') : 'default';
            $breakable[$edge] = in_array($tag, static::SPLIT_TAGS, true)
                ? (static::SPLIT_STEPS[$tag] ?? true)
                : false;
        }

        $flat = DrapeGeometry::flattenWithSpans(
            $piece['outline'],
            split: $breakable,
        );
        $polygon = $flat['polygon'];
        $spans = $flat['spans'];

        $lengths = [];
        $edges = [];

        foreach ($spans as $edge => [$start, $end]) {
            $length = DrapeGeometry::arcLength($polygon, $start, $end);
            $lengths[$edge] = $length;
            $tag = $origins[$edge] !== null ? ($prepared['tags'][$origins[$edge]] ?? 'default') : 'default';

            $edges[$edge] = [
                'tag' => $tag,
                'start' => $start,
                'end' => $end,
                'length' => round($length, 3),
            ];
        }

        $code = (string) $model->code;
        $id = $code.'#'.$index;
        $role = $this->role($model);
        $side = $this->side($model, $mirrored, $quantity, $index);

        $instance = [
            'id' => $id,
            'code' => $code,
            'role' => $role,
            'side' => $side,
            'instance' => $index,
            'mirrored' => $mirrored,
            'quantity' => $quantity,
            'outline' => $piece['outline'],
            'bounds' => Geometry::bounds($piece['outline']),
            'polygon' => $polygon,
            'spans' => $spans,
            'lengths' => $lengths,
            'edges' => $edges,
            'origins' => $origins,
            'unfolded' => $prepared['unfolded'],
            'meta' => $piece['meta'] ?? [],
        ];

        $placement = $this->placement($instance, $model, $body);
        $darts = $this->darts($piece, $instance, $id);

        $instance['placement'] = $placement;
        $instance['top_cm'] = $placement['y_top'] * $body->height;
        $instance['dart_seams'] = $darts['seams'];
        $instance['payload'] = [
            'id' => $id,
            'code' => $code,
            'name' => (string) $model->name,
            'role' => $role,
            'side' => $side,
            'instance' => $index,
            'mirrored' => $mirrored,
            'layer' => (string) ($model->layer ?: 'outer'),
            'polygon' => array_map(fn (array $point) => [round($point['x'], 3), round($point['y'], 3)], $polygon),
            'edges' => array_values($edges),
            'roll' => $this->rollLine($piece, $polygon, $edges),
            /*
             * «این قطعه دورِ بدن می‌پیچد» — کمربند، نوارِ یقه، مچ‌بند.
             *
             * نماگر نوارِ نگه‌دارنده را روی «تنه، یقه، دامن، آستین» می‌گذارد و
             * کمربند در هیچ‌کدام نیست: ناحیه‌اش «جزئیات» است. پس هیچ رأسی از
             * کمربندِ دامنِ کلوش تکیه نمی‌گرفت (۰ از ۱۰۲) و نُه سانتی‌متر پایین
             * می‌افتاد و دامن را با خودش می‌برد — همان ناپایداری که پوششِ آن دامن
             * را با دوازده رأس این‌ور و آن‌ور می‌کرد. کمرِ دامن روی خودِ کمر
             * می‌ایستد، مثل خطِ کمرِ خودِ دامن.
             */
            'wraps' => $this->wrapsAround($role, $model),
            'darts' => $darts['darts'],
            'placement' => array_filter(
                array_intersect_key($placement, array_flip([
                    'zone', 'u0', 'u1', 'y_top', 'y_end', 'radius_hint', 'radius', 'flip', 'laps',
                ])),
                fn ($value) => $value !== null,
            ),
        ];

        return $instance;
    }

    /**
     * نقش قطعه.
     *
     * نخست meta.part خوانده می‌شود چون همان چیزی است که ژنراتور خودش اعلام کرده؛
     * تنها اگر part چیزی نگوید (مثلاً «lining») سراغ کد و نام قطعه می‌رویم. اگر
     * جای این دو عوض شود، آسترِ دامن «تنه» می‌شود و مچ‌بند «آستین».
     */
    protected function role(PatternPiece $piece): string
    {
        return $this->matchRole((string) ($piece->meta['part'] ?? ''))
            ?? $this->matchRole($piece->code.' '.$piece->name)
            ?? 'torso';
    }

    /** نقشی که یک رشته به آن اشاره می‌کند. */
    protected function matchRole(string $haystack): ?string
    {
        $haystack = mb_strtolower(trim($haystack));

        if ($haystack === '') {
            return null;
        }

        foreach ([
            // مچ‌بند دور مچِ دست می‌پیچد، نه دور بدن. اگر «جزئیات» شمرده شود،
            // روی محور بدن و در ارتفاع مچ می‌نشیند — یعنی یک نوار پارچه دور
            // زانو، همان چیزی که در نمای سه‌بعدی جدا از لباس دیده می‌شد.
            'sleeve' => ['sleeve', 'cuff', 'آستین', 'مچ'],
            /*
             * برگردان، یقه نیست.
             *
             * «lapel» در فهرست یقه بود و نقشش «یقه» می‌شد؛ آن‌وقت مثل نوارِ یقه
             * دورِ گردن پیچانده می‌شد و — چون یقه وسطِ پشت چیده می‌شود — هر دو
             * برگردانِ کت روی هم، پشتِ گردن می‌نشستند و از آن‌جا به حلقهٔ تنهٔ پشت
             * دوخته می‌شدند. در عکس همان فلپِ ایستادهٔ روی سرشانه بود، و ۱۵۵
             * پیکسل پوستِ کنارِ گردن را باز نگه می‌داشت.
             *
             * برگردان روی سینه می‌خوابد: نه دورِ چیزی می‌پیچد، نه به خط یقه
             * دوخته می‌شود. جایش کنارِ سجاف است، در «جزئیات».
             */
            'collar' => ['collar', 'hood', 'یقه', 'کلاه'],
            'skirt' => ['skirt', 'peplum', 'godet', 'دامن'],
            'leg' => ['leg', 'pant', 'trouser', 'panty', 'short', 'شلوار', 'پاچه'],
            'detail' => [
                'pocket', 'facing', 'waistband', 'belt', 'placket', 'binding', 'band',
                'strap', 'tie', 'loop', 'trim', 'patch', 'gusset', 'veil',
                /*
                 * «lapel» عمداً این‌جا *نیست*.
                 *
                 * برگردان روی سینه می‌خوابد، نه دورِ گردن، پس منطقی بود که
                 * «جزئیات» باشد و یک بار همین کار را کردیم. ولی با ترتیبِ تازه
                 * (اول دوخت، بعد تن کردن) قطعهٔ «جزئیات» تکیه‌گاهی نمی‌گیرد و
                 * سرِ جایش نمی‌ماند: سوراخِ کت از ۰٫۲٪ به ۱۱٫۷٪ رفت. با نقشِ
                 * «یقه» برگردان همان‌جا می‌ماند که باید.
                 */
                'جیب', 'سجاف', 'کمربند', 'نوار', 'بند', 'برگردان',
            ],
        ] as $role => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $role;
                }
            }
        }

        return null;
    }

    /** سمت قطعه: جلو/پشت از خود الگو می‌آید و چپ/راست از جفت آینه‌ای. */
    protected function side(PatternPiece $piece, bool $mirrored, int $quantity, int $index = 0): ?string
    {
        $side = $piece->meta['side'] ?? null;

        if (in_array($side, ['front', 'back'], true)) {
            return $side;
        }

        // یوک همیشه روی تنه پشت می‌نشیند و ژنراتورها side آن را نمی‌نویسند؛ اگر
        // اینجا جبران نشود، یوک روی سینه چیده می‌شود و درز سرشانه‌اش از میان تن
        // رد می‌شود.
        if (($piece->meta['part'] ?? null) === 'yoke') {
            return 'back';
        }

        if ($quantity > 1 && $piece->mirror) {
            return $mirrored ? 'right' : 'left';
        }

        // قطعه‌ی اندام قرینه است و ژنراتور آینه‌اش نمی‌کند، ولی دو تا که بریده
        // شد یکی روی دست (یا پای) چپ می‌رود و یکی روی راست. ملاک شماره‌ی نمونه
        // است نه آینه بودن؛ وگرنه هر دو مچ‌بند روی یک دست می‌نشینند.
        if ($quantity > 1 && in_array($piece->meta['part'] ?? null, ['cuff', 'sleeve'], true)) {
            return $index % 2 === 1 ? 'right' : 'left';
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  ساسون
     * ------------------------------------------------------------------- */

    /**
     * ساسون‌های یک نمونه.
     *
     * ساسونی که روی مسیر بریده شده باشد دو ساقش دو کمان روی همان خط شکسته است و
     * به‌صورت درز از نوع «dart» هم می‌آید؛ ساسونی که فقط سه نقطه دارد در darts
     * می‌ماند تا مرورگر خودش دهانه‌اش را ببندد.
     *
     * @return array{darts: array<int, array<string, mixed>>, seams: array<int, array<string, mixed>>}
     */
    protected function darts(array $piece, array $instance, string $id): array
    {
        $darts = [];
        $seams = [];
        $polygon = $instance['polygon'];

        foreach ($piece['darts'] ?? [] as $dart) {
            $legs = array_values($dart['legs'] ?? []);

            if (count($legs) !== 2 || ! isset($dart['apex']['x'], $dart['apex']['y'])) {
                continue;
            }

            $first = ['x' => (float) $legs[0]['x'], 'y' => (float) $legs[0]['y']];
            $second = ['x' => (float) $legs[1]['x'], 'y' => (float) $legs[1]['y']];
            $apex = ['x' => (float) $dart['apex']['x'], 'y' => (float) $dart['apex']['y']];
            $mouth = Geometry::lerp($first, $second, 0.5);

            $startAt = $this->vertexAt($polygon, $first);
            $endAt = $this->vertexAt($polygon, $second);

            // دهانه ساسون باید در جهت مسیر خوانده شود، وگرنه کمانِ «ساق» از سمت
            // اشتباه دور قطعه می‌چرخد و به‌جای یک وجب، تقریباً کل محیط را می‌گیرد.
            if ($startAt !== null && $endAt !== null) {
                $apexAt = $this->vertexAt($polygon, $apex);

                $wrongWay = $apexAt !== null
                    ? ! $this->between($polygon, $startAt, $apexAt, $endAt)
                    : DrapeGeometry::arcLength($polygon, $startAt, $endAt)
                        > DrapeGeometry::arcLength($polygon, $endAt, $startAt);

                if ($wrongWay) {
                    [$startAt, $endAt] = [$endAt, $startAt];
                }
            }

            $record = [
                'legs' => [
                    [round($first['x'], 3), round($first['y'], 3)],
                    [round($second['x'], 3), round($second['y'], 3)],
                ],
                'apex' => [round($apex['x'], 3), round($apex['y'], 3)],
                'intake' => round((float) ($dart['intake'] ?? Geometry::distance($first, $second)), 3),
                'on_edge' => Geometry::nearestEdge($instance['outline'], $mouth)['edge'],
                'start' => $startAt,
                'end' => $endAt,
                'label' => (string) ($dart['label'] ?? 'ساسون'),
            ];

            $darts[] = $record;

            $apexAt = $this->vertexAt($polygon, $apex);

            if ($startAt === null || $endAt === null || $apexAt === null || $apexAt === $startAt || $apexAt === $endAt) {
                continue;
            }

            if (! $this->between($polygon, $startAt, $apexAt, $endAt)) {
                continue;
            }

            $legOne = DrapeGeometry::arcLength($polygon, $startAt, $apexAt);
            $legTwo = DrapeGeometry::arcLength($polygon, $apexAt, $endAt);

            $seams[] = [
                'a' => ['piece' => $id, 'from' => $startAt, 'to' => $apexAt, 'length' => round($legOne, 3)],
                'b' => ['piece' => $id, 'from' => $apexAt, 'to' => $endAt, 'length' => round($legTwo, 3)],
                'label' => (string) ($dart['label'] ?? 'ساسون'),
                'reverse' => true,
                'ease' => round($legTwo - $legOne, 3),
                'kind' => 'dart',
                'relation' => null,
            ];
        }

        return ['darts' => $darts, 'seams' => $seams];
    }

    /** شماره رأس خط شکسته که این نقطه روی آن نشسته است (اگر نشسته باشد). */
    protected function vertexAt(array $polygon, array $point): ?int
    {
        $best = null;
        $bestDistance = static::SNAP;

        foreach ($polygon as $index => $vertex) {
            $distance = Geometry::distance($vertex, $point);

            if ($distance <= $bestDistance) {
                $bestDistance = $distance;
                $best = $index;
            }
        }

        return $best;
    }

    /** آیا رأس $middle در جهت مسیر، میان $from و $to است؟ */
    protected function between(array $polygon, int $from, int $middle, int $to): bool
    {
        $count = max(1, count($polygon));
        $step = fn (int $a, int $b) => ((($b - $a) % $count) + $count) % $count;

        return $step($from, $middle) < $step($from, $to);
    }

    /* ---------------------------------------------------------------------
     |  چیدن اولیه دور بدن
     * ------------------------------------------------------------------- */

    /**
     * جای اولیه قطعه روی بدن.
     *
     * این جدول ثابتِ نام قطعه‌ها نیست؛ از برچسب لبه بالا و پایین، پهنای خود قطعه
     * و سمت آن درمی‌آید. دقیق بودنش لازم نیست — فقط باید گره نخورد: جلو و پشت
     * روی هم نیفتند و آستین از داخل تنه شروع نشود.
     *
     * @return array{zone: string, u0: float, u1: float, y_top: float, radius_hint: string, flip: bool}
     */
    protected function placement(array $instance, PatternPiece $model, DrapeBody $body): array
    {
        $role = $instance['role'];
        $side = $instance['side'];
        [$minX, $minY, $maxX, $maxY] = $instance['bounds'];
        $width = max(0.5, $maxX - $minX);
        $height = max(0.5, $maxY - $minY);

        $anchors = $this->edgeAnchors($instance);
        $top = $this->levelOf($anchors['top'], $body);
        $bottom = $this->levelOf($anchors['bottom'], $body);
        $part = $this->partLevel($model->meta['part'] ?? null, $body);

        $yTop = match (true) {
            $role === 'collar' => $this->collarLevel($instance, $body),
            // مچ‌بند روی همان محورِ دست است ولی سرِ دیگرش: پای آستین، نه حلقه
            $role === 'sleeve' && $part !== null => $part,
            // ببینید SLEEVE_LIFT
            $role === 'sleeve' => $body->level('armhole')
                + (static::SLEEVE_LIFT * ($body->level('shoulder') - $body->level('armhole'))),
            $top !== null => $top,
            $part !== null => $part,
            $bottom !== null => min(0.95, $bottom + ($height / $body->height)),
            default => match ($role) {
                'skirt', 'leg' => $body->level('waist'),
                default => $body->level('shoulder'),
            },
        };

        $middle = max(0.02, $yTop - (($height / $body->height) / 2));
        $hint = $this->radiusHint($role, $middle, $body, $instance);
        $radius = max(2.0, $body->radii[$hint] ?? $body->radii['bust']);

        /*
         * پهنای کادر برای قطعه‌ی خمیده کم است.
         *
         * نوار یقه مثل موز خمیده است: کادرش ۳۵ سانتی‌متر ولی خودِ لبه‌ی گردنش
         * ۵۴. پیچاندن با پهنای کادر یعنی همان لبه‌ی ۵۴ سانتی‌متری روی ۳۵
         * سانتی‌متر جمع شود. برای قطعه‌ای که دور چیزی می‌پیچد، طولِ بلندترین
         * لبه‌اش ملاک است، نه کادرش.
         */
        $wrap = $this->wrapsAround($role, $model) ? max($width, $this->wrapLength($instance)) : $width;
        $span = $wrap / $radius;

        /*
         * قطعه‌ای که از دور بدن بلندتر است، روی دایره‌ی خودش می‌نشیند.
         *
         * نوار یقه‌ی پیراهن ۵۴ سانتی‌متر است و دور گردن ۳۷؛ اگر همان‌جا دور
         * گردن پیچانده شود، یک‌سوم طولش فشرده می‌شود و روی خودش می‌افتد —
         * ناحیه‌ی گردن و سرشانه به‌هم می‌ریزد و درزها هیچ‌وقت جا نمی‌افتند.
         * پس شعاع از خودِ قطعه گرفته می‌شود و درزها آن را روی بدن می‌کشند،
         * نه برعکس. همین برای دامن کلوش و کمربند بلند هم درست است.
         */
        $ownRadius = null;

        if ($span > 2 * M_PI) {
            $ownRadius = round($wrap / (2 * M_PI), 2);
            $span = 2 * M_PI;
        }
        $center = $side === 'back' ? M_PI : 0.0;
        $symmetric = $instance['unfolded'] || ! $model->mirror;
        $lap = $symmetric ? null : $this->overlapArc($instance, $model, $center);
        $run = $this->slantRun($instance, $model, $body);

        if ($run !== null) {
            [$u0, $u1] = [$run['u0'], $run['u1']];
            $yTop = $run['y_top'];
            $ownRadius = null;
        } elseif ($lap !== null) {
            [$u0, $u1] = $lap;
        } elseif ($role === 'sleeve') {
            $span = min($span, 2 * M_PI);
            $u0 = -$span / 2;
            $u1 = $span / 2;
        } elseif ($role === 'collar') {
            $span = min($span, 2 * M_PI);
            $u0 = M_PI - ($span / 2);
            $u1 = M_PI + ($span / 2);
        } elseif ($role === 'leg') {
            // دو نمونهٔ پاچه، دو پای جداگانه‌اند نه دو نیمهٔ یک لوله؛ پس هر کدام
            // روی استوانهٔ خودش وسط‌چین می‌شود.
            $span = min($span, M_PI);
            $u0 = $center - ($span / 2);
            $u1 = $center + ($span / 2);
        } elseif ($this->wrapsAround($role, $model)) {
            /*
             * نواری که دورِ بدن می‌پیچد، تا دورِ کامل جا دارد.
             *
             * کمربند و نوارِ کمر و مچ‌بند در شاخهٔ «قطعهٔ قرینه» می‌افتادند و
             * همان‌جا به نیمهٔ بدن (π) بریده می‌شدند — همان اشتباهی که برای یقه
             * جدا رفع شده بود. کمربندِ دامنِ کلوش ۳٫۶ رادیان جا می‌خواهد و ۳٫۱۴
             * می‌گرفت، یعنی پانزده درصد فشرده می‌شد.
             *
             * (این به‌تنهایی دامنِ کلوش را درست نکرد: مشکلِ بزرگ‌ترش این است که
             * قطعهٔ کلوش روی استوانه پیچیده می‌شود نه روی مخروط، و دو سرِ درزِ
             * پهلو در لبهٔ دامن ۳۶ سانتی‌متر از هم می‌افتند.)
             */
            $span = min($span, 2 * M_PI);
            $u0 = $center - ($span / 2);
            $u1 = $center + ($span / 2);
        } elseif ($symmetric) {
            $u0 = $center - (min($span, M_PI) / 2);
            $u1 = $center + (min($span, M_PI) / 2);
        } else {
            $half = min($span, M_PI / 2);

            if ($this->centerAtLeft($instance)) {
                $u0 = $center;
                $u1 = $center + $half;
            } else {
                $u0 = $center - $half;
                $u1 = $center;
            }
        }

        return [
            'zone' => $this->zone($role, $side),
            'u0' => round($u0, 4),
            'u1' => round($u1, 4),
            'y_top' => round($yTop, 4),
            'radius_hint' => $hint,
            'radius' => $ownRadius,
            'flip' => $instance['mirrored'],
            // برای چیدن گروهی (فقط سمت سرور؛ در بسته نمی‌آید)
            'center' => $center,
            'span' => $span,
            'wrap' => $wrap,
            'radius_body' => $radius,
            'symmetric' => $symmetric,
            'laps' => $lap !== null,
            'y_end' => $run === null ? null : round($run['y_end'], 4),
            'to_right' => $symmetric ? true : $this->centerAtLeft($instance),
            'central' => $symmetric || $this->edgeTagsOf($instance, 'side') === [],
        ];
    }

    /**
     * ارتفاعِ لبهٔ *بالای* یقه، وقتی خطِ یقه‌اش روی گودی گردن بنشیند.
     *
     * خط یقهٔ خودِ یقه — لبه‌ای که به گردنِ لباس دوخته می‌شود — کفِ نوار است نه
     * سرش. اندازه گرفته شد: در پیراهن ۷٫۰، در کت رسمی ۷٫۲ و در ترنچ‌کت ۸٫۶
     * سانتی‌متر پایین‌تر از لبهٔ بالای قطعه. با y_top = ترازِ گردن، همان لبهٔ
     * دوخت هفت سانتی‌متر *زیرِ* گودی گردن می‌افتاد و ستونِ گردن تا زیرِ چانه لخت
     * می‌ماند — همان لکهٔ قرمزِ زیرِ چانه که در عکسِ هر سه مدل دیده می‌شد
     * (ترنچ‌کت ۴۰۷ پیکسل، پیراهن ۱۷۴، کت رسمی ۸۷).
     *
     * پس یقه از خطِ یقهٔ خودش آویزان می‌شود: لبهٔ دوخت روی گودیِ گردن و بقیه‌اش
     * بالاتر. سقفش چانه است، وگرنه کلاهِ سویشرت — که آن هم «یقه» است و لبهٔ
     * دوختش سی سانتی‌متر پایین‌تر — بالای سر پرواز می‌کرد.
     */
    protected function collarLevel(array $instance, DrapeBody $body): float
    {
        [, $minY] = $instance['bounds'];
        $rise = 0.0;
        $weight = 0.0;

        foreach ($instance['edges'] as $data) {
            if ($data['tag'] !== 'neck' || $data['length'] < 0.5) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $data['start'], $data['end']);
            $rise += ($middle['y'] - $minY) * $data['length'];
            $weight += $data['length'];
        }

        if ($weight <= 0.0) {
            return $body->level('neck');
        }

        /*
         * سقفش چانه است، و نه یک سانتی‌متر بالاتر.
         *
         * دو بار بالاتر امتحان شد و هر دو بار بدتر بود: با چانه + سه‌صدمِ قد،
         * پوستِ دیده‌شدهٔ ترنچ‌کت از ۶۵ پیکسل به ۵۱۳ رفت و پیراهن از ۶۲ به ۱۲۷.
         * با چانه + دو‌صدم — که سنجهٔ سایهٔ آفلاین بهترش می‌دانست — پیراهن از
         * صفر به ۱۷۳ برگشت و ترنچ‌کت از ۱۹۲ به ۲۱۷. یقه باید روی گردن بایستد،
         * و بالاتر از چانه دیگر تکیه‌گاهی ندارد: از بالا به بیرون تا می‌خورد و
         * کفش از گردن جدا می‌شود.
         */
        return min(
            $body->level('chin'),
            $body->level('neck') + (max(0.0, $rise / $weight) / $body->height),
        );
    }

    /** شماره لبه‌هایی از یک نمونه که این برچسب را دارند. */
    protected function edgeTagsOf(array $instance, string $tag): array
    {
        $found = [];

        foreach ($instance['edges'] as $edge => $data) {
            if ($data['tag'] === $tag) {
                $found[] = $edge;
            }
        }

        return $found;
    }

    /**
     * چیدن قطعه‌های یک ناحیه کنار هم، نه روی هم.
     *
     * قطعه‌ای که روی خط مرکز می‌نشیند (باز‌شده از تا، یا بی‌درزِ پهلو) وسط ناحیه
     * می‌ماند و پنل پهلو بیرون از آن می‌نشیند؛ اگر جا کم بیاید هر دو با هم فشرده
     * می‌شوند تا از نیم‌دور بدن بیرون نزنند. بدون این کار، تنهٔ مرکزی و پنل
     * پرنسسی هر دو روی مرکز جلو می‌افتند و از همان قدم اول در هم فرو می‌روند.
     *
     * قطعه‌های هم‌ترازِ روی هم (تنه پشت و یوک) هر دو مرکزی‌اند و کنار هم چیده
     * نمی‌شوند؛ چون اختلافشان در ارتفاع است نه در زاویه.
     *
     * @param  array<string, array<string, mixed>>  $instances
     */
    protected function arrange(array &$instances): void
    {
        $groups = [];

        foreach ($instances as $id => $instance) {
            $zone = $instance['placement']['zone'];

            if (! in_array($zone, ['torso_front', 'torso_back', 'skirt_front', 'skirt_back'], true)) {
                continue;
            }

            $groups[$zone.'|'.$instance['payload']['layer']][] = $id;
        }

        foreach ($groups as $ids) {
            $centralWidest = 0.0;
            $outerWidest = 0.0;

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];

                // قطعه‌ای که هم‌پوشانی اعلام کرده *باید* روی قطعهٔ دیگر بیفتد؛
                // این‌جا کارِ ما جدا کردنِ قطعه‌هاست، پس کنارش می‌گذاریم
                if ($place['laps']) {
                    continue;
                }

                $half = $place['symmetric'] ? $place['span'] / 2 : $place['span'];

                if ($place['central']) {
                    $centralWidest = max($centralWidest, $half);
                } else {
                    $outerWidest = max($outerWidest, $half);
                }
            }

            $central = min($centralWidest, M_PI / 2);
            $outer = min($outerWidest, (M_PI / 2) - $central);

            // اگر قطعه مرکزی همه نیم‌دور را برداشته باشد، پنل پهلو جایی نمی‌ماند؛
            // یک‌چهارم دور را به آن برمی‌گردانیم.
            if ($outerWidest > 0 && $outer < 0.2) {
                $outer = min($outerWidest, M_PI / 4);
                $central = (M_PI / 2) - $outer;
            }

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];

                if ($place['laps']) {
                    continue;
                }

                $room = $place['central'] ? $central : $outer;
                $start = $place['central'] ? 0.0 : $central;
                $half = min($place['symmetric'] ? $place['span'] / 2 : $place['span'], $room);
                $middle = $place['center'];

                if ($place['symmetric']) {
                    $u0 = $middle - $start - $half;
                    $u1 = $middle + $start + $half;
                } elseif ($place['to_right']) {
                    $u0 = $middle + $start;
                    $u1 = $middle + $start + $half;
                } else {
                    $u0 = $middle - $start - $half;
                    $u1 = $middle - $start;
                }

                $instances[$id]['placement']['u0'] = round($u0, 4);
                $instances[$id]['placement']['u1'] = round($u1, 4);
                $instances[$id]['payload']['placement']['u0'] = round($u0, 4);
                $instances[$id]['payload']['placement']['u1'] = round($u1, 4);
            }
        }
    }

    /**
     * پنل‌های یک آستین، کنار هم دور بازو — نه روی هم.
     *
     * آستین دوتکه (کت، کت‌وشلوار) دو پنل دارد: بالا و زیر. هر دو «آستین»اند، پس
     * هر دو وسط‌چین می‌شدند و u = -π..π می‌گرفتند — یعنی هر دو تمامِ دور بازو را
     * ادعا می‌کردند و از قدم اول در هم فرو می‌رفتند. اندازه گرفته شد: پوششِ آستین
     * روی کت ۳۰ درجه از ۳۶۰ و روی کت‌وشلوار ۴۵ — بازو عملاً لخت.
     *
     * درستش همان کاری است که arrange() برای تنه می‌کند: دور بازو میان پنل‌ها
     * تقسیم شود، به نسبتِ پهنای خودشان. و شعاع هم مشترک است: پنل‌های یک آستین
     * روی یک استوانه‌اند، پس شعاعش از *مجموع* پهنایشان می‌آید نه از پهنای هرکدام
     * جداگانه (وگرنه پنل بالا روی استوانهٔ ۴ سانتی‌متری می‌رفت و پنل زیر روی
     * ۴٫۵ سانتی‌متری، دو لولهٔ تودرتو).
     *
     * @param  array<string, array<string, mixed>>  $instances
     */
    protected function arrangeSleeves(array &$instances, DrapeBody $body): void
    {
        $groups = [];

        foreach ($instances as $id => $instance) {
            $place = $instance['placement'];

            if (($instance['payload']['role'] ?? '') !== 'sleeve') {
                continue;
            }

            // مچ‌بند دور مچ می‌پیچد و پنلِ آستین نیست؛ سهمش تمامِ دور است
            if (($instance['payload']['meta']['part'] ?? '') === 'cuff') {
                continue;
            }

            $key = implode('|', [
                $place['zone'],
                $instance['payload']['layer'],
                (string) ($instance['payload']['side'] ?? ''),
                (string) $place['y_top'],
            ]);

            $groups[$key][] = $id;
        }

        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue; // آستین یک‌تکه؛ خودش تمامِ دور را می‌گیرد
            }

            $this->levelUnderarms($instances, $ids, $body);

            $total = 0.0;

            foreach ($ids as $id) {
                $total += max(0.5, (float) $instances[$id]['placement']['wrap']);
            }

            $radius = 0.0;

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];
                $radius = max($radius, (float) ($place['radius'] ?? 0), (float) $place['radius_body']);
            }

            $radius = round($radius, 2);

            /*
             * دو راهِ امتحان‌شده که بدتر بودند، تا کسی دوباره نرود:
             *
             * ۱) استوانه به اندازهٔ *مجموعِ* پهنای پنل‌ها (آستینِ ترنچ‌کت ۴۴٫۵
             *    سانتی‌متر دور دارد و بازو ۲۹٫۶). پوششِ ترنچ‌کت از ۲۲۵ به ۲۷۰
             *    درجه رفت ولی کت رسمی از ۳۶۰ به ۱۹۵ افتاد و بازوی لختش از ۱۰
             *    به ۳۷ از ۳۱۲: روی استوانهٔ بزرگ‌تر پنل‌ها دقیقاً کنار هم
             *    می‌نشینند و هیچ هم‌پوشانی‌ای نمی‌ماند که خطای درز را ببلعد.
             *    میانگینِ هندسیِ دو شعاع هم امتحان شد؛ باز ۱۵۰ درجه.
             *
             * ۲) هم‌تراز کردنِ پنل‌ها از *مچ* به‌جای سرشانه (پنلِ زیر کوتاه‌تر
             *    است و مچش بالاتر می‌افتد). پوشش روی کت رسمی از ۳۶۰ به ۱۳۵ و
             *    روی ترنچ‌کت از ۲۲۵ به ۱۵۰ افتاد: پنلِ پایین‌تر دورِ باریک‌ترین
             *    جای بازو پیچیده می‌شود و باریک‌تر درمی‌آید.
             */

            /*
             * پنلِ پهن وسط‌چین می‌ماند، بقیه از کنارش پُر می‌کنند.
             *
             * سرِ آستین — همان کمانِ خمیده‌ای که به حلقه دوخته می‌شود — بیشترش
             * روی پنلِ بالاست. اگر چیدن از u = -π شروع شود، آن سر جای دلخواهی
             * می‌افتد و آستین از درزی کج آویزان می‌ماند: اندازه گرفته شد که
             * آستین کت‌وشلوار ۸٫۱ سانتی‌متر روی بازو سُر می‌خورد و پوستِ لخت از
             * ۲ به ۴۰ می‌رفت. آستین یک‌تکه وسط‌چین است و کار می‌کند؛ پس پنلِ
             * پهن هم همان‌جا می‌ماند.
             */
            $order = $ids;
            $at = -M_PI;

            foreach ($order as $id) {
                $place = $instances[$id]['placement'];
                $wrap = max(0.5, (float) $place['wrap']);
                $share = (2 * M_PI) * $wrap / $total;
                // پهنای خودِ پنل، فشرده‌نشده: پنل‌ها کمی روی هم می‌آیند، همان‌طور
                // که جای درز روی پارچه هست
                $half = min(M_PI, $wrap / (2 * max(0.5, $radius)), ($share / 2) * static::PANEL_OVERLAP);
                $middle = $at + ($share / 2);
                $u0 = $middle - $half;
                $u1 = $middle + $half;
                $at += $share;

                /*
                 * بازوی راست آینهٔ چپ است، نه چرخشِ آن.
                 *
                 * هندسهٔ نمونهٔ آینه‌شده را سرور خودش قرینه کرده، پس با *همان*
                 * بازهٔ زاویه، نگاشتِ x→u هم برعکس می‌شود و نتیجه‌اش انعکاس نسبت
                 * به میانهٔ همان بازه است، نه نسبت به صفر. تا وقتی بازه وسط‌چین
                 * بود (آستین یک‌تکه، ‎−π..π) فرقی نمی‌کرد؛ ولی پنل‌های آستینِ
                 * دوتکه وسط‌چین نیستند: پنل بالای کت ‎−۳٫۸۳..۱٫۷۲ می‌گیرد، یعنی
                 * میانه‌اش ‎−۱٫۰۶ و بازوی راست ۱۲۱ درجه چرخیده می‌نشست. ناقرینگیِ
                 * چیدن ۴٫۹ سانتی‌متر روی کت و ۱۰٫۷ روی ترنچ‌کت اندازه گرفته شد،
                 * و در عکس دمِ کت یک‌طرف هشت سانتی‌متر بالا کشیده بود و پوستِ
                 * باسن از زیرش پیدا بود (۶۰۱ پیکسل).
                 *
                 * پس بازهٔ نمونهٔ آینه‌شده هم آینه می‌شود: [−u1, −u0].
                 */
                if ($instances[$id]['mirrored']) {
                    [$u0, $u1] = [-$u1, -$u0];
                }

                foreach (['placement', 'payload'] as $where) {
                    $target = $where === 'payload' ? 'placement' : null;
                    $slot = &$instances[$id][$where];

                    if ($target !== null) {
                        $slot = &$slot[$target];
                    }

                    $slot['u0'] = round($u0, 4);
                    $slot['u1'] = round($u1, 4);
                    $slot['radius'] = $radius;
                    unset($slot);
                }
            }
        }
    }

    /**
     * پنل‌های یک آستین از *زیربغل* هم‌تراز می‌شوند، نه از سرِ قطعه.
     *
     * تا امروز هر پنلِ آستین سرش را روی یک ترازِ ثابت می‌گذاشت. برای آستینی که
     * هر دو پنلش کپِ کم‌عمق دارند (کت اسپرت: ۶٫۱ و ۳٫۸ سانتی‌متر) اختلافش ناچیز
     * بود، ولی کت رسمی کپِ ۱۱٫۹ سانتی‌متری دارد و آستین زیرش اصلاً کپ ندارد
     * (صفر): دو پنل با ۱۱٫۹ سانتی‌متر اختلافِ ارتفاع چیده می‌شدند، در حالی که
     * درزِ پهلوی هر دو ۴۸٫۲ سانتی‌متر است و از همان زیربغل شروع می‌شود. یعنی
     * قیدِ درز باید ده سانتی‌متر یکی را بالا و دیگری را پایین می‌کشید؛ آستین
     * روی بازو مچاله می‌شد و ساعد لخت می‌ماند. اندازه گرفته شد: پوششِ دورِ بازو
     * ۹۰ درجه از ۳۶۰ در برابر ۳۳۰ برای کت اسپرت، و ۹۳ نقطه از ۳۱۲ بازوی لخت.
     * در عکس، هر دو ساعد قرمز بود (۲۳۶۱ و ۸۹۴ پیکسل).
     *
     * زیربغل جایی است که درزِ پهلوی پنل شروع می‌شود — همان نقطه‌ای که خیاط دو
     * تکه را از آن به هم می‌رساند. پس همهٔ پنل‌ها زیربغلشان را روی یک تراز
     * می‌گذارند؛ اینکه این تراز کجای بازهٔ امروز بیفتد، UNDERARM_DIP می‌گوید.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, string>  $ids
     */
    protected function levelUnderarms(array &$instances, array $ids, DrapeBody $body): void
    {
        $drops = [];

        foreach ($ids as $id) {
            $drop = $this->underarmDrop($instances[$id]);

            if ($drop === null) {
                return; // پنلی که درز پهلو ندارد؛ حدس نمی‌زنیم
            }

            $drops[$id] = $drop;
        }

        $anchor = min($drops) + (static::UNDERARM_DIP * (max($drops) - min($drops)));

        foreach ($ids as $id) {
            $lower = ($anchor - $drops[$id]) / max(1.0, $body->height);

            if (abs($lower) < 0.001) {
                continue;
            }

            $yTop = round($instances[$id]['placement']['y_top'] - $lower, 4);

            $instances[$id]['placement']['y_top'] = $yTop;
            $instances[$id]['payload']['placement']['y_top'] = $yTop;
            $instances[$id]['top_cm'] = $yTop * $body->height;
        }
    }

    /**
     * فاصلهٔ سرِ یک پنلِ آستین تا خطِ زیربغلِ خودش، سانتی‌متر.
     *
     * زیربغل بالاترین سرِ درزِ پهلوست: زیرِ آن، دو پنل به هم دوخته می‌شوند و
     * بالای آن، سرآستین به حلقه می‌رود. null یعنی این پنل درز پهلو ندارد.
     */
    protected function underarmDrop(array $instance): ?float
    {
        $polygon = $instance['polygon'];
        $top = null;
        $seam = null;

        foreach ($polygon as $point) {
            $y = (float) $point['y'];
            $top = $top === null ? $y : min($top, $y);
        }

        foreach ($instance['edges'] as $edge) {
            if (($edge['tag'] ?? '') !== 'side') {
                continue;
            }

            foreach ([$edge['start'], $edge['end']] as $at) {
                $y = (float) ($polygon[$at]['y'] ?? 0);
                $seam = $seam === null ? $y : min($seam, $y);
            }
        }

        return ($top === null || $seam === null) ? null : max(0.0, $seam - $top);
    }

    /** ناحیه بدن که قطعه در آن می‌نشیند. */
    protected function zone(string $role, ?string $side): string
    {
        $back = $side === 'back';

        return match ($role) {
            'sleeve' => 'sleeve',
            'collar' => 'collar',
            'detail' => 'detail',
            'skirt' => $back ? 'skirt_back' : 'skirt_front',
            'leg' => $back ? 'leg_back' : 'leg_front',
            default => $back ? 'torso_back' : 'torso_front',
        };
    }

    /**
     * ترتیبِ بلندیِ برچسب‌های لبه روی بدن، از بالا به پایین.
     *
     * ببینید edgeAnchors(): برای لبهٔ بالا، «بلندترین» برنده است نه «بلندترین
     * طول».
     */
    protected const TAG_HEIGHT = ['neck' => 4, 'shoulder' => 3, 'armhole' => 2, 'waist' => 1];

    /**
     * برچسب لبه بالا و لبه پایین قطعه.
     *
     * لبه‌ی پایین پرتکرارترین برچسب غیرِ «default» است. لبه‌ی بالا ولی نه:
     * آن‌جا برچسبی برنده است که روی بدن از همه بالاتر می‌نشیند.
     *
     * چرا: y_top جای *بالاترین نقطهٔ* قطعه است، و بالاترین نقطه روی بلندترین
     * برچسب می‌افتد، نه روی بلندترین لبه. تنهٔ پشتِ کت هم لبهٔ یقه دارد (۱۰٫۱
     * سانتی‌متر) هم سرشانه (۱۰٫۸)؛ با ملاکِ طول، سرشانه می‌بُرد و گوشهٔ یقهٔ پشت
     * ۶٫۴ سانتی‌متر زیرِ گردن چیده می‌شد. پشتِ ترنچ‌کت بدتر بود: حلقه (۱۱٫۹) از
     * یقه (۱۱٫۰) بلندتر است، پس یقهٔ پشت ۱۴٫۶ سانتی‌متر پایین می‌افتاد و درزِ
     * سرشانه کلِ لباس را پایین می‌کشید — همان گودیِ بازِ گردن در عکس.
     *
     * @return array{top: string|null, bottom: string|null}
     */
    protected function edgeAnchors(array $instance): array
    {
        [, $minY, , $maxY] = $instance['bounds'];
        $height = max(0.5, $maxY - $minY);
        $band = $height / 8;

        $top = [];
        $bottom = [];

        /*
         * لبه‌ای که *سرش* بالاترین نقطهٔ قطعه است هم لبهٔ بالاست، هرچند میانه‌اش
         * پایین باشد.
         *
         * یقهٔ هفتِ پیراهن راپ همین بود: از نوکِ سرشانه تا سرِ کمر می‌رود، پس
         * میانه‌اش ۱۲ سانتی‌متر زیرِ لبهٔ بالا می‌افتد و اصلاً «لبهٔ بالا» شمرده
         * نمی‌شد. تنها برچسبِ بالا «سرشانه» می‌ماند و قطعه روی ترازِ سرشانه
         * چیده می‌شد، در حالی که بالاترین نقطه‌اش نقطهٔ گردن است — یعنی ۶٫۴
         * سانتی‌متر پایین‌تر از جای خودش، و همان‌قدر از سینه لخت می‌ماند.
         */
        $peak = null;

        foreach ($instance['polygon'] as $index => $point) {
            if ($peak === null || $point['y'] < $instance['polygon'][$peak]['y']) {
                $peak = $index;
            }
        }

        foreach ($instance['edges'] as $edge => $data) {
            if ($data['tag'] === 'default' || $data['length'] < 0.5) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $data['start'], $data['end']);

            if ($middle['y'] <= $minY + $band || $data['start'] === $peak || $data['end'] === $peak) {
                $top[$data['tag']] = ($top[$data['tag']] ?? 0) + $data['length'];
            }

            if ($middle['y'] >= $maxY - $band) {
                $bottom[$data['tag']] = ($bottom[$data['tag']] ?? 0) + $data['length'];
            }
        }

        arsort($bottom);

        // بلندترین برچسب برنده است؛ برچسبِ بی‌تراز (دم، پهلو) با طول سنجیده
        // می‌شود، چون از آن‌ها هیچ ارتفاعی درنمی‌آید
        uksort($top, function (string $one, string $two) use ($top) {
            $rank = (static::TAG_HEIGHT[$two] ?? 0) <=> (static::TAG_HEIGHT[$one] ?? 0);

            return $rank !== 0 ? $rank : ($top[$two] <=> $top[$one]);
        });

        return [
            'top' => array_key_first($top),
            'bottom' => array_key_first($bottom),
        ];
    }

    /**
     * ارتفاع لبه بالای قطعه‌هایی که خودشان برچسب گویا ندارند.
     *
     * سجاف و پیش‌بند و مچ‌بند لبه‌هایشان «default» است و از روی هندسه نمی‌شود
     * فهمید کجای بدن می‌نشینند؛ ولی ژنراتور در meta.part گفته این قطعه چیست و
     * همان کافی است تا نقطه شروع بی‌ربط نباشد.
     */
    protected function partLevel(?string $part, DrapeBody $body): ?float
    {
        return match ($part) {
            'cuff' => $body->wristLevel(),
            'waistband', 'belt' => $body->level('waist'),
            'collar', 'hood' => $body->level('neck'),
            'pocket' => $body->level('highHip'),
            'strap', 'placket', 'facing', 'lapel' => $body->level('shoulder'),
            'panty' => $body->level('hip'),
            default => null,
        };
    }

    /**
     * بازهٔ زاویه‌ایِ پنلی که هم‌پوشانی اعلام کرده — جلوی راپ.
     *
     * هر پنلِ دیگری «نیمهٔ بدن» است: لبهٔ مرکزی‌اش روی خط مرکز می‌نشیند و لبهٔ
     * دیگرش روی درز پهلو، و کلِ پهنای کادر روی همان نیم‌دور پخش می‌شود. جلوی راپ
     * ولی ۱۵ سانتی‌متر از خط مرکز پهن‌تر بریده شده و همان اضافه باید *روی* جلوی
     * دیگر بیفتد. با قاعدهٔ عمومی، آن ۱۵ سانتی‌متر هم درونِ همان نیم‌دور فشرده
     * می‌شد و خط مرکز جلو به‌جای صفر روی ۳۴ درجه می‌افتاد — یعنی از ۳۴− تا ۳۴+
     * درجه هیچ پارچه‌ای نبود و سینه از یقه تا زیرِ سینه لخت می‌ماند.
     *
     * پس این‌جا دو نقطه لنگر می‌شوند و مقیاس از خودشان درمی‌آید:
     *
     *   خط مرکز جلو (یعنی «هم‌پوشانی» سانتی‌متر آن‌طرف‌تر از لبهٔ پنل) ⇦ صفر
     *   درز پهلو (باریک‌ترین جای لبهٔ پهلو، یعنی سرِ خط کمر)          ⇦ ۹۰ درجه
     *
     * باقیِ پهنا — کلوشِ دامن — به همان مقیاس بیرون می‌زند، پس دو سرِ درز پهلوی
     * دامن هم‌راستا می‌مانند. اندازه‌گیری روی راپِ سایز ۴۰: ۲۳٫۵ سانتی‌متر از خط
     * مرکز تا درز پهلو، پس هر سانتی‌متر ۳٫۸ درجه و هم‌پوشانیِ ۱۵ سانتی‌متری ۵۷
     * درجه از خط مرکز رد می‌شود.
     *
     * @return array{0: float, 1: float}|null  null یعنی این قطعه هم‌پوشانی ندارد
     */
    protected function overlapArc(array $instance, PatternPiece $model, float $center): ?array
    {
        $overlap = (float) ($model->meta['crosses_center'] ?? 0);

        if ($overlap < 0.5) {
            return null;
        }

        [$minX, , $maxX] = $instance['bounds'];
        $toRight = $this->centerAtLeft($instance);
        // نمونهٔ آینه‌شده قرینه شده، پس مرکز سمت راستش است و پهلو سمت چپ
        $middle = $toRight ? $minX + $overlap : $maxX - $overlap;
        $side = $this->sideSeamX($instance, $toRight) ?? ($toRight ? $maxX : $minX);
        $reach = max(3.0, abs($side - $middle));
        $scale = M_PI_2 / $reach;

        return [
            round($center - (($middle - $minX) * $scale), 4),
            round($center + (($maxX - $middle) * $scale), 4),
        ];
    }

    /**
     * نوارِ مورب: قطعه‌ای که ارتفاعش در طولِ خودش عوض می‌شود.
     *
     * مدلِ چیدن جز «حلقهٔ افقی دورِ محورِ بدن در یک ارتفاعِ ثابت» چیزی بلد نبود،
     * و برای نوارِ لبهٔ راپ — که باید از سرِ کمر مورب تا نقطهٔ یقه برود — همان
     * حلقه را می‌ساخت: نواری ۶۵ سانتی‌متری که در ارتفاع سرشانه دورِ سینه
     * می‌چرخید و مچاله می‌شد، و لبهٔ راپ بی‌پوشش می‌ماند.
     *
     * دو سرِ مسیر را خودِ ژنراتور می‌گوید (meta.drape_run)، چون تنها اوست که
     * می‌داند نوار کدام لبه را می‌پوشاند. این‌جا فقط نام ترازها به ارتفاع تبدیل
     * می‌شود و برای نمونهٔ دوم — نوارِ آن یکی جلو — زاویه‌ها قرینه می‌شوند.
     *
     * @return array{u0: float, u1: float, y_top: float, y_end: float}|null
     */
    protected function slantRun(array $instance, PatternPiece $model, DrapeBody $body): ?array
    {
        $run = $model->meta['drape_run'] ?? null;

        if (! is_array($run) || ! isset($run['from'], $run['to'])) {
            return null;
        }

        // نوار دو بار بریده می‌شود، یکی برای هر جلو؛ دومی قرینهٔ اولی می‌نشیند
        $flip = ((int) ($instance['instance'] ?? 0)) % 2 === 1 ? -1.0 : 1.0;

        $level = fn (array $end) => $this->levelOf((string) ($end['level'] ?? ''), $body)
            ?? $body->level('shoulder');

        return [
            'u0' => round($flip * (float) ($run['from']['u'] ?? 0), 4),
            'u1' => round($flip * (float) ($run['to']['u'] ?? 0), 4),
            'y_top' => $level($run['from']),
            'y_end' => $level($run['to']),
        ];
    }

    /**
     * ایکسِ درز پهلو روی خودِ قطعه: باریک‌ترین جای لبهٔ پهلو.
     *
     * لبهٔ پهلوی دامنِ کلوش از کمر تا دم باز می‌شود؛ آنچه باید روی ۹۰ درجه بنشیند
     * سرِ کمرِ همان لبه است، نه دمش. برای بالاتنه هر دو تقریباً یکی‌اند.
     */
    protected function sideSeamX(array $instance, bool $toRight): ?float
    {
        $best = null;

        foreach ($instance['edges'] as $data) {
            if ($data['tag'] !== 'side') {
                continue;
            }

            foreach ([$data['start'], $data['end']] as $at) {
                $x = (float) ($instance['polygon'][$at]['x'] ?? 0);
                $best = $best === null ? $x : ($toRight ? min($best, $x) : max($best, $x));
            }
        }

        return $best;
    }

    /** آیا این قطعه دور چیزی می‌پیچد (یقه، کمربند، نوار، مچ‌بند)؟ */
    protected function wrapsAround(string $role, PatternPiece $model): bool
    {
        return $role === 'collar' || in_array($model->meta['part'] ?? null, [
            'waistband', 'band', 'binding', 'cuff', 'collar',
        ], true);
    }

    /**
     * طولِ پیچیدنِ یک نوار: نصف محیط منهای پهنای نوار.
     *
     * نوار یقه از چند لبه‌ی پشت‌سرهم ساخته شده و هیچ‌کدام به‌تنهایی طولش را
     * نمی‌گویند؛ ولی هر نواری دو ضلع بلند دارد و دو ضلع کوتاه، پس نصف محیط
     * منهای بلندی، همان ضلع بلند است.
     */
    protected function wrapLength(array $instance): float
    {
        $perimeter = 0.0;

        foreach ($instance['edges'] as $info) {
            $perimeter += (float) $info['length'];
        }

        [, $minY, , $maxY] = $instance['bounds'];

        return max(0.0, ($perimeter / 2) - ($maxY - $minY));
    }

    /** ارتفاع ترازی که یک برچسب لبه به آن اشاره می‌کند. */
    protected function levelOf(?string $tag, DrapeBody $body): ?float
    {
        return match ($tag) {
            'neck' => $body->level('neck'),
            'shoulder' => $body->level('shoulder'),
            'armhole' => $body->level('armhole'),
            'waist' => $body->level('waist'),
            default => null,
        };
    }

    /** نزدیک‌ترین تراز بدن به میانه ارتفاع قطعه، از میان ترازهای همان نقش. */
    protected function radiusHint(string $role, float $middle, DrapeBody $body, array $instance): string
    {
        $wrist = $body->wristLevel();

        return match ($role) {
            'collar' => 'neck',
            'sleeve' => $body->nearestRadius($middle, ['bicep', 'wrist'], [
                'bicep' => $body->level('armhole'),
                'wrist' => $wrist,
            ]),
            'leg' => $body->nearestRadius($middle, ['hip', 'thigh', 'knee', 'ankle'], [
                'thigh' => $body->level('crotch'),
            ]),
            'skirt' => $body->nearestRadius($middle, ['waist', 'highHip', 'hip', 'knee']),
            'detail' => $body->nearestRadius($middle, ['neck', 'bust', 'waist', 'hip', 'knee', 'wrist', 'ankle'], [
                'wrist' => $wrist,
            ]),
            default => $body->nearestRadius($middle, ['neck', 'armhole', 'bust', 'underBust', 'waist', 'highHip', 'hip']),
        };
    }

    /**
     * آیا مرکز بدن (خط مرکز جلو یا پشت) سمت چپِ خودِ قطعه است؟
     *
     * درز پهلو دورترین جای قطعه از مرکز بدن است؛ پس اگر لبه‌های «side» سمت راست
     * قطعه باشند، مرکز سمت چپ است. برای نمونه آینه‌شده این حساب خودبه‌خود برعکس
     * می‌شود، چون خود هندسه قرینه شده است.
     */
    protected function centerAtLeft(array $instance): bool
    {
        [$minX, , $maxX] = $instance['bounds'];
        $middle = ($minX + $maxX) / 2;

        $weight = 0.0;
        $sum = 0.0;

        foreach ($instance['edges'] as $data) {
            if ($data['tag'] !== 'side') {
                continue;
            }

            $point = DrapeGeometry::arcMidpoint($instance['polygon'], $data['start'], $data['end']);
            $sum += $point['x'] * $data['length'];
            $weight += $data['length'];
        }

        if ($weight <= 0.0) {
            return ! $instance['mirrored'];
        }

        /*
         * لبه‌های پهلویی که خودشان قرینه‌اند، هیچ سمتی را نشان نمی‌دهند.
         *
         * جیبِ چهارگوش دو پهلوی هم‌طول دارد و میانگینشان دقیقاً وسطِ قطعه
         * می‌افتد؛ آینه کردن هم چیزی را عوض نمی‌کند، پس هر دو نمونه یک جواب
         * می‌گرفتند و *هر دو جیبِ* ترنچ‌کت روی یک پهلو می‌نشستند (مرکزشان
         * هر دو x = −۶٫۹ سانتی‌متر). همین برای درپوشِ جیبِ کت رسمی هم بود.
         * وقتی هندسه چیزی نمی‌گوید، شمارهٔ نمونه می‌گوید.
         */
        $offset = ($sum / $weight) - $middle;

        if (abs($offset) < 0.05 * max(0.5, $maxX - $minX)) {
            return ! $instance['mirrored'];
        }

        return $offset > 0;
    }

    /* ---------------------------------------------------------------------
     |  درزها
     * ------------------------------------------------------------------- */

    /**
     * ترجمه رابطه‌های دوخت به کمان‌های همین بسته.
     *
     * @param  array<int, array<string, mixed>>  $relations
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<string, array<int, string>>  $byCode
     * @param  array<int, array<string, mixed>>  $unmatched
     * @return array<int, array<string, mixed>>
     */
    protected function seams(array $relations, array $instances, array $byCode, array &$unmatched): array
    {
        $seams = [];
        $resolved = [];

        foreach ($relations as $index => $relation) {
            $from = $this->relationSide($relation['from'] ?? []);
            $to = $this->relationSide($relation['to'] ?? []);

            if ($from === null || $to === null) {
                $unmatched[] = $this->unmatched($relation, $index, 'رابطه دوخت سرِ درستی ندارد.');

                continue;
            }

            $left = $this->arcs($instances, $byCode, $from['piece'], $from['edges']);
            $right = $this->arcs($instances, $byCode, $to['piece'], $to['edges']);

            if ($left === [] || $right === []) {
                $missing = $left === [] ? $from['piece'] : $to['piece'];
                $unmatched[] = $this->unmatched($relation, $index, "لبه‌های خواسته‌شده روی قطعه «{$missing}» در بسته پیدا نشد.");

                continue;
            }

            $resolved[$index] = [
                'left' => $left,
                'right' => $right,
                'relation' => $relation,
                // دو سرِ رابطه یک قطعه و یک لبه است (درزِ فاق): هر جفت یک بار
                'self' => $from['piece'] === $to['piece'] && $from['edges'] === $to['edges'],
            ];
        }

        $resolved = $this->share($resolved);

        foreach ($resolved as $index => $entry) {
            $relation = $entry['relation'];
            $label = (string) ($relation['label'] ?? 'درز');

            [$left, $right, $zipped] = $this->balance($entry['left'], $entry['right']);

            // کمانِ بریده‌شده تکه‌به‌تکه با همان ترتیب جفت می‌شود؛ ببینید balance
            $pairs = $zipped
                ? ['matched' => array_map(null, $left, $right), 'left' => [], 'right' => []]
                : $this->pairArcs($left, $right, (bool) ($entry['self'] ?? false));

            foreach ($pairs['matched'] as [$a, $b]) {
                $seams[] = $this->seam($a, $b, $label, $index, $relation);
            }

            if ($pairs['left'] !== [] || $pairs['right'] !== []) {
                $unmatched[] = $this->unmatched($relation, $index, sprintf(
                    'تعداد کمان دو سمت برابر نشد؛ %d کمان بی‌جفت ماند.',
                    count($pairs['left']) + count($pairs['right']),
                ));
            }
        }

        return $seams;
    }

    /**
     * فهرست رابطه‌های دوخت.
     *
     * suggest() جفت‌های نام‌دار را می‌دهد (سرشانه، پهلو، آستین، یقه) و complete()
     * کمان‌های جفت‌نشده — درز پرنسسی و پنل‌های کرست — را روی هم می‌آورد. خروجی
     * دوم فقط «افزوده‌ها» است، پس دو فهرست به هم چسبانده می‌شوند؛ اگر روزی
     * complete() خودش فهرست کامل را برگرداند، تکراری‌ها اینجا کنار می‌روند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function relations(Pattern $pattern): array
    {
        $relations = SewingRelationBuilder::suggest($pattern);

        if (method_exists(SewingRelationBuilder::class, 'complete')) {
            $relations = array_merge($relations, SewingRelationBuilder::complete($pattern, $relations));
        }

        $seen = [];
        $out = [];

        foreach ($relations as $relation) {
            $from = $this->relationSide($relation['from'] ?? []);
            $to = $this->relationSide($relation['to'] ?? []);
            $key = $from === null || $to === null
                ? null
                : $from['piece'].'|'.implode(',', $from['edges']).'~'.$to['piece'].'|'.implode(',', $to['edges']);

            if ($key !== null && isset($seen[$key])) {
                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }

            $out[] = $relation;
        }

        return $out;
    }

    /**
     * یک سرِ رابطه: کد قطعه و فهرست لبه‌های اصلی.
     *
     * suggest() یک لبه تکی می‌دهد و complete() آرایه‌ای از لبه‌های پشت‌سرهم؛ هر
     * دو شکل پذیرفته می‌شود.
     *
     * @return array{piece: string, edges: array<int, int>}|null
     */
    protected function relationSide(array $side): ?array
    {
        $code = trim((string) ($side['piece'] ?? ''));

        if ($code === '') {
            return null;
        }

        $edges = [];

        if (isset($side['edges']) && is_array($side['edges'])) {
            foreach ($side['edges'] as $edge) {
                if (is_numeric($edge)) {
                    $edges[] = (int) $edge;
                }
            }
        } elseif (is_numeric($side['edge'] ?? null)) {
            $edges[] = (int) $side['edge'];
        }

        return $edges === [] ? null : ['piece' => $code, 'edges' => $edges];
    }

    /**
     * کمان‌های یک سرِ رابطه روی همه نمونه‌های آن قطعه.
     *
     * یک لبه روی قطعه‌ای که تایش باز شده دو بار می‌آید (یکی روی هر نیمه)، پس
     * خروجی فهرست کمان است نه یک کمان.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function arcs(array $instances, array $byCode, string $code, array $edges): array
    {
        $arcs = [];

        foreach ($byCode[$code] ?? [] as $id) {
            $instance = $instances[$id];

            foreach (DrapeGeometry::runs($instance['origins'], $edges) as $run) {
                $from = $instance['spans'][$run[0]][0];
                $to = $instance['spans'][$run[count($run) - 1]][1];
                $length = 0.0;

                foreach ($run as $edge) {
                    $length += $instance['lengths'][$edge] ?? 0.0;
                }

                if ($length < 0.05) {
                    continue;
                }

                $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $from, $to);

                $arcs[] = [
                    'piece' => $id,
                    'from' => $from,
                    'to' => $to,
                    'length' => $length,
                    'instance' => $instance,
                    'at' => $this->onBody($instance, $middle),
                    'frame' => $this->frame($instance['role']),
                    'body_side' => $this->bodySide($instance),
                ];
            }
        }

        return $arcs;
    }

    /**
     * دو نمونه‌ی دقیقاً هم‌جا، یک لایه‌اند نه دو قطعه.
     *
     * یقه و یوک و نوارها دو بار بریده می‌شوند چون دو لایه دارند: رو و زیر.
     * هر دو لایه یک شکل و یک جا دارند، پس در بسته دو نمونه‌ی هم‌جا می‌شوند و
     * روی مانکن دو پارچه‌ی هم‌اندازه روی هم می‌افتند و با هم می‌جنگند — همان
     * توده‌ای که روی سرشانه‌ی پیراهن دیده می‌شد و لباس را نامتقارن نشان می‌داد
     * (یوک دوم فقط به یقه‌ی دوم دوخته بود و آزاد می‌ماند).
     *
     * ملاک هندسه است نه نام: نمونه‌ای که بازه‌ی زاویه‌ای و ارتفاعش دقیقاً با
     * نمونه‌ی پیش از خودش یکی است، لایه‌ی دوم همان است. لنگه‌ی چپ و راست بازه‌ی
     * یکسان ندارند (یا سمتشان فرق دارد)، پس دست‌نخورده می‌مانند.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, string>  $notes
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<int, string>>}
     */
    protected function dedupe(array $instances, array &$notes): array
    {
        $seen = [];
        $kept = [];
        $byCode = [];
        $dropped = 0;

        foreach ($instances as $id => $instance) {
            $placement = $instance['placement'];
            $key = implode('|', [
                $instance['code'],
                $instance['side'] ?? '—',
                /*
                 * قطعهٔ آینه‌شده تکراری نیست؛ آن یکی طرف است.
                 *
                 * پاچهٔ راست و چپ یک کد دارند، یک بازهٔ زاویه‌ای و یک ارتفاع —
                 * فرقشان فقط آینه بودن است. بی این، دومی «هم‌جا» شمرده می‌شد و
                 * حذف: شلوار روی مانکن یک پاچه داشت. جای نشستنش را مرورگر از
                 * روی همین شماره انتخاب می‌کند.
                 */
                $instance['mirrored'] ? 'آینه' : 'اصل',
                round($placement['u0'], 3),
                round($placement['u1'], 3),
                round($placement['y_top'], 4),
            ]);

            if (isset($seen[$key])) {
                $dropped++;

                continue;
            }

            $seen[$key] = true;
            $kept[$id] = $instance;
            $byCode[$instance['code']][] = $id;
        }

        if ($dropped > 0) {
            $notes[] = $dropped.' قطعه‌ی هم‌جا (لایه‌ی دوم یقه، یوک یا نوار) در پیش‌نمایش یک بار نشان داده می‌شود.';
        }

        return [$kept, $byCode];
    }

    /**
     * قطعه‌ای که هیچ رابطه‌ای به آن نرسیده، به همسایه‌اش دوخته می‌شود.
     *
     * سنجش روی کل کاتالوگ: از ۲۳۶ مدل، ۵۲۱ قطعه هیچ درزی نداشتند — جیب و
     * سجاف و نوار، ولی همچنین ۵۴ یقه، ۴۳ تکه دامن، ۳۱ آستین و ۲۸ تنه. قطعه‌ی
     * بی‌درز روی مانکن یا می‌افتد یا باید پنهان شود؛ هر دو یعنی لباس ناقص.
     *
     * فهرست رابطه‌ها این‌ها را نمی‌بیند چون نامشان را نمی‌شناسد. ولی جای همه‌شان
     * روی بدن معلوم است: یقه کنار خط یقه است، جیب روی تنه، نوار روی لبه‌ای که
     * می‌پوشاند. پس همان ملاکی که برای جفت‌کردن کمان‌ها داریم — نزدیکی روی بدن
     * و هم‌طولی — این‌جا هم به کار می‌آید: بلندترین کمانِ قطعه‌ی جامانده به
     * نزدیک‌ترین کمانِ هم‌طولِ آزاد روی یک قطعه‌ی دوخته‌شده می‌رسد.
     *
     * سخت‌گیرانه است و باید باشد: درزی که وجود ندارد لباس را پیچ می‌دهد.
     *
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function adopt(array $instances, array $seams): array
    {
        $stitched = [];
        $used = [];

        foreach ($seams as $seam) {
            foreach (['a', 'b'] as $end) {
                $stitched[$seam[$end]['piece']] = true;
                $used[$seam[$end]['piece'].'|'.$seam[$end]['from'].'|'.$seam[$end]['to']] = true;
            }
        }

        $free = [];

        foreach ($instances as $id => $instance) {
            if (! isset($stitched[$id])) {
                continue;
            }

            foreach ($this->sewableArcs($instance) as $arc) {
                if (! isset($used[$id.'|'.$arc['from'].'|'.$arc['to']])) {
                    $free[] = $arc;
                }
            }
        }

        $out = [];
        /*
         * دو نمونهٔ یک قطعه باید به دو نمونهٔ یک قطعه دوخته شوند.
         *
         * این پاس کمان‌های جامانده را یکی‌یکی برمی‌دارد و هر کمانی که رفت، رفت.
         * نمونهٔ اول اول انتخاب می‌کند و آینه‌اش با هرچه مانده می‌سازد — پس دو
         * سمتِ بدن دو جور دوخته می‌شوند. روی کت‌وشلوار همین شد: آستینِ چپ به
         * *آسترِ* پشت دوخته شد و آستینِ راست به خودِ پشت. آزمونِ «دو بازو یک‌جور
         * دوخته شوند» همین را گرفت.
         *
         * پس هرچه یک نمونه انتخاب کرد، برای نمونهٔ دیگرِ همان قطعه یادداشت
         * می‌شود و اول همان جست‌وجو می‌شود. اگر پیدا نشد، دوباره آزادانه
         * می‌گردیم — بی‌دوخت ماندن بدتر از ناقرینه بودن است.
         */
        $twin = [];

        foreach ($instances as $id => $instance) {
            if (isset($stitched[$id]) || $free === []) {
                continue;
            }

            $arcs = $this->sewableArcs($instance);
            $mate = $twin[$this->codeOf($id)] ?? null;
            $best = null;

            // اول با قیدِ «همان قطعه‌ای که جفتم گرفت»، و اگر چیزی نبود بی‌قید
            foreach ($mate === null ? [null] : [$mate, null] as $wanted) {
                foreach ($arcs as $arc) {
                    foreach ($free as $key => $partner) {
                        if ($wanted !== null && $this->codeOf($partner['piece']) !== $wanted) {
                            continue;
                        }

                        $longer = max($arc['length'], $partner['length']);

                        if ($longer < 4.0 || abs($arc['length'] - $partner['length']) / $longer > 0.25) {
                            continue;
                        }

                        /*
                         * «همسایه» تنها در یک دستگاه معنی دارد.
                         *
                         * distance() برای دو دستگاهِ متفاوت (تنه و بازو) فقط
                         * اختلافِ ارتفاع را برمی‌گرداند و زاویه را کنار می‌گذارد —
                         * چون زاویهٔ روی بازو با زاویهٔ روی تنه یکی نیست. نتیجه‌اش
                         * این بود که جیبِ روی تنه و کمانِ آستین در یک ارتفاع
                         * «فاصلهٔ صفر» می‌گرفتند: روی ترنچ‌کت، جیب و حلقهٔ کمربند و
                         * سجافِ گردن هر سه *به آستین* دوخته شدند و آستین را ۱۶
                         * سانتی‌متر روی بازو پایین کشیدند.
                         *
                         * درزِ واقعیِ میان‌دستگاهی — آستین به حلقه — از برچسب
                         * می‌آید نه از این چارهٔ آخر، پس چیزی از دست نمی‌رود.
                         */
                        if ($arc['frame'] !== $partner['frame']) {
                            continue;
                        }

                        $cost = $this->cost($arc, $partner);

                        if ($cost > 25.0) {
                            continue; // بیش از یک وجب دورتر، همسایه نیست
                        }

                        if ($best === null || $cost < $best['cost']) {
                            $best = ['cost' => $cost, 'arc' => $arc, 'partner' => $partner, 'key' => $key];
                        }
                    }
                }

                if ($best !== null) {
                    break;
                }
            }

            if ($best === null) {
                continue;
            }

            $out[] = $this->seam($best['arc'], $best['partner'], 'دوخت به قطعه‌ی همسایه', null, []);
            $stitched[$id] = true;
            $twin[$this->codeOf($id)] = $this->codeOf($best['partner']['piece']);
            unset($free[$best['key']]);
        }

        return $out;
    }

    /**
     * کمان‌های دوختنیِ یک نمونه، از بلند به کوتاه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sewableArcs(array $instance): array
    {
        $arcs = [];

        foreach ($instance['edges'] as $edge => $info) {
            if ($info['length'] < 2.0) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $info['start'], $info['end']);

            $arcs[] = [
                'piece' => $instance['id'],
                'from' => $info['start'],
                'to' => $info['end'],
                'length' => $info['length'],
                'tag' => (string) ($info['tag'] ?? 'default'),
                'instance' => $instance,
                'at' => $at = $this->onBody($instance, $middle),
                'frame' => $this->frame($instance['role']),
                'body_side' => $this->arcSide($instance, $at),
            ];
        }

        usort($arcs, fn (array $a, array $b) => $b['length'] <=> $a['length']);

        return $arcs;
    }

    /**
     * سمتِ بدنِ یک کمان — نه سمتِ قطعه‌ای که کمان رویش است.
     *
     * یوکِ پشت روی تای پارچه بریده می‌شود و از شانهٔ چپ تا شانهٔ راست می‌رسد؛
     * پس دو حلقهٔ آستین دارد، یکی چپ و یکی راست. تا وقتی سمت را از خودِ قطعه
     * می‌گرفتیم، هر دو «چپ» بودند و جریمهٔ سمتِ مخالف (هزار) آستینِ راست را از
     * یوک دور می‌کرد؛ اندازه‌گیری هزینهٔ ۱۰۰۹ را نشان داد. کمان جای خودش را
     * روی بدن دارد، پس سمتش را هم خودش دارد.
     *
     * آستین و پا استثناءاند: زاویهٔ آنها دور بازو و ران می‌چرخد، نه دور تن.
     *
     * @param  array{u: float, y: float}  $at
     */
    protected function arcSide(array $instance, array $at): ?string
    {
        if (in_array($instance['role'], ['sleeve', 'leg'], true)) {
            return $this->bodySide($instance);
        }

        $u = $this->wrap((float) ($at['u'] ?? 0));

        // نزدیکِ مرکزِ جلو یا مرکزِ پشت، کمان به هیچ سمتی تعلق ندارد
        if (abs($u) < 0.15 || abs(abs($u) - M_PI) < 0.15) {
            return null;
        }

        return $u < 0 ? 'left' : 'right';
    }

    /**
     * درزی که یک شریکش را جا گذاشته.
     *
     * پیراهنِ یوک‌دار نمونهٔ روشنش است: سرآستین باید هم به حلقهٔ تنه و هم به آن
     * تکه از حلقه که روی یوک افتاده دوخته شود. رابطه‌های سازنده ولی یک درز
     * می‌نویسند — سرآستین به حلقهٔ پشت — و لبهٔ ۵٫۹ سانتی‌متریِ حلقهٔ یوک بی‌دوخت
     * می‌ماند. نتیجه روی مانکن: ۱۸٫۴ سانتی‌متر سرآستین روی ۱۱٫۴ سانتی‌متر حلقه
     * چپانده می‌شود و یک زبانهٔ آزاد سر شانه تکان می‌خورد. در عکسِ کاربر همین
     * دیده می‌شد.
     *
     * قاعده‌ای که این را می‌گیرد اندازه‌پذیر است و به هیچ مدلی گره نخورده:
     * درزی که دو سرش بیش از حدِ آزادیِ پارچه ناهم‌طول‌اند، و کمانِ آزادی با همان
     * برچسب کنارش هست که تفاوت را پر می‌کند، شریکش را جا گذاشته. آن وقت کمانِ
     * بلند به نسبتِ طول میان دو شریک بریده می‌شود.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function splice(array $instances, array $seams): array
    {
        $free = $this->freeArcs($instances, $seams);

        if ($free === []) {
            return $seams;
        }

        foreach ($seams as $index => $seam) {
            if (($seam['kind'] ?? 'seam') !== 'seam') {
                continue;
            }

            $long = $seam['a']['length'] >= $seam['b']['length'] ? 'a' : 'b';
            $short = $long === 'a' ? 'b' : 'a';
            $excess = (float) $seam[$long]['length'] - (float) $seam[$short]['length'];

            if ($excess < 2.0 || $excess / max(0.01, (float) $seam[$long]['length']) < static::EASE_SHARE) {
                continue; // در حدِ آزادیِ پارچه؛ درز سالم است
            }

            $arcs = $this->arcsOf($instances, $seam);

            if ($arcs === null) {
                continue;
            }

            // چینِ اعلام‌شده خودش توضیحِ اضافه‌طول است؛ آن‌جا شریکی جا نمانده
            if ($excess <= $this->declaredFullness($arcs[$long]['instance']) + 1.0) {
                continue;
            }

            $best = null;

            foreach ($free as $key => $candidate) {
                if ($candidate['piece'] === $seam['a']['piece'] || $candidate['piece'] === $seam['b']['piece']) {
                    continue;
                }

                /*
                 * برچسبِ کمانِ آزاد باید همان برچسبِ سرِ کوتاه باشد. سرِ کوتاه
                 * همان چیزی است که کم آورده — حلقهٔ آستینِ تنه — و شریکِ جامانده
                 * هم حتماً حلقهٔ آستین است. با پذیرفتنِ برچسبِ سرِ بلند هم،
                 * لبهٔ بی‌نامِ پاتلت به خط یقهٔ پشت دوخته شد؛ اندازه‌گیری نشانش داد.
                 */
                if ($candidate['tag'] !== $arcs[$short]['tag'] || $candidate['tag'] === 'default') {
                    continue;
                }

                // باید همان کمبود را پر کند، نه چیز دیگری را
                if (abs($candidate['length'] - $excess) / max($candidate['length'], $excess) > 0.4) {
                    continue;
                }

                $cost = $this->cost($candidate, $arcs[$long]);

                if ($cost > 25.0) {
                    continue;
                }

                if ($best === null || $cost < $best['cost']) {
                    $best = ['cost' => $cost, 'arc' => $candidate, 'key' => $key];
                }
            }

            if ($best === null) {
                continue;
            }

            /*
             * کدام تکه به کدام شریک؟ جای هر شریک *روی خودِ کمان*.
             *
             * دو ملاکِ پیشین هر دو جهت را نمی‌دیدند. cost() برای دو دستگاهِ
             * متفاوت (بازو و تنه) فقط اختلافِ ارتفاع را می‌سنجد و با نوسانِ چند
             * میلی‌متری برمی‌گشت. بعد «هم‌طولی» آمد — و آن هم کور است: تکهٔ اول
             * همیشه از سرِ `from` بریده می‌شود و سهمِ بزرگ‌تر (تنه) را می‌گیرد،
             * ولی `from` روی یک آستین نوکِ کپ است و روی آینه‌اش زیربغل. اندازه
             * گرفته شد روی پیراهنِ کلاسیک: آستینِ چپ از نوکِ کپ [پشت ۱۲٫۸، یوک
             * ۶٫۷] و راست از زیربغل [پشت ۱۲٫۳، یوک ۷٫۲] — یعنی روی چپ یوک سرِ
             * زیربغل دوخته می‌شد و همان شانه لخت می‌ماند (۴۶ از ۲۱۶ نقطه در
             * برابر ۱۴). هر دو ملاک برای هر دو آستین «درست» می‌گفتند.
             *
             * جای شریک روی کمان (along) جهت را می‌داند: یوک بالاتر از نوکِ کپ
             * است و پشت میانِ کمان، پس از هر سری که پیموده شود، یوک سرِ بالایی
             * را می‌گیرد. تکه‌ها به همان ترتیب بریده و تکه‌به‌تکه جفت می‌شوند.
             */
            $partners = $this->alongOrder($arcs[$long], [$arcs[$short], $best['arc']]);
            $parts = $this->splitArc($arcs[$long], $this->shares($partners));

            if (count($parts) !== 2) {
                continue;
            }

            $seams[$index] = $this->seam($parts[0], $partners[0], (string) ($seam['label'] ?? 'درز'), $seam['relation'] ?? null, $seam);
            $seams[] = $this->seam($parts[1], $partners[1], (string) ($seam['label'] ?? 'درز'), $seam['relation'] ?? null, $seam);

            unset($free[$best['key']]);
        }

        return array_values($seams);
    }

    /**
     * خطِ خوابِ یقه، به شکلِ y روی همان چندضلعیِ بسته.
     *
     * یقهٔ یک‌تکه روی این خط تا می‌شود و می‌خوابد. چون قطعه ممکن است باز یا سروته
     * شده باشد، فاصله را از خودِ لبهٔ برچسب‌خوردهٔ «neck» می‌سنجیم نه از کادر، و
     * جواب را در دستگاهِ همان چندضلعی می‌دهیم تا مرورگر بی حساب‌وکتاب بخواندش.
     *
     * @param  array<int, array{x: float, y: float}>  $polygon
     * @param  array<int, array{tag: string, start: int}>  $edges
     */
    protected function rollLine(array $piece, array $polygon, array $edges): float|array|null
    {
        $roll = $piece['meta']['roll_line'] ?? null;

        if (! is_numeric($roll) || (float) $roll <= 0.01 || $polygon === []) {
            /*
             * خطِ برگردانِ مورب (کت): نشانگرِ «roll_line» روی قطعه، از نقطهٔ
             * شکست تا گردن. یقهٔ پیراهن با یک عدد (فاصله از لبهٔ گردن) خط می‌شود؛
             * لپهٔ کت با یک پاره‌خط در همان دستگاهِ چندضلعی. مرورگر روی هر دو
             * تا می‌زند (ببینید creaseAt).
             */
            foreach ((array) ($piece['markers'] ?? []) as $marker) {
                if (($marker['key'] ?? '') !== 'roll_line' || ! isset($marker['from']['x'], $marker['to']['x'])) {
                    continue;
                }

                $length = hypot((float) $marker['to']['x'] - (float) $marker['from']['x'], (float) $marker['to']['y'] - (float) $marker['from']['y']);

                if ($length < 1.0) {
                    continue;
                }

                return [
                    'x1' => round((float) $marker['from']['x'], 3),
                    'y1' => round((float) $marker['from']['y'], 3),
                    'x2' => round((float) $marker['to']['x'], 3),
                    'y2' => round((float) $marker['to']['y'], 3),
                ];
            }

            return null;
        }

        $neck = null;

        foreach ($edges as $info) {
            if (($info['tag'] ?? '') === 'neck' && isset($polygon[$info['start']]['y'])) {
                $neck = (float) $polygon[$info['start']]['y'];

                break;
            }
        }

        if ($neck === null) {
            return null;
        }

        [, $minY, , $maxY] = Geometry::bounds($polygon);

        // خط یقه یا کفِ کادر است یا سقفش؛ خطِ خواب از همان‌جا به درونِ قطعه می‌رود
        $inward = abs($neck - $minY) < abs($neck - $maxY) ? 1 : -1;

        return round($neck + ($inward * (float) $roll), 3);
    }

    /** پُریِ اعلام‌شدهٔ یک قطعه: چین و پیلی، سانتی‌متر. */
    protected function declaredFullness(array $instance): float
    {
        $total = 0.0;

        foreach (['gathers', 'pleats'] as $key) {
            foreach ((array) ($instance['meta'][$key] ?? []) as $entry) {
                $total += abs((float) ($entry['amount'] ?? ($entry['depth'] ?? 0)));
            }
        }

        return $total;
    }

    /**
     * کمان‌های دوختنیِ بی‌درز، روی همهٔ قطعه‌ها.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function freeArcs(array $instances, array $seams): array
    {
        $used = [];

        foreach ($seams as $seam) {
            foreach (['a', 'b'] as $end) {
                $used[$seam[$end]['piece'].'|'.$seam[$end]['from'].'|'.$seam[$end]['to']] = true;
            }
        }

        $free = [];

        foreach ($instances as $id => $instance) {
            foreach ($this->sewableArcs($instance) as $arc) {
                if (! isset($used[$id.'|'.$arc['from'].'|'.$arc['to']])) {
                    $free[] = $arc;
                }
            }
        }

        return $free;
    }

    /**
     * دو سر یک درز، به شکلِ کمانِ کامل (با نمونه و جای روی بدن).
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @return array{a: array<string, mixed>, b: array<string, mixed>}|null
     */
    protected function arcsOf(array $instances, array $seam): ?array
    {
        $out = [];

        foreach (['a', 'b'] as $end) {
            $instance = $instances[$seam[$end]['piece']] ?? null;

            if ($instance === null) {
                return null;
            }

            $from = (int) $seam[$end]['from'];
            $to = (int) $seam[$end]['to'];
            $tag = 'default';

            foreach ($instance['edges'] as $info) {
                if ((int) $info['start'] === $from) {
                    $tag = (string) ($info['tag'] ?? 'default');

                    break;
                }
            }

            $out[$end] = [
                'piece' => $seam[$end]['piece'],
                'from' => $from,
                'to' => $to,
                'length' => (float) $seam[$end]['length'],
                'tag' => $tag,
                'instance' => $instance,
                'at' => $this->onBody($instance, DrapeGeometry::arcMidpoint($instance['polygon'], $from, $to)),
                'frame' => $this->frame($instance['role']),
                'body_side' => $this->bodySide($instance),
            ];
        }

        return $out;
    }

    /**
     * بستن مرکز جلو و مرکز پشت.
     *
     * قطعه‌ای که دو برشِ آینه‌ای دارد — بالاتنهٔ پشتِ زیپ‌دار، جلوی پیراهن
     * دکمه‌دار — روی تن با زیپ یا دکمه بسته می‌شود، ولی هیچ رابطهٔ دوختی برایش
     * نوشته نمی‌شود چون دوخته نمی‌شود. برای نمای سه‌بعدی همین کافی است که لباس
     * از پشت باز بماند و دو نیمه از تن آویزان شوند؛ چیزی که در نخستین نما دیده
     * شد.
     *
     * پس دو نیمه از همان لبه‌ای که به هم می‌رسند بسته می‌شوند: بلندترین کمانِ
     * هر نیمه که نزدیک مرز مشترکِ دو نیمه است. اگر چنین کمانی پیدا نشود یا دو
     * طول به هم نخورند، چیزی بسته نمی‌شود.
     *
     * @param  array<string, array<int, string>>  $byCode
     * @return array<int, array<string, mixed>>
     */
    protected function closures(array $instances, array $byCode): array
    {
        $out = [];

        foreach ($byCode as $ids) {
            if (count($ids) !== 2) {
                continue;
            }

            [$first, $second] = [$instances[$ids[0]], $instances[$ids[1]]];

            if (empty($second['payload']['mirrored'])) {
                continue;
            }

            // مرز مشترک دو نیمه: جایی که بازهٔ زاویه‌ای یکی تمام و دیگری شروع می‌شود
            $meeting = $this->meetingAngle($first['placement'], $second['placement']);

            if ($meeting === null) {
                continue;
            }

            $a = $this->closureArc($first, $meeting);
            $b = $this->closureArc($second, $meeting);

            if ($a === null || $b === null) {
                continue;
            }

            $longer = max($a['length'], $b['length']);

            if ($longer < 8.0 || abs($a['length'] - $b['length']) / $longer > 0.2) {
                continue;
            }

            $front = abs(atan2(sin($meeting), cos($meeting))) < M_PI_2;

            $out[] = $this->seam(
                $a,
                $b,
                $front ? 'بستن مرکز جلو' : 'بستن مرکز پشت',
                // این درز از هیچ رابطه‌ای نیامده؛ خودِ هندسه آن را می‌بندد
                null,
                ['reverse' => true],
            );
        }

        return array_merge($out, $this->buttonCollars($instances));
    }

    /**
     * یقه جلوی گردن بسته می‌شود.
     *
     * نوارِ یقه دورِ گردن حلقه می‌زند و جلو با دکمه بسته می‌شود، ولی هیچ رابطهٔ
     * دوختی این را نمی‌گوید — چون در کارگاه دوخته نمی‌شود. نتیجه‌اش روی مانکن
     * این بود که دو سرِ یقه جلوی گردن از هم فاصله می‌گرفتند و ستونِ گردن از
     * لای همان شکاف دیده می‌شد: روی پیراهن ۲۵ خانه از سنجهٔ سایه، و روی کت رسمی
     * تمامِ پهنای گردن در دو سانتی‌متر بالای خط یقه.
     *
     * فقط دو سرِ کوتاهِ خودِ یقه، و فقط وقتی هم‌اندازه‌اند: یقهٔ برگردانِ کت هم
     * از این راه بسته می‌شود و ایرادی ندارد، چون دو سرش همان‌جا کنارِ هم‌اند —
     * هر دو به گوشهٔ خط یقهٔ مرکزِ جلو دوخته شده‌اند.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @return array<int, array<string, mixed>>
     */
    protected function buttonCollars(array $instances): array
    {
        $out = [];

        foreach ($instances as $instance) {
            if ($instance['role'] !== 'collar') {
                continue;
            }

            $ends = [];

            foreach ($this->sewableArcs($instance) as $arc) {
                if ($arc['tag'] === 'side') {
                    $ends[] = $arc;
                }
            }

            if (count($ends) !== 2) {
                continue;
            }

            $longer = max($ends[0]['length'], $ends[1]['length']);

            if ($longer < 2.0 || abs($ends[0]['length'] - $ends[1]['length']) / $longer > 0.15) {
                continue;
            }

            $out[] = $this->seam($ends[0], $ends[1], 'بستن جلوی یقه', null, ['reverse' => true]);
        }

        return $out;
    }

    /** زاویه‌ای که دو نیمه در آن به هم می‌رسند؛ null یعنی کنار هم نیستند. */
    protected function meetingAngle(array $a, array $b): ?float
    {
        foreach ([[$a['u1'], $b['u0']], [$a['u0'], $b['u1']]] as [$left, $right]) {
            if (abs($left - $right) < 0.05) {
                return ($left + $right) / 2;
            }
        }

        return null;
    }

    /**
     * کمانی از یک نمونه که روی مرز مشترک می‌نشیند.
     *
     * ملاک دوتاست و هر دو لازم است: نزدیکی به مرز، و بلندی. لبهٔ کوتاهِ کنارِ
     * مرز (مثل گوشهٔ یقه) نباید جای درزِ مرکز را بگیرد.
     */
    protected function closureArc(array $instance, float $meeting): ?array
    {
        $best = null;

        foreach ($instance['edges'] as $edge => $info) {
            if (in_array($info['tag'], ['hem', 'waist', 'neck', 'shoulder', 'armhole'], true)) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $info['start'], $info['end']);
            $at = $this->onBody($instance, $middle);
            $gap = abs($at['u'] - $meeting);

            if ($gap > 0.25 || $info['length'] < 8.0) {
                continue;
            }

            if ($best === null || $info['length'] > $best['length']) {
                $best = [
                    'piece' => $instance['id'],
                    'from' => $info['start'],
                    'to' => $info['end'],
                    'length' => $info['length'],
                    'instance' => $instance,
                    'at' => $at,
                    'frame' => $this->frame($instance['role']),
                    'body_side' => $this->bodySide($instance),
                ];
            }
        }

        return $best;
    }

    /**
     * کمانی که چند رابطه سراغش را می‌گیرند، میانشان تقسیم می‌شود.
     *
     * کمربندِ دامن کلوش یک نوارِ بلند است و خط کمرِ دامن چند کمان؛ سازنده‌ی
     * رابطه‌ها برای هر کمانِ کمر یک رابطه می‌نویسد و در همه‌شان همان یک نوار را
     * می‌گذارد. اگر هر رابطه کلِ نوار را بردارد، نوار از چند جا هم‌زمان کشیده
     * می‌شود و لباس روی مانکن مچاله می‌شود؛ اندازه‌گیری روی کاتالوگ: نودتا از
     * ۲۳۶ مدل دست‌کم یک درز با بیش از ۲۵٪ اختلاف طول داشتند و بدترینشان ۹۲٪.
     *
     * پس نوار به نسبت طولِ سرِ مقابلِ هر رابطه بریده می‌شود و تکه‌ها به ترتیبِ
     * جایی که روی بدن می‌نشینند پخش می‌شوند — همان کاری که خیاط با نشانه‌گذاری
     * کمربند می‌کند.
     *
     * @param  array<int, array{left: array, right: array, relation: array}>  $resolved
     * @return array<int, array{left: array, right: array, relation: array}>
     */
    protected function share(array $resolved): array
    {
        /*
         * سرِ *گیرنده* اول بریده می‌شود، بعد سرِ دهنده.
         *
         * ترتیب مهم است، چون هر پاس سهم‌ها را از طولِ سرِ مقابل می‌گیرد. حلقهٔ
         * آستینِ دوتکه این را نشان داد: پنلِ پهلوی جلوی کت رسمی ۱۱٫۷ سانتی‌متر
         * حلقه دارد که ۵٫۸ از آن سهمِ آستینِ زیر است و تنها ۵٫۹ می‌ماند برای
         * سرآستینِ رو. با بریدنِ سرآستین *پیش از* پنل، پاس اول همان ۱۱٫۷ کامل
         * را می‌دید و ۹٫۵ سانتی‌متر سرآستین رویش می‌گذاشت — یعنی زیرِ بغل زیادی
         * و روی سرشانه ۳٫۲ سانتی‌متر کم. همان کمبود، سرِ آستین را از سرشانه
         * جدا نگه می‌داشت و در عکس هر دو سرشانه لخت بود (۱۰۴۳ و ۲۹۰ پیکسل).
         */
        foreach (['right', 'left'] as $side) {
            $other = $side === 'left' ? 'right' : 'left';
            $users = [];

            foreach ($resolved as $index => $entry) {
                $key = implode('+', array_map(
                    fn (array $arc) => $arc['piece'].'|'.$arc['from'].'|'.$arc['to'],
                    $entry[$side],
                ));

                $users[$key][] = $index;
            }

            foreach ($users as $indexes) {
                if (count($indexes) < 2) {
                    continue;
                }

                $band = $resolved[$indexes[0]][$side];

                if (count($band) === 1) {
                    $split = $this->shareAlong($resolved, $indexes, $side, $other, $band[0]);

                    if ($split !== null) {
                        $resolved = $split;

                        continue;
                    }
                }

                // ترتیب روی بدن، نه ترتیب فهرست؛ وگرنه تکه‌ی کمر جلو به پشت می‌رود
                usort($indexes, fn (int $a, int $b) => $this->arcAnchor($resolved[$a][$other])
                    <=> $this->arcAnchor($resolved[$b][$other]));

                // هر کمانِ این سمت به همان نسبت‌ها بریده می‌شود؛ کمربندِ دوتکه
                // هم دو کمان دارد و هر دو باید میان همان رابطه‌ها پخش شوند
                foreach ($band as $position => $arc) {
                    /*
                     * نمونهٔ آینه‌شده کمان را از سرِ دیگر می‌پیماید.
                     *
                     * splitArc از `from` راه می‌افتد و تکه‌ها را به ترتیبِ
                     * راه‌رفتن می‌دهد. آینه‌کردن جهتِ پیمایش را وارون می‌کند، پس
                     * تکه‌ای که روی بازوی چپ سرِ شانه بود روی بازوی راست
                     * زیرِ بغل درمی‌آید. کت این را نشان داد: پنلِ بالای آستین
                     * روی یک بازو به سرشانه دوخته می‌شد و روی آن یکی به زیربغل،
                     * و آستین ده سانتی‌متر از شانه سُر می‌خورد. اندازه گرفته شد:
                     * همبستگیِ ارتفاعِ دو سرِ درز روی نمونهٔ اول ‎+۰٫۹۹ بود و روی
                     * آینه‌اش ‎−۰٫۹۸ — یعنی وارونه دوخته شده بود.
                     *
                     * پس ترتیبِ رابطه‌ها برای همین کمان وارون می‌شود، نه ترتیبِ
                     * فهرست. این حدس نیست: آینه‌کردن *همیشه* جهت را برمی‌گرداند.
                     */
                    $order = ($arc['instance']['mirrored'] ?? false) ? array_reverse($indexes) : $indexes;
                    $shares = array_map(
                        fn (int $index) => max(0.01, array_sum(array_column($resolved[$index][$other], 'length'))),
                        $order,
                    );
                    $total = array_sum($shares);
                    $pieces = $this->splitArc($arc, array_map(
                        fn (float $share) => $share / $total,
                        $shares,
                    ));

                    if (count($pieces) !== count($order)) {
                        continue;
                    }

                    foreach ($order as $at => $index) {
                        $resolved[$index][$side][$position] = $pieces[$at];
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * بریدنِ یک کمانِ مشترک به ترتیبی که خودِ کمان دارد، نه به ترتیبِ زاویهٔ بدن.
     *
     * خط یقهٔ یقهٔ پیراهن این را لازم کرد. کمانِ یقه از نوکِ چپ می‌رود، از مرکز
     * پشت می‌گذرد و به نوکِ راست می‌رسد؛ پس تکهٔ «پشت» وسطِ کمان است، نه یک
     * سرش. با مرتب‌کردن رابطه‌ها بر پایهٔ میانگینِ زاویهٔ سرِ مقابل، میانگینِ دو
     * تنهٔ جلو صفر درمی‌آید — مرکزِ جلو — و کمان به [جلو][پشت] بریده می‌شود.
     * نتیجه: ۲۲٫۵ سانتی‌متر یقه روی خط یقهٔ ۱۹٫۲ سانتی‌متریِ پشت.
     *
     * پس جای هر سرِ مقابل روی خودِ کمان پیدا می‌شود: نزدیک‌ترین رأسِ کمان به آن،
     * و فاصله‌اش از سرِ کمان. آن وقت بریدن به ترتیبِ راه رفتنِ روی کمان است و
     * تکه‌های یک رابطه لازم نیست پشت‌سرِ هم باشند.
     *
     * @param  array<int, array{left: array, right: array, relation: array}>  $resolved
     * @param  array<int, int>  $indexes
     * @return array<int, array{left: array, right: array, relation: array}>|null
     */
    protected function shareAlong(array $resolved, array $indexes, string $side, string $other, array $arc): ?array
    {
        $targets = [];

        foreach ($indexes as $index) {
            foreach ($resolved[$index][$other] as $position => $partner) {
                $targets[] = [
                    'index' => $index,
                    'position' => $position,
                    'along' => $this->along($arc, $partner),
                    'length' => max(0.01, (float) $partner['length']),
                ];
            }
        }

        if (count($targets) < 2) {
            return null;
        }

        usort($targets, fn (array $a, array $b) => $a['along'] <=> $b['along']);

        $total = array_sum(array_column($targets, 'length'));
        $parts = $this->splitArc($arc, array_map(
            fn (array $target) => $target['length'] / $total,
            $targets,
        ));

        if (count($parts) !== count($targets)) {
            return null; // کمان جای بریدن نداشت؛ دست‌نخورده بماند
        }

        $byRelation = [];

        foreach ($targets as $order => $target) {
            $byRelation[$target['index']][$target['position']] = $parts[$order];
        }

        foreach ($byRelation as $index => $list) {
            ksort($list);
            $resolved[$index][$side] = array_values($list);
        }

        return $resolved;
    }

    /**
     * جای یک کمانِ روبه‌رو روی این کمان: فاصلهٔ نزدیک‌ترین رأس از سرِ کمان.
     *
     * @return float سانتی‌متر از سرِ کمان
     */
    protected function along(array $arc, array $partner): float
    {
        $polygon = $arc['instance']['polygon'];
        $count = count($polygon);
        $same = $arc['frame'] === $partner['frame'];
        $best = INF;
        $at = 0.0;
        $walked = 0.0;
        $index = $arc['from'];

        $first = $this->onBody($arc['instance'], $polygon[$arc['from']]);
        $last = $this->onBody($arc['instance'], $polygon[$arc['to']]);

        for ($step = 0; $step < $count; $step++) {
            $here = $this->distance($this->onBody($arc['instance'], $polygon[$index]), $partner['at'], $same);

            if ($here < $best) {
                $best = $here;
                $at = $walked;
            }

            if ($index === $arc['to']) {
                break;
            }

            $next = ($index + 1) % $count;
            $walked += Geometry::distance($polygon[$index], $polygon[$next]);
            $index = $next;
        }

        /*
         * سرِ مقابلی که *بیرونِ* بازهٔ کمان می‌افتد، همان‌سو ادامه می‌یابد.
         *
         * دو قطعه از قاب‌های متفاوت (آستین و تنه) فقط با ارتفاع سنجیده می‌شوند،
         * و اگر هر دو سرِ مقابل بالاتر از بلندترین رأسِ کمان بنشینند، هر دو به
         * همان یک رأس نزدیک‌ترند و `along`شان برابر می‌شود؛ آن‌وقت ترتیبشان را
         * usort تعیین می‌کند، نه هندسه. اندازه گرفته شد روی پیراهنِ کلاسیک: سرِ
         * آستینِ چپ از نوکِ کپ [پشت ۱۲٫۸، یوک ۶٫۷] بریده می‌شد و راست [یوک
         * ۷٫۲، پشت ۱۲٫۳] — روی یکی یوک کنارِ زیربغل دوخته می‌شد و همان شانه
         * لخت می‌ماند (راست ۴۶ از ۲۱۶ نقطه، چپ ۱۴).
         *
         * پس بیرونِ بازه، جا برون‌یابی می‌شود: هرچه سرِ مقابل از سرِ بالاییِ کمان
         * بالاتر باشد، همان‌قدر پیش از آن سر می‌نشیند، و برای سرِ پایینی برعکس.
         * ترتیب همیشه از هندسه می‌آید.
         */
        if (! $same) {
            $height = (float) ($partner['at']['y'] ?? 0.0);
            $top = max($first['y'], $last['y']);
            $bottom = min($first['y'], $last['y']);
            $topAtStart = $first['y'] >= $last['y'];

            if ($height > $top) {
                $at = $topAtStart ? -($height - $top) : $walked + ($height - $top);
            } elseif ($height < $bottom) {
                $at = $topAtStart ? $walked + ($bottom - $height) : -($bottom - $height);
            }
        }

        return $at;
    }

    /** جای یک سرِ رابطه روی بدن، برای مرتب کردن تکه‌های یک کمانِ مشترک. */
    protected function arcAnchor(array $arcs): float
    {
        if ($arcs === []) {
            return 0.0;
        }

        return array_sum(array_map(fn (array $arc) => (float) ($arc['at']['u'] ?? 0), $arcs)) / count($arcs);
    }

    /**
     * هم‌شمار کردن دو سر یک درز با شکستن کمانِ بلند.
     *
     * خط کمر لباس غلافی نمونهٔ روشنش است: بالاتنهٔ پشت دو نیمه است و دامنِ پشت
     * یک قطعهٔ کامل، پس یک کمانِ ۴۴ سانتی‌متری باید به دو کمانِ ۲۲ سانتی‌متری
     * برسد. با جفت‌سازی یک‌به‌یک، یکی از دو نیمه بی‌دوخت می‌ماند و روی مانکن از
     * تن آویزان می‌شود — همان چیزی که در نخستین نمای سه‌بعدی دیده شد.
     *
     * فقط حالت «یک در برابر چند» شکسته می‌شود. اگر هر دو سر چند کمان داشته
     * باشند و شمارشان یکی نباشد، دست نمی‌زنیم و همان‌طور که هست گزارش می‌شود؛
     * حدس زدن در آن حالت یعنی درزی که وجود ندارد.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    protected function balance(array $left, array $right): array
    {
        if (count($left) === count($right)) {
            return [$left, $right, false];
        }

        /*
         * تکه‌ها به ترتیبِ *جای روی کمان* بریده و جفت می‌شوند، نه به ترتیبِ فهرست.
         *
         * تا امروز کمانِ تنها به نسبتِ طولِ کمان‌های مقابل و به ترتیبِ فهرستشان
         * بریده می‌شد و بعد pairArcs هر تکه را با نزدیک‌ترین کمان (به فاصلهٔ
         * میانه‌ها) جفت می‌کرد. برای سرِ آستین این دو با هم غلط درمی‌آمد: کمانِ
         * پشتِ کپ از نوکِ کپ تا زیربغل می‌رود و باید اول یوک (بالا) و بعد پشت
         * (پایین‌تر) بگیرد؛ ولی فهرست [پشت، یوک] بود و میانهٔ تکهٔ بالایی به
         * میانهٔ کمانِ پشت نزدیک‌تر بود تا یوک. روی آستینِ چپِ پیراهنِ کلاسیک یوک
         * سرِ زیربغل دوخته می‌شد و همان شانه لخت می‌ماند؛ آستینِ راست چون از
         * سرِ دیگر پیموده می‌شود درست درمی‌آمد. ترتیب باید از هندسهٔ خودِ کمان
         * بیاید (along) و جفت‌کردن هم به همان ترتیب، تکه به تکه.
         */
        if (count($left) === 1 && count($right) > 1) {
            $right = $this->alongOrder($left[0], $right);
            $pieces = $this->splitArc($left[0], $this->shares($right));

            return [$pieces, $right, count($pieces) === count($right)];
        }

        if (count($right) === 1 && count($left) > 1) {
            $left = $this->alongOrder($right[0], $left);
            $pieces = $this->splitArc($right[0], $this->shares($left));

            return [$left, $pieces, count($pieces) === count($left)];
        }

        return [$left, $right, false];
    }

    /**
     * کمان‌های مقابل به ترتیبِ جایشان روی این کمان، از `from` تا `to`.
     *
     * @param  array<int, array>  $partners
     * @return array<int, array>
     */
    protected function alongOrder(array $arc, array $partners): array
    {
        $keyed = array_map(fn (array $partner) => ['along' => $this->along($arc, $partner), 'arc' => $partner], $partners);

        usort($keyed, fn (array $a, array $b) => $a['along'] <=> $b['along']);

        return array_column($keyed, 'arc');
    }

    /** سهم هر کمان از طول کل، برای شکستن کمان روبه‌رو به همان نسبت‌ها. */
    protected function shares(array $arcs): array
    {
        $total = array_sum(array_map(fn (array $arc) => (float) $arc['length'], $arcs));

        if ($total < 0.01) {
            return array_fill(0, count($arcs), 1 / max(1, count($arcs)));
        }

        return array_map(fn (array $arc) => (float) $arc['length'] / $total, $arcs);
    }

    /**
     * شکستن یک کمان به چند کمانِ پشت‌سرهم، به نسبت‌های خواسته‌شده.
     *
     * برش روی نزدیک‌ترین رأس انجام می‌شود، نه وسط یک پاره‌خط؛ قرارداد بسته
     * می‌گوید دو سر هر درز باید به رأس واقعی اشاره کنند.
     *
     * @param  array<int, float>  $shares
     * @return array<int, array<string, mixed>>
     */
    protected function splitArc(array $arc, array $shares): array
    {
        $polygon = $arc['instance']['polygon'];
        $count = count($polygon);
        $total = DrapeGeometry::arcLength($polygon, $arc['from'], $arc['to']);

        if ($total < 0.1 || $count < 3) {
            return [$arc];
        }

        /*
         * نمونهٔ آینه‌شده از سرِ دیگر راه می‌رود، تا برش روی *همان* رأس‌ها بیفتد.
         *
         * برش به نزدیک‌ترین رأس می‌چسبد و همین چسبیدن جهت‌دار است: کمانِ حلقهٔ
         * آستینِ چپ از سرشانه به زیربغل پیموده می‌شود و آینه‌اش از زیربغل به
         * سرشانه، پس هر کدام به رأسِ خودش گرد می‌شود. اندازه گرفته شد روی
         * پیراهنِ کلاسیک: سرِ آستینِ ۱۹٫۵ سانتی‌متری روی یک بازو ۱۲٫۸۰/۶٫۷۱ بریده
         * می‌شد و روی بازوی دیگر ۱۲٫۳۰/۷٫۲۱ — نیم سانتی‌متر، یعنی درست یک
         * پاره‌خطِ چندضلعی — با آن‌که خودِ دو چندضلعی تا ۰٫۰۰۱ سانتی‌متر آینهٔ
         * هم بودند. همان نیم سانتی‌متر یک شانهٔ پیراهن را لخت می‌گذاشت
         * (شانهٔ راست ۴۶ از ۲۱۶ نقطه لخت، چپ ۱۴).
         *
         * آینه‌کردن ترتیبِ رأس‌ها را وارون می‌کند، پس نمونهٔ آینه‌شده اگر از
         * `to` رو به عقب راه برود، رأس‌ها را به همان ترتیبِ نمونهٔ اصلی می‌بیند
         * و به همان‌ها گرد می‌شود.
         */
        $backwards = (bool) ($arc['instance']['mirrored'] ?? false);
        $start = $backwards ? $arc['to'] : $arc['from'];
        $finish = $backwards ? $arc['from'] : $arc['to'];
        $ordered = $backwards ? array_reverse($shares) : $shares;

        // مرزهای برش را روی رأس‌ها پیدا کن
        $cuts = [$start];
        $target = 0.0;
        $walked = 0.0;
        $index = $start;
        $wanted = array_slice($ordered, 0, count($ordered) - 1);

        foreach ($wanted as $share) {
            $target += $share * $total;

            while ($index !== $finish) {
                $next = $backwards ? ($index - 1 + $count) % $count : ($index + 1) % $count;
                $step = Geometry::distance($polygon[$index], $polygon[$next]);

                // نزدیک‌ترین رأس، نه رأسِ پیش از هدف: با گردکردن به پایین، برشِ
                // خط یقه یک رأسِ کامل عقب می‌افتاد و ۲٫۶ سانتی‌متر جابه‌جا می‌شد
                if ($walked + ($step / 2) > $target) {
                    break;
                }

                $walked += $step;
                $index = $next;
            }

            $cuts[] = $index;
        }

        $cuts[] = $finish;

        if ($backwards) {
            $cuts = array_reverse($cuts);
        }

        $pieces = [];

        for ($i = 0; $i < count($cuts) - 1; $i++) {
            [$from, $to] = [$cuts[$i], $cuts[$i + 1]];

            if ($from === $to) {
                return [$arc]; // برش بی‌معنا شد؛ کمان دست‌نخورده می‌ماند
            }

            $pieces[] = array_merge($arc, [
                'from' => $from,
                'to' => $to,
                'length' => DrapeGeometry::arcLength($polygon, $from, $to),
                'at' => $this->onBody($arc['instance'], DrapeGeometry::arcMidpoint($polygon, $from, $to)),
            ]);
        }

        return $pieces;
    }

    /**
     * جفت‌کردن کمان‌های دو سر یک رابطه.
     *
     * ملاک نزدیکی روی بدن است، نه شماره نمونه: کمانی که روی پهلوی چپ نشسته با
     * کمان پهلوی چپ جفت می‌شود. برای قطعه‌های دست و پا که هر دو نمونه‌شان روی یک
     * بازه زاویه‌ای می‌نشینند، سمت بدن حرف آخر را می‌زند.
     *
     * @return array{matched: array<int, array{0: array, 1: array}>, left: array, right: array}
     */
    protected function pairArcs(array $left, array $right, bool $self = false): array
    {
        $costs = [];

        foreach ($left as $i => $a) {
            foreach ($right as $j => $b) {
                /*
                 * رابطه‌ای که دو سرش یک قطعه است (درزِ فاق): دو فهرست یکی‌اند؛
                 * نمونه به خودش دوخته نمی‌شود و هر جفت یک بار می‌آید (i<j).
                 */
                if ($self && ($i >= $j || $a['piece'] === $b['piece'])) {
                    continue;
                }

                $costs[] = [$this->cost($a, $b), $i, $j];
            }
        }

        usort($costs, fn (array $a, array $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        $matched = [];
        $usedLeft = [];
        $usedRight = [];
        /*
         * و دو نمونهٔ یک قطعه باید به دو نمونهٔ یک قطعه برسند.
         *
         * پشتِ رویه و پهلوی پشت هم‌ارتفاع‌اند و هزینه‌شان برای حلقهٔ آستین
         * یکسان درمی‌آید؛ آن وقت انتخاب به ترتیبِ فهرست می‌افتد و دو بازو دو
         * جور دوخته می‌شوند. روی کت‌وشلوار: آستینِ چپ به «پهلوی پشت» و آستینِ
         * راست دو بار به «پشت» — و آستین ۸٫۵ سانتی‌متر از شانه سُر می‌خورد.
         *
         * پس هر جفتی که با انتخابِ نمونهٔ دیگرِ همان قطعه نمی‌خواند، عقب
         * می‌افتد تا اول جفتِ هم‌خوان امتحان شود. اگر هیچ هم‌خوانی نبود، همان
         * عقب‌افتاده برمی‌گردد — بی‌دوخت ماندن بدتر است.
         */
        $twin = [];
        $deferred = [];

        $take = function (int $i, int $j) use (&$matched, &$usedLeft, &$usedRight, &$twin, $left, $right): void {
            $usedLeft[$i] = true;
            $usedRight[$j] = true;
            $twin[$this->codeOf($left[$i]['piece'])] = $this->codeOf($right[$j]['piece']);
            $matched[] = [$left[$i], $right[$j]];
        };

        foreach ($costs as [, $i, $j]) {
            if (isset($usedLeft[$i]) || isset($usedRight[$j])) {
                continue;
            }

            $wanted = $twin[$this->codeOf($left[$i]['piece'])] ?? null;

            if ($wanted !== null && $this->codeOf($right[$j]['piece']) !== $wanted) {
                $deferred[] = [$i, $j];

                continue;
            }

            $take($i, $j);
        }

        foreach ($deferred as [$i, $j]) {
            if (isset($usedLeft[$i]) || isset($usedRight[$j])) {
                continue;
            }

            $take($i, $j);
        }

        return [
            'matched' => $matched,
            'left' => array_values(array_diff_key($left, $usedLeft)),
            'right' => array_values(array_diff_key($right, $usedRight)),
        ];
    }

    /**
     * دستگاهی که زاویه یک قطعه در آن معنا دارد.
     *
     * زاویهٔ دورِ آستین دور بازو می‌چرخد و زاویهٔ تنه دور تن؛ مقایسه مستقیم این دو
     * بی‌معناست، پس برای درزی که میان دو دستگاه است فقط ارتفاع سنجیده می‌شود.
     */
    protected function frame(string $role): string
    {
        return match ($role) {
            'sleeve' => 'arm',
            'leg' => 'limb',
            default => 'body',
        };
    }

    /** هزینه جفت‌شدن دو کمان: فاصله‌شان روی بدن، به‌اضافه جریمه سمت و لایه مخالف. */
    protected function cost(array $a, array $b): float
    {
        $cost = $this->distance($a['at'], $b['at'], $a['frame'] === $b['frame']);

        if ($a['body_side'] !== null && $b['body_side'] !== null && $a['body_side'] !== $b['body_side']) {
            $cost += static::SIDE_PENALTY;
        }

        /*
         * و رویه به رویه دوخته می‌شود، آستر به آستر.
         *
         * آسترِ پشت و پشتِ رویه هم‌شکل و هم‌ارتفاع‌اند، پس هزینه‌شان برای حلقهٔ
         * آستین دقیقاً یکی درمی‌آمد و انتخاب به ترتیبِ فهرست می‌افتاد. روی
         * کت‌وشلوار همین شد: آستینِ راست به پشتِ رویه دوخته شد و آستینِ چپ به
         * *آسترِ* پشت — دو بازو دو جور، و آستین ۸٫۵ سانتی‌متر از شانه سُر خورد.
         * خیاط این را اشتباه نمی‌کند؛ آستر جدا دوخته می‌شود و بعد داخل می‌رود.
         */
        if ($this->layerOf($a) !== $this->layerOf($b)) {
            $cost += static::LAYER_PENALTY;
        }

        return round($cost, 4);
    }

    /** کدِ قطعه، بی شمارهٔ نمونه: «blazer-front#1» → «blazer-front». */
    protected function codeOf(string $id): string
    {
        $at = strrpos($id, '#');

        return $at === false ? $id : substr($id, 0, $at);
    }

    /** لایهٔ یک کمان: رویه، آستر، لایی. */
    protected function layerOf(array $arc): string
    {
        return (string) ($arc['instance']['payload']['layer'] ?? $arc['instance']['layer'] ?? 'outer');
    }

    /**
     * یک درز از دو کمان جفت‌شده.
     *
     * @return array<string, mixed>
     */
    protected function seam(array $a, array $b, string $label, ?int $relation, array $source): array
    {
        return [
            'a' => ['piece' => $a['piece'], 'from' => $a['from'], 'to' => $a['to'], 'length' => round($a['length'], 3)],
            'b' => ['piece' => $b['piece'], 'from' => $b['from'], 'to' => $b['to'], 'length' => round($b['length'], 3)],
            'label' => $label,
            'reverse' => $this->reverse($a, $b, $source),
            'ease' => round($b['length'] - $a['length'], 3),
            'kind' => 'seam',
            'relation' => $relation,
        ];
    }

    /**
     * آیا سمت b باید وارونه پیموده شود؟
     *
     * حدس نمی‌زنیم: چهار سرِ دو کمان روی بدن گذاشته می‌شوند و هر دو حالت سنجیده
     * می‌شود. اگر دو حالت به‌اندازه هم خوب بودند (قطعه‌های هم‌جا)، همان چیزی که
     * سازنده رابطه گفته بود می‌ماند.
     */
    protected function reverse(array $a, array $b, array $source): bool
    {
        $same = $a['frame'] === $b['frame'];
        $aStart = $this->onBody($a['instance'], $a['instance']['polygon'][$a['from']]);
        $aEnd = $this->onBody($a['instance'], $a['instance']['polygon'][$a['to']]);
        $bStart = $this->onBody($b['instance'], $b['instance']['polygon'][$b['from']]);
        $bEnd = $this->onBody($b['instance'], $b['instance']['polygon'][$b['to']]);

        $straight = $this->distance($aStart, $bStart, $same) + $this->distance($aEnd, $bEnd, $same);
        $flipped = $this->distance($aStart, $bEnd, $same) + $this->distance($aEnd, $bStart, $same);

        // اگر دو حالت به‌اندازه هم خوب بودند، جواب هندسه نویز است. آن وقت همان
        // چیزی می‌ماند که سازنده رابطه گفته؛ و اگر او هم چیزی نگفته باشد، قاعده
        // کلیِ دوخت: دو قطعه‌ای که هم‌جهت بریده شده‌اند، درزشان را وارونه‌ی هم
        // می‌پیمایند.
        if (abs($straight - $flipped) < ($same ? 0.5 : 2.0)) {
            /*
             * ولی همیشه نویز نیست؛ گاهی آزمون کور است.
             *
             * برای درزی که میان دو دستگاه است فقط ارتفاع سنجیده می‌شود، و اگر
             * دو کمان در ارتفاع هیچ هم‌پوشانی نداشته باشند جمعِ دو فاصله در هر
             * دو ترتیب *دقیقاً* برابر درمی‌آید. حلقهٔ آستینِ کت این را نشان داد:
             * Δ صفرِ کامل، برای هر چهار درزِ پنلِ زیرین.
             *
             * آن‌جا هنوز چیزی برای گفتن هست: جهت. کمانی که از بالا به پایین
             * می‌رود باید به کمانی دوخته شود که آن هم از بالا به پایین می‌رود.
             */
            $slopeA = $aEnd['y'] - $aStart['y'];
            $slopeB = $bEnd['y'] - $bStart['y'];

            if (! $same && abs($slopeA) > 0.5 && abs($slopeB) > 0.5) {
                return ($slopeA > 0) !== ($slopeB > 0);
            }

            return (bool) ($source['reverse'] ?? true);
        }

        return $flipped < $straight;
    }

    /**
     * جای یک نقطه قطعه روی بدن: زاویه دور بدن و ارتفاع از کف.
     *
     * @return array{u: float, y: float}
     */
    protected function onBody(array $instance, array $point): array
    {
        [$minX, $minY, $maxX] = $instance['bounds'];
        $placement = $instance['placement'];
        $width = max(1e-6, $maxX - $minX);
        $ratio = (((float) $point['x']) - $minX) / $width;

        return [
            'u' => $placement['u0'] + (($placement['u1'] - $placement['u0']) * $ratio),
            'y' => $instance['top_cm'] - (((float) $point['y']) - $minY),
        ];
    }

    /** فاصله دو جای روی بدن؛ اختلاف زاویه با شعاع مرجع به سانتی‌متر برمی‌گردد. */
    protected function distance(array $a, array $b, bool $sameFrame = true): float
    {
        $height = abs($a['y'] - $b['y']);

        if (! $sameFrame) {
            return $height;
        }

        $angle = $this->wrap($a['u'] - $b['u']);

        return sqrt((($angle * static::REFERENCE_RADIUS) ** 2) + ($height ** 2));
    }

    /** سمت بدنی که این نمونه روی آن نشسته (اگر روی هر دو سمت باشد، null). */
    protected function bodySide(array $instance): ?string
    {
        if (in_array($instance['role'], ['sleeve', 'leg'], true)) {
            return $instance['mirrored'] ? 'right' : 'left';
        }

        $placement = $instance['placement'] ?? null;

        if ($placement === null) {
            return null;
        }

        $middle = $this->wrap(($placement['u0'] + $placement['u1']) / 2);

        if (abs($middle) < 1e-6 || abs(abs($middle) - M_PI) < 1e-6) {
            return null;
        }

        return $middle < 0 ? 'left' : 'right';
    }

    /** بردن یک زاویه به بازه (-π, π]. */
    protected function wrap(float $angle): float
    {
        while ($angle > M_PI) {
            $angle -= 2 * M_PI;
        }

        while ($angle <= -M_PI) {
            $angle += 2 * M_PI;
        }

        return $angle;
    }

    /**
     * گزارش رابطه‌ای که جفت نشد.
     *
     * @return array<string, mixed>
     */
    protected function unmatched(array $relation, int $index, string $reason): array
    {
        return [
            'relation' => $index,
            'label' => (string) ($relation['label'] ?? 'درز'),
            'from' => $relation['from'] ?? null,
            'to' => $relation['to'] ?? null,
            'reason' => $reason,
        ];
    }

    /* ---------------------------------------------------------------------
     |  بودجه مثلث‌بندی
     * ------------------------------------------------------------------- */

    /**
     * طول یال هدف طوری انتخاب می‌شود که مجموع رأس‌ها زیر سقف بماند.
     *
     * @return array{target_edge: float, max_vertices: int}
     */
    protected function budget(array $instances): array
    {
        $area = 0.0;

        foreach ($instances as $instance) {
            $area += abs(Geometry::signedArea($instance['polygon']));
        }

        // مثلث متساوی‌الاضلاع با یال e مساحتی نزدیک ۰٫۴۳ e² دارد و هر رأس میان
        // شش مثلث شریک است؛ پس تعداد رأس تقریباً area / (0.87 e²) است.
        $target = $area > 0
            ? sqrt($area / (0.87 * max(1, static::MAX_VERTICES * 0.7)))
            : static::TARGET_EDGE;

        return [
            'target_edge' => round(max(1.2, min(9.0, $target)), 2),
            'max_vertices' => static::MAX_VERTICES,
        ];
    }
}
