<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Портфолио Никитина Павла Викторовича" />
    <meta name="keywords" content="портфолио, программист" />
    <?if(!empty($noindex)){?>
        <meta name="robots" content="noindex"/>
        <meta name="robots" content="nofollow"/>
    <?}?>
    <title><?=$title?></title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href='http://fonts.googleapis.com/css?family=Jura&subset=latin,cyrillic-ext' rel='stylesheet' type='text/css'>
    <link href="/css/style.css" rel="stylesheet">

    <script src="http://yandex.st/jquery/2.0.3/jquery.min.js"></script>
    <script src="/js/scrollTo.js"></script>
    <script src="/js/site.js"></script>

    <!--[if lt IE 9]>
    <style>
        .bad_browser{display:block;}
        .page, footer{display:none;}
    </style>
    <![endif]-->

</head>

<body>
<div class="page">
    <header>
        <table>
            <tr>
                <td><span class="pv_nav"><a href="/" class="just_link">projects</a></span></td>
                <td><a href="/" class="to_main">Pavel Nikitin</a></td>
                <td><a href="/about">about</a></td>
            </tr>
        </table>
    </header>

    <?=$content?>

    <?if(isset($total)){?>
        <input type="hidden" name="total" value="<?=$total?>">
        <div class="show_more">
            <table>
                <tr>
                    <td><div>show&nbsp;</div><span>9</span> of <?=$total?></td>
                    <td><a href="#" class="s_more">show more</a></td>
                    <td><a href="#" class="s_all">show all</a></td>
                <tr>
            </table>
        </div>
    <?}?>
</div>

<footer>
    <table>
        <tr>
            <td><a href="http://vk.com/paShaman" target="_blank">vk</a> <a href="https://www.facebook.com/paShamanZ" target="_blank">fb</a> <a href="https://twitter.com/paShamanZ" target="_blank">tw</a></td>
            <td class="totop"><a href="#">&uarr;</a></td>
            <td><div class="pru">paShaman.ru&nbsp;</div>&copy; <?=date('Y')?></td>
        </tr>
    </table>
</footer>

<!--[if lt IE 9]>
<div class="bad_browser">
    <h1>Ваш браузер устарел</h1>
    Установите современный браузер <a href="http://www.google.com/chrome/">Chrome</a>, <a href="http://www.opera.com">Opera</a>, <a href="http://www.firefox.com">Firefox</a>.
</div>
<![endif]-->

</body>
</html>
