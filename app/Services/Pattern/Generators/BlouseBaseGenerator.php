<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایهٔ شومیز — و همان قرارِ ترکیبی که کاتالوگ را از انفجار نگه می‌دارد.
 *
 * فهرستِ «انواع شومیز» چند محورِ جداست که در هم ضرب می‌شوند، نه یک فهرستِ تخت:
 *
 *   مدلِ پایه   کلاسیک، اورسایز، راپ، پپلوم، کیمونو، کراپ، …  (قطعه‌ها عوض می‌شوند)
 *   یقه         مردانه، ایستاده، خشتی، شالی، کرواتی، پاپیونی، آبشاری، …
 *   خطِ یقه     گرد، هفت، قایقی، مربعی، دلبری، هالتر، آف‌شولدر، U، حلزونی
 *   آستین       کوتاه، بلند، سه‌ربع، پفی، زنگوله‌ای، فانوسی، خفاشی، رگلان، …
 *   فرم         جذب، معمولی، راحت، اورسایز، جعبه‌ای
 *   جزئیات      چین، پیلی، رافل، کمربند، جیب، پشت‌باز، دکمهٔ مخفی، …
 *   کاربرد      روزمره، اداری، مجلسی، محجبه، بارداری، شیردهی، …
 *
 * اگر هر ترکیب یک «نوعِ الگو» شود، چند هزار ردیفِ بی‌نظم می‌شود. پس تنها محورِ
 * *اول* ژنراتورِ ثبت‌شده است — چون قطعه‌های الگو را عوض می‌کند — و شش محورِ دیگر
 * پارامترند. یک شومیزِ کلاسیک با یقهٔ مردانه و آستینِ پفی و فرمِ معمولی همان
 * ژنراتورِ کلاسیک است با سه پارامترِ متفاوت، نه یک مدلِ تازه.
 *
 * کاربرد (مجلسی، اداری، محجبه) هیچ هندسه‌ای عوض نمی‌کند و فقط برچسب است؛ ولی
 * چون کاربر با آن جست‌وجو می‌کند، در meta ثبت می‌شود.
 */
abstract class BlouseBaseGenerator extends ShirtBaseGenerator
{
    /** خطِ یقه: گودی و پهنای اضافه، و اینکه لبه‌اش منحنی است یا صاف. */
    protected const NECKLINES = [
        'round' => ['گرد', 0.0, 0.0],
        'scoop' => ['گرد و باز', 2.0, 4.0],
        'v' => ['هفت', 1.0, 7.0],
        'deep_v' => ['هفت گود', 1.5, 12.0],
        'square' => ['مربعی (خشتی)', 3.0, 5.0],
        'boat' => ['قایقی', 6.5, -1.0],
        'sweetheart' => ['دلبری', 2.5, 5.0],
        'u' => ['U', 2.0, 8.0],
        'keyhole' => ['حلزونی (سوراخ‌کلیدی)', 0.5, 1.0],
        'halter' => ['هالتر', -2.0, 9.0],
        'off_shoulder' => ['آف‌شولدر', 9.0, 3.0],
        'one_shoulder' => ['یک‌طرفه', 5.0, 6.0],
    ];

    /** آستین: بلندی پیش‌فرض و شکلِ سرآستین. */
    protected const SLEEVES = [
        'none' => ['بدون آستین (حلقه با نوار)', 0],
        'cap' => ['حلقه‌ای کوتاه (Cap)', 8],
        'short' => ['کوتاه', 20],
        'elbow' => ['تا آرنج', 34],
        'three_quarter' => ['سه‌ربع', 44],
        'long' => ['بلند تا مچ', 58],
        'puff' => ['پفی', 22],
        'bell' => ['زنگوله‌ای', 52],
        'bishop' => ['بیشاپ (مچ‌دار)', 58],
        'lantern' => ['فانوسی', 30],
        'flutter' => ['کلوش کوتاه (Flutter)', 16],
        'balloon' => ['بالونی', 50],
        'petal' => ['گلبرگی', 18],
        'butterfly' => ['پروانه‌ای', 24],
        'split' => ['چاک‌دار', 56],
        'raglan' => ['رگلان', 58],
        'kimono' => ['کیمونو (یک‌سره با تنه)', 30],
        'batwing' => ['خفاشی', 46],
    ];

    /** کاربرد — فقط برچسب، بی اثر روی هندسه. */
    protected const USES = [
        'daily' => 'روزمره',
        'office' => 'اداری و محل کار',
        'party' => 'مهمانی و مجلسی',
        'summer' => 'تابستانی',
        'winter' => 'زمستانی',
        'beach' => 'ساحلی',
        'modest' => 'محجبه',
        'maternity' => 'بارداری',
        'nursing' => 'شیردهی',
        'uniform' => 'یونیفرم',
    ];

    /**
     * شناسنامهٔ مدلِ پایه: همان چیزهایی که *قطعه* را عوض می‌کنند.
     *
     * @return array<string, mixed>
     */
    abstract protected function blouse(): array;

    /**
     * نامِ مدل، از همان شناسنامه.
     *
     * مدل‌های قدیمی‌تر خودشان label دارند و همان می‌ماند؛ مدلِ تازه فقط title
     * می‌نویسد و دیگر لازم نیست یک متد برای یک رشته بنویسد.
     */
    public function label(): string
    {
        return (string) ($this->blouse()['title'] ?? 'شومیز');
    }

    public static function group(): string
    {
        return 'shirt';
    }

    public function paramsSchema(): array
    {
        $own = $this->blouse();

        return $this->shirtSchema(
            array_merge([
                'fit' => $own['fit'] ?? 'regular',
                'sleeve_length' => (float) (static::SLEEVES[$own['sleeve'] ?? 'long'][1] ?? 58),
                'body_length' => (float) ($own['body_length'] ?? 16),
                'armhole_depth_extra' => (float) ($own['armhole'] ?? 3),
            ], $own['defaults'] ?? []),
            array_merge(
                [
                    'neckline' => [
                        'label' => 'خط یقه', 'type' => 'select',
                        'default' => $own['neckline'] ?? 'round',
                        'options' => array_map(fn (array $row) => $row[0], static::NECKLINES),
                        'hint' => 'شکلِ خودِ خطِ یقه؛ جدا از اینکه رویش یقه دوخته شود یا نه.',
                    ],
                    'collar' => [
                        'label' => 'یقه', 'type' => 'select',
                        'default' => $own['collar'] ?? 'shirt',
                        'options' => [
                            'none' => 'بدون یقه (نوار اریب)',
                            'shirt' => 'یقه مردانه (پیراهنی)',
                            'stand' => 'یقه ایستاده',
                            'peter_pan' => 'یقه خشتی (پیتر‌پن)',
                            'shawl' => 'یقه شالی',
                            'tie' => 'یقه کرواتی',
                            'bow' => 'یقه پاپیونی',
                            'ruffle' => 'یقه چین‌دار',
                            'cascade' => 'یقه آبشاری',
                        ],
                    ],
                    'collar_height' => [
                        'label' => 'بلندی یقه', 'min' => 3, 'max' => 12, 'step' => 0.5,
                        'default' => (float) ($own['collar_height'] ?? 7), 'unit' => 'سانتی‌متر',
                    ],
                    'sleeve_style' => [
                        'label' => 'مدل آستین', 'type' => 'select',
                        'default' => $own['sleeve'] ?? 'long',
                        'options' => array_map(fn (array $row) => $row[0], static::SLEEVES),
                    ],
                    'garment_use' => [
                        'label' => 'کاربرد', 'type' => 'select',
                        'default' => $own['use'] ?? 'daily',
                        'options' => static::USES,
                        'hint' => 'هندسه را عوض نمی‌کند؛ برای جست‌وجو و پیشنهادِ پارچه ثبت می‌شود.',
                    ],
                    'front_opening' => [
                        'label' => 'جلوی لباس', 'type' => 'select',
                        'default' => $own['opening'] ?? 'button',
                        'options' => [
                            'button' => 'دکمه‌دار',
                            'hidden' => 'دکمه مخفی (زیر پاتلت)',
                            'closed' => 'بسته (بی‌دکمه، از سر پوشیده می‌شود)',
                            'wrap' => 'چپ‌وراست (Wrap)',
                        ],
                    ],
                    'bust_dart' => [
                        'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle',
                        'default' => (bool) ($own['bust_dart'] ?? true),
                    ],
                    'gathers' => [
                        'label' => 'چین سرشانه یا یوک', 'min' => 0, 'max' => 16, 'step' => 0.5,
                        'default' => (float) ($own['gathers'] ?? 0), 'unit' => 'سانتی‌متر',
                    ],
                    'ruffle' => [
                        'label' => 'رافل لبه جلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                        'default' => (float) ($own['ruffle'] ?? 0), 'unit' => 'سانتی‌متر',
                    ],
                    'tie_belt' => [
                        'label' => 'کمربند پارچه‌ای', 'type' => 'toggle',
                        'default' => (bool) ($own['belt'] ?? false),
                    ],
                    'back_slit' => [
                        'label' => 'چاک یا سوراخ پشت', 'min' => 0, 'max' => 30, 'step' => 1,
                        'default' => (float) ($own['back_slit'] ?? 0), 'unit' => 'سانتی‌متر',
                    ],
                ],
                $this->pocketParam((bool) ($own['pocket'] ?? false)),
                $own['extra'] ?? [],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $own = $this->blouse();
        $params = $this->applyNeckline($params);
        $params = $this->applySleeveStyle($params);
        $params = $this->withDropShoulder($params);
        $ease = $this->shirtEase($ease, $params);
        $g = $this->bodiceMetrics($measurements, $ease, $params);

        $opening = (string) $this->param($params, 'front_opening', $own['opening'] ?? 'button');
        $stand = in_array($opening, ['button', 'hidden'], true)
            ? (float) $this->param($params, 'button_stand', 1.5)
            : ($opening === 'wrap' ? 12.0 : 0.0);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => $own['prefix'],
        ]);

        $pieces = array_merge([$front, $back], $extras);

        foreach ($this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'sleeve_name' => 'آستین '.$own['title'],
        ]) as $sleeve) {
            $pieces[] = $sleeve;
        }

        foreach ($this->blouseCollar($g, $params, $own) as $collar) {
            $pieces[] = $collar;
        }

        foreach ($this->blouseTrims($g, $params, $own) as $trim) {
            $pieces[] = $trim;
        }

        $pieces[0]['meta']['blouse'] = [
            'base' => static::key(),
            'neckline' => (string) $this->param($params, 'neckline', $own['neckline'] ?? 'round'),
            'collar' => (string) $this->param($params, 'collar', $own['collar'] ?? 'shirt'),
            'sleeve' => (string) $this->param($params, 'sleeve_style', $own['sleeve'] ?? 'long'),
            'fit' => (string) $this->param($params, 'fit', $own['fit'] ?? 'regular'),
            'use' => (string) $this->param($params, 'garment_use', $own['use'] ?? 'daily'),
        ];

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            $own['notes'] ?? [],
            ['کاربرد: '.(static::USES[$pieces[0]['meta']['blouse']['use']] ?? 'روزمره').'.'],
        );

        return $this->finish($pieces);
    }

    /**
     * خطِ یقه را به گودی و پهنای خودش ترجمه می‌کند.
     *
     * خطِ یقه شکلِ *سوراخِ گردن* است، نه قطعهٔ یقه: هفت یعنی گودترِ جلو، قایقی
     * یعنی پهن‌تر و کم‌گودتر، آف‌شولدر یعنی هر دو. پس به‌جای یک شاخهٔ هندسیِ
     * جدا، همان دو پارامترِ موجودِ پایه را جابه‌جا می‌کند.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function applyNeckline(array $params): array
    {
        $key = (string) $this->param($params, 'neckline', 'round');
        $row = static::NECKLINES[$key] ?? null;

        if ($row === null) {
            return $params;
        }

        $params['neck_width_extra'] = (float) $this->param($params, 'neck_width_extra', 0) + $row[1];
        $params['front_neck_depth_extra'] = (float) $this->param($params, 'front_neck_depth_extra', 0) + $row[2];

        if (in_array($key, ['boat', 'off_shoulder'], true)) {
            // یقهٔ پهن پشت را هم باز می‌کند، وگرنه لباس روی گردن می‌ماند
            $params['back_neck_depth'] = (float) $this->param($params, 'back_neck_depth', 2) + 2;
        }

        return $params;
    }

    /**
     * مدلِ آستین را به بلندی و شکلِ سرآستین ترجمه می‌کند.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function applySleeveStyle(array $params): array
    {
        $key = (string) $this->param($params, 'sleeve_style', 'long');
        $row = static::SLEEVES[$key] ?? null;

        if ($row === null) {
            return $params;
        }

        // اگر کاربر خودش بلندی داده، همان می‌ماند؛ وگرنه بلندیِ همان مدل
        if (! array_key_exists('sleeve_length', $params)) {
            $params['sleeve_length'] = (float) $row[1];
        }

        if ($key === 'none') {
            $params['sleeve_length'] = 0.0;
        }

        return $params;
    }

    /**
     * قطعهٔ یقه، بر پایهٔ محورِ «یقه».
     *
     * یقهٔ مردانه و ایستاده و خشتی هر سه *قطعه* دارند و از سازنده‌های خودِ پایهٔ
     * پیراهن می‌آیند. کرواتی و پاپیونی و چین‌دار و آبشاری نوارند و در
     * blouseTrims ساخته می‌شوند. «بدون یقه» تنها نوارِ اریب می‌خواهد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function blouseCollar(array $g, array $params, array $own): array
    {
        $style = (string) $this->param($params, 'collar', $own['collar'] ?? 'shirt');
        $height = (float) $this->param($params, 'collar_height', $own['collar_height'] ?? 7);
        $half = ($g['front_neck_length'] ?? 0) + ($g['back_neck_length'] ?? 0);

        if ($half < 1) {
            $half = max(8.0, ($g['neck_width'] ?? 8) * 2);
        }

        return match ($style) {
            'shirt' => [$this->shirtCollar($half, $height, $own['prefix'].'-collar', 'یقه شومیز')],
            'stand' => [$this->bandCollar($half, min($height, 5.0))],
            'peter_pan' => [$this->campCollar($half, max(5.0, $height), 0.5)],
            'shawl' => [$this->campCollar($half, max(7.0, $height), 6.0)],
            default => [],
        };
    }

    /**
     * نوارِ مستطیلیِ ساده — کمربند، رافل، نوارِ یقه.
     *
     * پایهٔ پیراهن bandPiece ندارد (آن روی شاخهٔ بالاتنه است)، پس همین‌جا
     * کوچک‌ترین نسخه‌اش نوشته می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function blouseBand(string $code, string $name, float $length, float $height, array $o = []): array
    {
        $length = max(4.0, $length);
        $height = max(1.0, $height);

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => (int) ($o['cut'] ?? 1),
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, $height),
                Geometry::point(0, $height),
            ],
            'grainline' => $this->bandGrainline($length, $height),
            'meta' => array_merge([
                'part' => $o['part'] ?? 'trim',
                'edges' => ['default', 'side', 'default', 'side'],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'trim',
            ], $o['meta'] ?? []),
        ]);
    }

    /**
     * قطعه‌های تزیینیِ محورِ «جزئیات»: رافل، کمربند، نوارِ یقه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function blouseTrims(array $g, array $params, array $own): array
    {
        $out = [];
        $ruffle = (float) $this->param($params, 'ruffle', 0);
        $belt = (bool) $this->param($params, 'tie_belt', false);
        $collar = (string) $this->param($params, 'collar', 'shirt');
        $length = (float) $this->param($params, 'body_length', 16);

        if ($ruffle > 0.5) {
            $out[] = $this->blouseBand(
                $own['prefix'].'-ruffle',
                'رافل لبه جلو',
                ($g['front_waist_y'] + $length) * 2.2,
                $ruffle,
                [
                    'cut' => 2, 'part' => 'trim',
                    'meta' => [
                        'notes' => [
                            'دو برابرِ لبه بریده می‌شود تا چین بخورد؛ لبهٔ بیرونی‌اش نواردوزی می‌شود.',
                        ],
                    ],
                ],
            );
        }

        if ($belt) {
            $out[] = $this->blouseBand($own['prefix'].'-belt', 'کمربند پارچه‌ای', 190, 8, [
                'cut' => 1, 'part' => 'belt',
                'meta' => ['girth_role' => 'trim', 'notes' => ['دو سرش باریک می‌شود و جلو گره می‌خورد.']],
            ]);
        }

        if (in_array($collar, ['tie', 'bow'], true)) {
            $out[] = $this->blouseBand(
                $own['prefix'].'-'.$collar,
                $collar === 'tie' ? 'نوار کراواتی یقه' : 'نوار پاپیونی یقه',
                $collar === 'tie' ? 150 : 120,
                $collar === 'tie' ? 9 : 12,
                [
                    'cut' => 2, 'part' => 'collar',
                    'meta' => [
                        'interfacing' => $collar === 'bow',
                        'girth_role' => 'trim',
                        'notes' => ['به خط یقه دوخته می‌شود و دو سرش جلو گره می‌خورد.'],
                    ],
                ],
            );
        }

        if ($collar === 'ruffle' || $collar === 'cascade') {
            $out[] = $this->blouseBand(
                $own['prefix'].'-neck-frill',
                $collar === 'ruffle' ? 'چین دور یقه' : 'آبشار جلوی یقه',
                $collar === 'ruffle' ? 120 : 90,
                $collar === 'ruffle' ? 6 : 14,
                [
                    'cut' => 1, 'part' => 'trim',
                    'meta' => [
                        'bias' => $collar === 'cascade',
                        'girth_role' => 'trim',
                        'notes' => [
                            $collar === 'cascade'
                                ? 'روی اریب بریده می‌شود تا خودش آبشاری بیفتد؛ چین نمی‌خورد.'
                                : 'دو برابرِ خط یقه بریده و روی آن چین می‌شود.',
                        ],
                    ],
                ],
            );
        }

        return $out;
    }
}
