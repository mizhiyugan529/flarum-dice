<?php

namespace annonny\Dice;

use Flarum\Api\Serializer\PostSerializer;
use Flarum\Extend;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->css(__DIR__ . '/less/forum.less')
        ->js(__DIR__ . '/js/dist/forum.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Event())
        ->listen(Saving::class, function (Saving $event) {
            $attributes = Arr::get($event->data, 'attributes', []);
//            print_r($event->post);

            if (!Arr::exists($attributes, 'content')) {
                return;
            }

            /**
             * @var $settings SettingsRepositoryInterface
             */
            $settings = resolve(SettingsRepositoryInterface::class);

            $rolls = [];

//            if ($event->post->dice_rolls_20 ) {
//                $rolls = explode(" ", ($event->post->dice_rolls));
//            }

            // We don't actually care about permission to edit post content
            // If the user isn't allowed or the content is invalid, the save will fail anyway
            // and the values generated here will never be persisted to the database
            // Negative lookahead is necessary to match multiple emojis immediately following each other
            // Allow die in block quotes, because it's just too difficult to exclude this in the HTML parsing in the frontend
            $pattern = '~(?<=\[dice])(?P<firstDice>\d?d\d+|\d+)(?P<addOne>\+|\-)?(?P<secDice>\d?d\d+|\d+)?(?=\[/dice])~i';
            preg_match_all('~(?<=\[dice])(?P<firstDice>\d?d\d+|\d+)((?P<ifAddOne>\+|-)(?P<secondDice>\d?d\d+|\d+))?((?P<ifAddTwo>\+|-)(?P<thirdDice>\d?d\d+|\d+))?(?=\[/dice])~i', Arr::get($attributes, 'content'), $matches);

            $numberOfRolls = count($matches[0]);

            // We only generate additional numbers
            // Even if some of the dice have been edited out, we keep the value in case they are added back in
//            $rolls_dice_text = [];
//            for ($i = count($rolls); $i < $numberOfRolls; $i++) {
//                $roll_dice_text = $matches[0][$i];
//                $dice1 = $matches["firstDice"][$i];
//                $rolls_dice_text[] = $matches[0][$i];
//            }
            $seednum = 0;
            try {
                $seednum = $event->post->created_at->timestamp;
            } catch (Throwable $e) {
                $seednum = random_int(1, 9999999999);
            }
            $seednum = 1000 * $seednum;
            try {
                for ($i = count($rolls); $i < $numberOfRolls; $i++) {
                    $rolls_dice_text = [];
                    if ($matches["firstDice"][$i] != null) {
                        $rolls_dice_text[] = $matches["firstDice"][$i];
                    }
                    if ($matches["secondDice"][$i] != null) {
                        $rolls_dice_text[] = $matches["secondDice"][$i];
                    }
                    if ($matches["thirdDice"][$i] != null) {
                        $rolls_dice_text[] = $matches["thirdDice"][$i];
                    }
                    $rolls_dice_num = 0;
                    if ($matches["secondDice"][$i] != null) {
                        $rolls_dice_result = "" . $matches[0][$i] . "=";
                    } else {
                        $rolls_dice_result = "" . $matches[0][$i];
                    }
                    $result_final = 0;
                    foreach ($rolls_dice_text as $key => $value) {
                        $rolls_dice_num = $rolls_dice_num + 1;
                        //开始对每个进行处理
                        $result = 0;
                        if ($rolls_dice_num == 1) {
                            $result_final = $result;
                        } elseif ($rolls_dice_num == 2) {
                            if ($matches["ifAddOne"][$i] != null) {
                                if ($matches["ifAddOne"][$i] == "-") {
                                    $rolls_dice_result = $rolls_dice_result . "-";
                                } else {
                                    $rolls_dice_result = $rolls_dice_result . "+";
                                }
                            }
                        } elseif ($rolls_dice_num == 3) {
                            if ($matches["ifAddTwo"][$i] != null) {
                                if ($matches["ifAddTwo"][$i] == "-") {
                                    $rolls_dice_result = $rolls_dice_result . "-";
                                } else {
                                    $rolls_dice_result = $rolls_dice_result . "+";
                                }
                            }
                        }
                        if ($value != null) {
                            if (is_numeric($value)) {
                                if ($matches["ifAddOne"][$i] != null) {
                                    $rolls_dice_result = $rolls_dice_result . $value;
                                }
                                $result = $value;
                            } else {
                                $regex2 = '~(?P<d1>\d?)d(?P<d2>\d+)~i';
                                preg_match($regex2, $value, $matches2);
                                if ($matches2["d1"] == null) {
                                    $matches2["d1"] = 1;
                                }
                                for ($add = 0; $add < $matches2["d1"]; $add++) {
                                    srand($seednum+$i*100+$rolls_dice_num*10+$add);
                                    $result_temp = rand(1, $matches2["d2"]);
                                    if ($matches2["d1"] > 1) {
                                        if ($add == 0) {
                                            if ($matches["secondDice"][$i] == null) {
                                                $rolls_dice_result = "" . $matches[0][$i]. "=";
                                            }
                                            $rolls_dice_result = $rolls_dice_result . "(" . (string)$result_temp;
                                        } else {
                                            $rolls_dice_result = $rolls_dice_result . "+" . (string)$result_temp;
                                        }
                                    } else {
                                        if ($matches["ifAddOne"][$i] != null) {
                                            $rolls_dice_result = $rolls_dice_result . "(" . $result_temp . ")";
                                        }
                                    }
                                    $result += $result_temp;
                                }
                                if ($matches2["d1"] > 1) {
                                    $rolls_dice_result = $rolls_dice_result . ")";
                                }
                            }
                            if ($rolls_dice_num == 1) {
                                $result_final = $result;
                            } elseif ($rolls_dice_num == 2) {
                                if ($matches["ifAddOne"][$i] != null) {
                                    if ($matches["ifAddOne"][$i] == "-") {
                                        $result_final -= $result;
                                    } else {
                                        $result_final += $result;
                                    }
                                }
                            } elseif ($rolls_dice_num == 3) {
                                if ($matches["ifAddTwo"][$i] != null) {
                                    if ($matches["ifAddTwo"][$i] == "-") {
                                        $result_final -= $result;
                                    } else {
                                        $result_final += $result;
                                    }
                                }
                            }

                        }


                    }
//                print_r($rolls_dice_result . "=" . (string)$result_final);
                    $rolls[] = $rolls_dice_result . "=" . (string)$result_final;
                }
            } catch (Throwable $e) {
                $rolls = [];
            }


            // The rolls are saved as characters in a single string
            $event->post->dice_rolls_20 = implode(' ', $rolls);
//            $event->post->dice_rolls = "3";
//            $event->post->dice_rolls = $rolls[0];

        }),

    (new Extend\ApiSerializer(PostSerializer::class))
        ->attributes(function (PostSerializer $serializer, Post $post): array {
            if ($post->dice_rolls_20) {
                return [
                    'diceRolls20' => $post->dice_rolls_20,
                ];
            }

            return [];
        }),
];
