<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ورزشِ تیمی: گشادتر، آستینِ کوتاه، پشتِ توری. */
class ActiveTeamJerseyGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_team_jersey';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_team_jersey',
            'title' => 'پیراهن تیمی',
            'use' => 'team',
            'stretch' => 1.0,
            'length' => 16,
            'back_drop' => 3,
            'sleeve' => 'set_in',
            'sleeve_length' => 24,
            'mesh_back' => true,
            'neck_width_extra' => 2.5,
        ];
    }
}
