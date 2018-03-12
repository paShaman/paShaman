<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        \App\Page::create([
            'sort' => 100,
            'text' => '',
            'url' => '/',
            'active' => true,
            'name' => 'works',
        ]);
        \App\Page::create([
            'sort' => 200,
            'text' => '<h1>Программирование - это тоже вид творчества!</h1>
<br>
И я в это верю! Писать программы надо с душой, чтобы тебе было действительно интересно.

<br><br>
Сайтами начал интересоваться ещё со школы. Делал сначала для себя, потом простые сайты для знакомых. И в последствии понял, что это мое, и начал заниматься программированием всерьез.

<br><br>
Профессионально занимаюсь php программированием c 2010. <a href="/projects/docplus/">Работал</a> и в веб-студиях, и в соцсетях, и в интернет-магазинах.
<br><br>

Сейчас тоже работаю, но продолжаю делать сайты со знакомыми дизайнерами. В основном использую CMS Bitrix, но всегда готов к чему-то новому.

<br><br><br><br>
<i>По вопросам сотрудничества обращаться сюда: <a href="http://vk.com/paShaman" target="_blank">http://vk.com/paShaman</a></i>',
            'url' => '/about',
            'active' => true,
            'name' => 'about',
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
