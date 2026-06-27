<?php

use Illuminate\Database\Migrations\Migration;

class AddPages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \App\Models\Page::create([
            'sort' => 100,
            'text' => '',
            'url' => '/',
            'active' => true,
            'name' => 'portfolio',
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
