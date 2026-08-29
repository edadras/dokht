<?php

namespace App\Services\Pattern\Generators;

/**
 * لایهٔ ترکیبیِ لباس یک‌تکه: سرهمی، اورال، رامپر و بویلرسوت.
 *
 * لباسِ یک‌تکه با لباسِ دوتکه یک تفاوتِ بنیادی دارد و همهٔ سختی‌اش از همان
 * می‌آید: **قدِ تنه دیگر انتخاب نیست، اندازه است.** در پیراهن و شلوار اگر تنه
 * دو سانتی‌متر کوتاه دربیاید کسی نمی‌فهمد؛ در سرهمی همان دو سانتی‌متر روی فاق
 * می‌نشیند و پوشنده نمی‌تواند بایستد.
 *
 * پس این خانواده سه چیز را جدا از هم نگه می‌دارد و هر سه را روی الگو اعلام
 * می‌کند:
 *
 *   ۱. **آزادیِ قدِ بالاتنه** — تنه عمداً بلندتر از اندازهٔ بدن درفت می‌شود.
 *   ۲. **آزادیِ قدِ فاق** — منحنیِ فاق هم جدا باز می‌شود.
 *   ۳. **یک آزادی برای هر دو نیمه** — کمرِ بالاتنه و کمرِ پاچه به هم دوخته
 *      می‌شوند، پس اگر هرکدام آزادیِ خودش را بگیرد آن درز بسته نمی‌شود. برای
 *      همین ساسونِ کمر در این خانواده بسته است: هر سانتی‌متر ساسون، یک لبه را
 *      از لبهٔ روبه‌رویش کوتاه‌تر می‌کند.
 *
 * فرم‌ها: pants (پاچهٔ بلند)، shorts (پاچهٔ کوتاه).
 */
abstract class JumpsuitBaseGenerator extends OnePieceBaseGenerator
{
    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، form (pants|shorts)، fit، grow، sleeve،
     * sleeve_length، opening (zip|button|none)، buttons، collar، collar_height،
     * neck_width، neck_depth، armhole، belt، pocket، knee_ease، hem_ease،
     * short_length، leg_length، rise، crotch، schema، extra، notes.
     *
     * @return array<string, mixed>
     */
    abstract protected function jumpsuit(): array;

    public function label(): string
    {
        return (string) ($this->jumpsuit()['title'] ?? 'لباس یک‌تکه');
    }

    public function paramsSchema(): array
    {
        $j = $this->jumpsuit();
        $short = (string) ($j['form'] ?? 'pants') === 'shorts';

        $schema = array_merge(
            $this->onePieceSchema(array_merge([
                'neck_width_extra' => (float) ($j['neck_width'] ?? 1.0),
                'front_neck_depth_extra' => (float) ($j['neck_depth'] ?? 2.5),
                'armhole_depth_extra' => (float) ($j['armhole'] ?? 2.5),
            ], (array) ($j['schema'] ?? []))),
            $this->riseSchema((float) ($j['rise'] ?? 3.0), (float) ($j['crotch'] ?? 2.5)),
            $short
                ? $this->shortLegSchema((float) ($j['short_length'] ?? 16), (float) ($j['hem_ease'] ?? 8))
                : $this->legSchema([
                    'length_extra' => (float) ($j['leg_length'] ?? 0),
                    'knee_ease' => (float) ($j['knee_ease'] ?? 10),
                    'hem_ease' => (float) ($j['hem_ease'] ?? 14),
                ]),
            $this->sleeveParam(
                (string) ($j['sleeve'] ?? 'none'),
                (float) ($j['sleeve_length'] ?? 20),
                ['none' => 'بدون آستین', 'set_in' => 'آستین (کوتاه یا بلند)'],
            ),
            [
                'belt' => [
                    'label' => 'کمربند پارچه‌ای', 'type' => 'toggle',
                    'default' => (bool) ($j['belt'] ?? false),
                ],
                'pocket' => [
                    'label' => 'جیب رودوزی', 'type' => 'toggle',
                    'default' => (bool) ($j['pocket'] ?? false),
                ],
            ],
        );

        if ((string) ($j['opening'] ?? 'zip') !== 'none') {
            $schema = array_merge($schema, $this->openingParam(
                (string) ($j['opening'] ?? 'zip'),
                (float) ($j['button_stand'] ?? 2.5),
            ));
            $schema['buttons']['default'] = (int) ($j['buttons'] ?? 6);
        }

        if ((string) ($j['collar'] ?? 'none') !== 'none') {
            $schema = array_merge($schema, $this->collarParam(
                (string) $j['collar'],
                ['none' => 'بدون یقه', 'stand' => 'یقه ایستاده', 'turn' => 'یقه برگردان'],
                (float) ($j['collar_height'] ?? 5),
            ));
        }

        return array_merge($schema, (array) ($j['extra'] ?? []));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $j = $this->jumpsuit();
        $prefix = (string) ($j['prefix'] ?? static::key()).'-';
        $short = (string) ($j['form'] ?? 'pants') === 'shorts';

        // آزادیِ قدِ بالاتنه پیش از درفتِ بلوک اعمال می‌شود، و فقط یک بار
        $params = $this->withRise($params);
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]);
        $opening = (string) $this->param($params, 'front_opening', $j['opening'] ?? 'zip');

        $pieces = $this->onePieceBody($measurements, $ease, $params, $g, [
            'prefix' => $prefix,
            'grow' => $grow,
            'short' => $short,
            'panel' => [
                'bust_dart' => (bool) ($j['bust_dart'] ?? true),
            ],
            'front' => $opening === 'closed'
                ? []
                : ['extension' => (float) $this->param($params, 'button_stand', $j['button_stand'] ?? 2.5),
                    'on_fold' => false, 'cut' => 2, 'mirror' => true],
            'sleeve' => [],
        ]);

        $halfNeck = $this->neckOf(array_slice($pieces, 0, 2));

        $pieces = array_merge($pieces, $this->collarSet($g, $halfNeck, $params, [
            'prefix' => $prefix,
            'collar' => (string) $this->param($params, 'collar', $j['collar'] ?? 'none'),
            'collar_height' => (float) $this->param($params, 'collar_height', $j['collar_height'] ?? 5),
        ]));

        if ($this->flag($params, 'pocket', (bool) ($j['pocket'] ?? false))) {
            $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => $prefix]));
        }

        if ($this->flag($params, 'belt', (bool) ($j['belt'] ?? false))) {
            $pieces[] = $this->bandPiece($prefix.'belt', 'کمربند پارچه‌ای', 190, 8, [
                'cut' => 1, 'fold_line' => true, 'part' => 'belt',
                'meta' => ['girth_role' => 'trim', 'notes' => ['دولا دوخته می‌شود؛ پهنای تمام‌شده چهار سانتی‌متر است.']],
            ]);
        }

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['jumpsuit'] = [
                'model' => (string) ($j['prefix'] ?? static::key()),
                'form' => $short ? 'shorts' : 'pants',
            ];
        }

        $notes = array_merge([
            'لبهٔ کمرِ بالاتنه و لبهٔ کمرِ پاچه با یک آزادی درفت شده‌اند و باید بی کش آمدن به هم برسند.',
        ], (array) ($j['notes'] ?? []));

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $this->finishBlock($pieces, $g, $grow, ['shell', 'bottom']);
    }
}
