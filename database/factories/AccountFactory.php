<?php

use Faker\Generator as Faker;

$factory->define(App\Eloquents\Account::class, function (Faker $faker) {
    return [];
});

$factory->define(App\Eloquents\QiitaAccount::class, function (Faker $faker) {
    return [
        'account_id'       => '1',
        'qiita_account_id' => '1'
    ];
});

$factory->define(App\Eloquents\QiitaUserName::class, function (Faker $faker) {
    return [
        'account_id'       => '1',
        'user_name'        => $faker->word
    ];
});


$factory->define(App\Eloquents\AccessToken::class, function (Faker $faker) {
    return [
        'account_id'   => '1',
        'access_token' => $faker->unique()->regexify('[a-z0-9]{64}')
    ];
});

$factory->define(App\Eloquents\LoginSession::class, function (Faker $faker) {
    $expiredOn = new \DateTime();
    $expiredOn->add(new \DateInterval('PT1H'));

    return [
        'id'         => $faker->uuid(),
        'account_id' => '1',
        'expired_on' => $expiredOn
    ];
});
