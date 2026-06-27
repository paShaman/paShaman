<?php

use Illuminate\Database\Migrations\Migration;

class AddUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \App\Models\User::create([
            'name' => 'Павел Никитин',
            'site' => 'http://paShaman.ru',
            'nick' => 'paShaman',
        ]);

        \App\Models\User::create([
            'name' => 'Алексей Усольцев',
            'site' => 'http://www.behance.net/alpus',
            'nick' => 'Alpus',
        ]);
        \App\Models\User::create([
            'name' => 'Дмитрий Щавлеев',
            'site' => 'http://mitia.ru',
            'nick' => 'Mitia',
        ]);
        \App\Models\User::create([
            'name' => 'Маша Терешкова',
            'site' => 'http://maws.ru',
            'nick' => 'maws',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
