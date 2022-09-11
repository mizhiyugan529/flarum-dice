<?php

use Flarum\Database\Migration;

return Migration::addColumns('posts', [
    'dice_rolls_20' => ['string', 'length' => 255, 'nullable' => true],
]);
