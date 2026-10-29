<?php

use App\Models\Plan;
use App\Models\Studio;
use Illuminate\Support\Facades\DB;

it('rejects a negative price, zero classes, zero days or a zero daily limit', function (array $values) {
    $plan = Plan::factory()->create();

    expectSqlState(fn () => DB::table('plans')->where('id', $plan->id)->update($values), '23514');
})->with([
    'price' => [['price' => -1]],
    'credits' => [['credits' => 0]],
    'validity' => [['validity_days' => 0]],
    'daily limit' => [['daily_limit' => 0]],
]);

it('keeps one name per studio among the plans on sale', function () {
    $studio = Studio::factory()->create();
    Plan::factory()->for($studio)->create(['name' => '8 clases']);
    Plan::factory()->for($studio)->archived()->create(['name' => '8 Clases']);

    expectSqlState(fn () => Plan::factory()->for($studio)->create(['name' => '8 CLASES']), '23505');
});
