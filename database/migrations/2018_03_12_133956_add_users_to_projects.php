<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsersToProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (2, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (2, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (3, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (3, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (4, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (4, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (5, 1, 'верстка фильтров, программирование серверной части и клиентской части с использованием google maps api ');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (5, 2, 'дизайн фильтров и иконок на карте');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (6, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (7, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (7, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (8, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (8, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (9, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (10, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (11, 1, 'верстка дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (12, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (12, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (13, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (14, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (14, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (15, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (15, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (16, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (16, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (17, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (17, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (18, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (18, 2, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (19, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (19, 2, 'дизайн, flash');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (20, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (20, 2, 'дизайн, flash');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (21, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (22, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (22, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (23, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (24, 1, 'работа в должности программиста');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (24, 4, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (25, 1, 'верстка, дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (26, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (27, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (27, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (28, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (29, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (30, 1, 'верстка, программирование сайта на фреймворке kohana');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (30, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (31, 1, 'верстка фильтров, программирование серверной части (фреймворк kohana) и клиентской части с использованием google maps api ');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (31, 2, 'дизайн фильтров и иконок на карте');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (32, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (32, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (33, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (34, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (34, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (35, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (35, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (36, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (36, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (37, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (37, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (38, 1, 'верстка, программирование, самописная цмс (Orange Web), дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (39, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (39, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (40, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (40, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (41, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (42, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (42, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (43, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (43, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (44, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (45, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (45, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (46, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (46, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (47, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (47, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (48, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (49, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (49, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (50, 1, 'ветстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (51, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (51, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (52, 1, 'правки');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (53, 1, 'работа в должности программиста');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (54, 1, 'работа в должности программиста');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (55, 1, 'верстка, программирование, дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (56, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (57, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (57, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (58, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (58, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (59, 1, 'верстка, программирование, самописная цмс (Orange Web)');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (59, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (60, 1, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (61, 1, 'верстка, программирование, самописная цмс (Orange Web), дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (62, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (62, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (63, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (63, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (64, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (65, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (65, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (66, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (66, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (67, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (67, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (68, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (68, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (69, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (69, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (20, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (70, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (70, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (71, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (71, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (72, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (72, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (73, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (73, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (74, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (74, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (75, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (75, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (76, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (77, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (77, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (79, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (78, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (80, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (80, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (81, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (82, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (82, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (83, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (83, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (84, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (84, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (85, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (85, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (86, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (86, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (87, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (87, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (88, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (88, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (89, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (89, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (90, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (90, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (91, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (91, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (92, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (92, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (93, 1, 'программирование, внедрение системы оплаты webpay');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (94, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (95, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (95, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (96, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (96, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (97, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (98, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (98, 3, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (99, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (100, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (100, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (101, 1, 'верстка, программирование, дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (102, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (102, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (103, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (104, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (106, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (106, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (107, 1, 'программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (108, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (109, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (110, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (111, 1, 'верстка, программирование');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (111, 2, 'дизайн');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (112, 1, 'верстка');");
DB::insert("INSERT INTO users_to_projects (project_id, user_id, role) VALUES (112, 2, 'дизайн');");

        /*
         * небольшой костыль, так как в прошлой базе был сдвиг по id на единицу
         */
        DB::update('update users_to_projects set project_id = project_id-1');
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
