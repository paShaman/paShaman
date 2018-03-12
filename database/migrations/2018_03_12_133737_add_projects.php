<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \Illuminate\Support\Facades\DB;

class AddProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('StarKo', 'http://starko-group.ru/', 'Профессиональные IP-камеры высокого разрешения', 'starko', 1, '08/2013', 'верстка php bitrix 2013 alpus mysql jquery parallax less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Mitia', 'http://mitia.ru', '', 'mitia_ver2', 1, '08/2013', 'ver2 верстка bitrix php 2013 mitia mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Edifico', 'http://edifico.ru', 'Архитектурное бюро', 'edifico', 1, '10/2013', 'верстка bitrix php 2013 mitia mysql jquery parallax adaptive less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Локатор Scanoil', 'http://locator.scanoil.ru/', 'Локатор с возможностью фильтрации АЗС по множеству параметров. Построение маршрутов с поиском ближайших АЗС.', 'scanoil_locator', 1, '05/2013', '2013 kohana php google_maps alpus mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('РОСИНВЕСТПРОЕКТ', 'http://www.rosinvestproekt.ru/', '', 'rosinvestproekt', 1, '11/2008', 'верстка 2008 innovamedia');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Майоров Петр Юрьевич', 'http://www.mnotary.ru/', '', 'mnotary', 1, '01/2013', '2013 верстка bitrix php alpus mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Мореходики', 'http://morehodiki.ru/', 'Детсткий клуб', 'morehodiki', 1, '10/2011', '2011 php bitrix верстка alpus mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Casinomika', 'http://casinomika.ru/', 'Интернет-сообщество работников игорного бизнеса', 'casinomika', 1, '04/2009', 'верстка 2009 innovamedia');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Zamer-LKP', 'http://www.zamer-lkp.ru/', 'Изменение толщины лакокрасочных изделий', 'zamer_lkp', 1, '12/2010', '2010 верстка');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('ВСМ строй', 'http://vsm-stroy.ru/', '', 'vsm_stroj', 1, '11/2009', '2009 верстка paShaman');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Траттория Uno', 'http://www.trattoriauno.ru/', '', 'trattoria_uno', 1, '06/2010', '2010 php верстка orange_web alpus mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Топлайн', 'http://www.topline.ru/', '', 'topline', 1, '12/2010', '2010 верстка php bitrix soobwa mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Thiemstone', 'http://thiemstone.com/', '', 'thiemstone', 1, '08/2013', '2013 верстка alpus jquery parallax less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Street Beanz', 'http://www.streetbeanz.ru/', '', 'street_beanz', 1, '10/2010', '2010 верстка php bitrix mitia mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Mitia', 'http://mitia.ru', '', 'mitia', 1, '02/2013', 'верстка 2013 mitia jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('AL Soft Distribution', 'http://www.soft-distribution.com/', '', 'soft_distribution', 1, '02/2012', 'верстка 2012 alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('AL Soft Distribution', 'http://www.soft-distribution.com', '', 'soft_distribution_ver2', 1, '05/2013', '2013 верстка alpus ver2 jquery parallax less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Shoomusic', 'http://shoomusic.ru/', 'Сайт певицы Shoo', 'shoomusic', 1, '10/2011', '2011 верстка php bitrix alpus flash mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Shoomusic', 'http://shoomusic.ru/', 'Сайт певицы Shoo. Добавлено много нового функционала. Из презентационного сайта сделан полноценный сайт музыкальной группы.', 'shoomusic_ver2', 1, '12/2011', '2011 верстка php bitrix alpus flash ver2 mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Центр гигиены и эпидемиологии в городе Москве', 'http://www.sen-uao.ru/', 'Перенос сайта на CMS Bitrix', 'sen_uao', 1, '08/2011', '2011 bitrix php mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Окна Ронин', 'http://www.okna-ronin.ru/', '', 'roninwindows', 1, '02/2012', 'верстка 2012 php bitrix alpus mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Res-Q Group', 'http://res-q.ru/', '', 'resq', 1, '05/2008', 'верстка php orange_web 2008 mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Read.ru', 'http://read.ru', 'Книжный интернет-магазин', 'readru', 1, '03/2012', 'верстка php jquery postresql extjs smarty git memcached 2010 2011 2012 работа maws google_maps');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Favola Porta', '', '', 'favola_porta', 1, '12/2009', '2009 верстка paShaman');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Энергия Сервис', '', '', 'energy_service', 1, '01/2010', '2010 верстка');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Papovskys', '', 'Русский ресторан-бар в Мексике', 'papovskys', 1, '04/2013', '2013 верстка alpus jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Paolo Conte', 'http://www.paoloconte.ru/', '', 'paolo_conte', 1, '03/2010', 'верстка 2010 innovamedia');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('ОВКП', 'http://ovkp.ru/', 'Отопление Вентиляция Кондиционирование Проектирование', 'ovkp', 1, '03/2011', 'php bitrix 2011 soobwa mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Oil System Tehnology', 'http://oilst.ru/', 'Сайт компании-оператора безналичных расчётов за нефтепродукты', 'oilst', 1, '04/2012', '2012 верстка alpus kohana php mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Локатор OilST', 'http://oilst.ru/services/locator/', 'Интерактивная карта АЗС с возможностью фильтрации и построения маршрутов.', 'oilst_locator', 1, '05/2012', '2012 php alpus kohana google_maps mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('ЮГК', 'http://www.ugorcom.ru/', 'Сайт югорских горных компаний', 'ugk', 1, '10/2013', 'верстка php bitrix jquery alpus mysql 2013 less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Московские огни', 'http://www.ogni.ru/', 'Агентство недвижимости', 'ogni', 1, '02/2009', 'верстка bitrix php mysql innovamedia 2009');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('МТС Бонус', 'http://www.bonus.mts.ru/', '', 'mts_bonus', 1, '10/2011', 'верстка mitia jquery 2011');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Современные Информационные Технологии', 'http://modernteh.ru/', '', 'moderntech', 1, '04/2012', '2012 alpus верстка jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Luxtec', 'http://luxtec.ru', '', 'luxtec', 1, '10/2010', '2010 php правки mitia');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Luxtec', 'http://luxtec.ru', '', 'luxtec_ver2', 1, '07/2012', '2012 верстка php bitrix jquery mysql mitia ver2');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Global ST', 'http://global-st.ru/', 'Страхование и туризм', 'global_st', 1, '11/2008', '2008 верстка php orange_web paShaman');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Корона-Сервис', 'http://korona-servis.ru/', 'Клининговая компания', 'korona_servis', 1, '02/2009', '2009 верстка php orange_web alpus mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Конторамода', 'http://www.kontoramoda.ru/', 'Сайт мужских подарков', 'kontoramoda', 1, '11/2011', '2011 верстка php bitrix alpus mysql jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Kite Travel', 'http://kite-travel.ru/', '', 'kite_travel', 1, '09/2010', '2010 верстка soobwa jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Kalinkin Hill', 'http://www.kalinkinhill.com/', '', 'kalinkinhill', 1, '09/2012', '2012 верстка php bitrix jquery mysql mitia less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('IE EnSystems', 'http://www.ie-ensystems.ru/', '', 'ie_ensystems', 1, '05/2012', '2012 верстка php bitrix jquery mysql alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Graffiti Market', 'http://graffitimarket.ru/', '', 'graffiti_market', 1, '01/2011', '2011 верстка php jquery bitrix soobwa mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Дискавери', 'http://www.gostsert.ru/', 'Центр сертификации', 'gostsert', 1, '12/2011', '2011 верстка php bitrix jquery mysql alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Городская жизнь', 'http://gor-life.ru/', 'Недвижимость и консалтинг', 'gor_life', 1, '12/2009', '2009 верстка orange_web php mysql alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Foreignhouse', 'http://foreignhouse.com/', '', 'foreignhouse', 1, '03/2009', '2009 верстка orange_web php mysql alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Espira', 'http://espira.ru/', '', 'espira', 1, '12/2008', '2008 верстка php bitrix mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Edium', 'http://edium.ru/', '', 'edium', 1, '03/2012', '2012 верстка php bitrix mysql alpus jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('EC Electric', 'http://www.ec-electric.ru/', '', 'ec_electric', 1, '09/2010', '2010 верстка php bitrix mysql jquery soobwa');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Denirello', 'http://denirello.com/', '', 'denirello', 1, '02/2013', '2013 верстка alpus less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Consul Cotton', 'http://www.consul-coton.ru/', '', 'consul_cotton', 1, '04/2011', '2011 правки');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Topgamer', 'http://topgamer.ru', 'Социальная сеть для геймеров', 'topgamer', 1, '07/2013', '2013 2012 верстка kohana php nodejs redis extjs mysql svn работа less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Mamsy', 'http://mamsy.ru', 'Клуб распродаж товаров для детей и мам', 'mamsy', 1, '10/2013', '2013 2014 2015 2016 2017 php git mysql memcached jquery zend работа smarty redis');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('paShaman', 'http://paShaman.ru', 'Мой личный сайт - портфолио.', 'pashaman', 1, '11/2013', '2013 верстка paShaman php jquery adaptive mysql less kohana');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('BlackSmith', 'http://www.kovka-stanki.ru/', '', 'blacksmith', 1, '01/2010', '2010 верстка innovamedia');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Бадахшан', 'http://www.badahshan.ru/', 'Региональная общественная организация\r\nветеранов локальных войн и военных конфликтов', 'badahshan', 1, '08/2012', '2012 верстка php jquery bitrix mysql alpus less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('2K Sport', 'http://www.2k-sport.com/', '', '2k_sport', 1, '02/2013', '2013 alpus верстка jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Когнитивные технологии проектирования', 'http://ktp-stankin.msk.ru/', 'Сайт кафедры на самописной цмс. Бакалаврская работа.', 'stankin', 1, '03/2008', '2008 верстка php alpus orange_web mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Leasing', '', '', 'leasing', 1, '01/2008', '2008 paShaman');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Акиба', '', '', 'akiba', 1, '01/2008', '2008 верстка php orange_web mysql paShaman');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Стратег', 'http://strateg.com.ru/', 'Частное охранное предприятие', 'strateg', 1, '11/2013', '2013 верстка php bitrix alpus jquery mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Move Words', '', 'Простая игра на яваскрипте, в которой необходимо перетаскиванием облаков составлять корректные фразы.', 'game_english', 1, '03/2014', '2014 mitia верстка jquery less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Scanoil личный кабинет', '', 'Личный кабинет по управления картами оплаты.', 'scanoil_admin', 1, '04/2014', 'kohana php oracle 2014');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('iOsago', 'http://iOsago.com', 'Бесплатное мобильное приложение для iPhone. Оно позволит быстро рассчитать стоимость и заказать полис ОСАГО.', 'iosago', 1, '11/2012', 'верстка jquery php 2012 soobwa alpus bitrix mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Aquasorium', 'http://aquasorium.ru', 'Интернет-магазин сантехники и аксессуаров для ванных комнат.', 'aquasorium', 1, '06/2014', 'верстка bitrix php 2014 mitia mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Mitia', 'http://mitia.ru', 'Адаптивная версия сайта', 'mitia_ver3', 1, '06/2014', 'ver3 верстка bitrix php 2014 mitia mysql jquery less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Subcon Business Solutions', 'http://subcon.ru', '', 'sbs', 1, '08/2014', 'верстка bitrix php 2014 mitia mysql jquery less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Soobwa', 'http://soobwa.ru', 'Промо страница агентства Сообща.', 'soobwa', 1, '09/2014', 'alpus 2014 верстка soobwa parallax jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Benefit', 'http://benefit-beauty.ru', 'Сайт салона красоты', 'benefit', 1, '11/2014', 'верстка bitrix php 2014 alpus mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Andrey Serdyuchenko', 'http://serdyuchenko.ru', 'Персональный сайт', 'andrey', 1, '11/2014', 'alpus 2014 верстка parallax jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Квартал Дубки', 'http://slavynsky.ru', 'Сайт строительной компании.', 'dubki', 2, '12/2014', '2014 alpus bitrix php jquery less перенос mysql верстка');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Denirello', 'http://denirello.com/', '', 'denirello_ver2', 1, '12/2014', '2014 верстка alpus less jquery parallax ver2 google_maps');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('СК Славянский', 'http://slavynsky.ru', '', 'slavynsky', 2, '02/2015', 'верстка bitrix php 2015 alpus mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('AXAYS личный кабинет', '', 'Платежная система', 'axays', 2, '03/2015', '2015 верстка alpus less jquery adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Twice', 'http://twice-agency.ru/', '', 'twice', 2, '04/2015', '2015 верстка jquery less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Henkel Team', 'http://henkelteam.ru', '', 'henkel', 2, '05/2015', '2015 alpus верстка jquery less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Helen Miller', 'http://helenmillerbridal.com', 'Сайт марки свадебных платьев', 'helenmiller', 2, '06/2015', '2015 верстка jquery less php bitrix');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('CityEngine', 'http://www.city-engine.ru', '', 'cityengine', 1, '06/2015', '2015 верстка jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('MySunFarm', 'http://mysunfarm.ru', '', 'mysunfarm', 1, '07/2015', 'верстка bitrix php 2015 alpus mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('КОМПЛИМЕД', '', '', 'complimed', 2, '08/2015', 'верстка 2015 soobwa jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('AXAYS', 'http://axays.com', '', 'axays_ver2', 2, '08/2015', '2015 верстка alpus less jquery adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Healthy Carrot', 'http://healthycarrot.ru', 'Интернет-магазин индивидуального фитнес питания на каждый день.', 'carrot', 1, '10/2015', 'верстка bitrix php 2015 mitia mysql jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('СКАЛА', '', 'Система управления топливными картами', 'skala', 1, '11/2015', 'верстка php 2015 alpus oracle jquery less kohana 2016 2017 2018 работа');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('InterOil', 'http://interoil51.ru', '', 'interoil', 1, '11/2015', 'верстка php 2015 alpus wordpress jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Klebstoffe', 'http://klebstoffe.ru', '', 'klebstoffe', 1, '12/2015', 'верстка php 2015 mitia bitrix jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Промо-сайт компании СКАЛА', 'http://skala-card.ru/', '', 'skala_card', 1, '12/2015', 'верстка php 2015 alpus wordpress jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Percutaneous Bianchi Systems', 'http://pbsmoscow.ru', '', 'pbs', 1, '01/2016', '2016 alpus верстка php adaptive jquery less');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('CosmoGifts', 'http://cosmo.gift', '', 'cosmogifts', 1, '02/2016', 'alpus wordpress 2016 верстка less jquery php');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('BF Creative Studio', 'http://www.bfcreative.ru', '', 'bfcreative', 1, '02/2016', 'alpus 2016 верстка less jquery adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Душевная Кухня', 'http://soulkitchenmoscow.ru', '', 'soulkitchen', 1, '03/2016', 'alpus 2016 верстка less jquery bitrix php mysql adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('New Opera World', 'http://www.newoperaworld.com', '', 'newoperaworld', 1, '04/2016', 'alpus 2016 верстка less jquery bitrix php mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Coup De Coeur', 'http://www.coupdecoeur.by', 'Кулинарные курсы в Белоруси', 'coupdecoeur', 1, '04/2016', 'php bitrix billing jquery 2016');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Mobi 24', 'http://mobi-24.com', '', 'mobi24', 1, '05/2016', 'bitrix перенос jquery php mysql 2016');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Alyeshka', 'http://alyeshka.ru', '', 'alyeshka', 1, '05/2016', 'alpus 2016 верстка less jquery bitrix php mysql adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('DublDom', 'http://dubldom-place.com', '', 'dubldom', 1, '08/2016', 'alpus 2016 верстка less jquery bitrix php mysql google_maps');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('iWill', 'http://i-will.ru', '', 'iwill', 2, '08/2016', '2016 верстка less jquery bitrix php mysql adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Object 23', 'http://object-23.com', '', 'object23', 1, '09/2016', '2016 верстка less jquery wordpress php mysql adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Дом Рональда Макдональда', 'http://mchappyday2016.rmhc.ru/dreamstories/', '', 'mcfund', 2, '10/2016', '2016 верстка less jquery');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Банк Фридом Финанс', 'http://bankffin.ru', '', 'ffin', 2, '11/2016', '2016 верстка less jquery bitrix php mysql adaptive alpus 2017');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('paShaman', 'http://paShaman.ru', 'На 100-ый проект решил слегка освежить свой сайт.', 'pashaman_ver2', 1, '11/2016', 'less paShaman kohana jquery верстка mysql php adaptive 2016 ver2');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('SINCECLOTHING', 'http://sinceclothing.ru/', '', 'sinceclothing', 1, '12/2016', 'less alpus bitrix jquery верстка mysql php adaptive 2016');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('CityMetria', 'http://citymetria.ru/', '', 'citymetria', 2, '10/2016', 'правки 2016');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('СityMetria', 'http://citymetria.ru/', '', 'citymetria_ver2', 2, '03/2017', '2017 php less правки ver2');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Doc+', 'https://docplus.ru', '', 'docplus', 1, '03/2017', '2017 php работа 2018 angular mysql redis bitrix');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('StarKo', 'http://starko-group.ru/', '', 'starko_ver2', 1, '06/2017', 'верстка php bitrix 2017 alpus mysql jquery less ver2');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('3DENOV.RU', 'http://3denov.ru/', '3D Архитектурная Визуализация', '3denov', 1, '06/2017', '2017 wordpress');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Spirit Fitness', 'http://spiritfit.ru', '', 'spiritfit', 2, '07/2017', 'php 2017 верстка jquery parallax less adaptive');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Comunica', 'http://comunica.ru/', '', 'comunica', 2, '09/2017', 'php 2017 верстка jquery less bitrix adaptive mysql');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('Spirit Fitness', 'http://spiritfit.ru', '', 'spiritfit_ver2', 2, '09/2017', 'php 2017 верстка jquery less adaptive ver2');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('ЮКА', 'http://uka-thebest.ru/', '', 'uka', 1, '10/2017', 'php 2017 верстка jquery less bitrix mysql alpus');");
DB::insert("INSERT INTO projects (name, site, info, link, active, date, tags) VALUES ('JetCRM', 'http://jetcrm.ru/', '', 'jet', 1, '12/2017', 'верстка 2017 alpus jquery parallax less adaptive');");

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
