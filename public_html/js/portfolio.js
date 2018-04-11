$(function() {

    var itemSelector = '.item';

    var $container = $('.portfolio-box').isotope({
        itemSelector: itemSelector,
        percentPosition: true,
        masonry: {
            columnWidth: itemSelector
        }
    });

    //Ascending order
    var responsiveIsotope = [
        [760, 3],
        [990, 6]
    ];

    var itemsPerPageDefault = 9;
    var itemsPerPage = defineItemsPerPage();
    var currentNumberPages = 1;
    var currentPage = 1;
    var currentFilter = '*';
    var filterAtribute = 'data-filter';
    var pageAtribute = 'data-page';
    var pagerClass = 'isotope-pager';

    function changeFilter(selector) {
        $container.isotope({
            filter: selector
        });
    }


    function goToPage(n) {
        currentPage = n;

        $('.isotope-pager .pager[data-page].current').removeClass('current');
        $('.isotope-pager .pager[data-page='+ currentPage +']').addClass('current');

        var pages = [];

        for (var i = 1; i <= currentPage; i++) {
            var selector = itemSelector;
            selector += ( currentFilter != '*' ) ? '.'+currentFilter : '';
            selector += '['+pageAtribute+'="'+i+'"]';
            pages.push(selector);
        }

        changeFilter(pages.join(','));
    }

    function defineItemsPerPage() {
        var pages = itemsPerPageDefault;

        for( var i = 0; i < responsiveIsotope.length; i++ ) {
            if( $(window).width() <= responsiveIsotope[i][0] ) {
                pages = responsiveIsotope[i][1];
                break;
            }
        }

        return pages;
    }

    function setPagination() {

        var SettingsPagesOnItems = function(){

            var itemsLength = $container.children(itemSelector).length;

            var pages = Math.ceil(itemsLength / itemsPerPage);
            var item = 1;
            var page = 1;
            var selector = itemSelector;
            selector += ( currentFilter != '*' ) ? '.' + currentFilter : '';

            $container.children(selector).each(function(){
                if( item > itemsPerPage ) {
                    page++;
                    item = 1;
                }
                $(this).attr(pageAtribute, page);
                item++;
            });

            currentNumberPages = page;

        }();

        var CreatePagers = function() {

            var $isotopePager = ( $('.'+pagerClass).length == 0 ) ? $('<div class="text-center '+pagerClass+'"></div>') : $('.'+pagerClass);

            $isotopePager.html('');

            for( var i = 0; i < currentNumberPages; i++ ) {
                var $pager = $('<a class="pager btn btn-outline-secondary btn-md btn-arrow m-t-20" href="javascript:void(0)" '+pageAtribute+'="'+(i+1)+'"> <span>Load More <i class="ti-arrow-right"></i></span></a>');

                $pager.click(function(){
                    var page = $(this).eq(0).attr(pageAtribute);
                    goToPage(page);
                });

                $pager.appendTo($isotopePager);
            }

            $container.after($isotopePager);

        }();

    }

    setPagination();
    goToPage(1);

    //Adicionando Event de Click para as categorias
    $('.filterby span, .filterby a').click(function(){
        var t = $(this);
        var filter = t.attr(filterAtribute);
        currentFilter = filter;

        if (t.hasClass('label')) {
            t.data('class', t.attr('class'));

            var inverse = $('.filterby .label-inverse');
            var classAttr = inverse.data('class');

            inverse.removeAttr('class').attr('class', classAttr);
            t.attr('class', 'label label-inverse');
        } else {
            $('.filterby .active').removeClass('active');
            t.addClass('active');
        }

        setPagination();
        goToPage(1);
    });

    //Evento Responsivo
    $(window).resize(function(){
        itemsPerPage = defineItemsPerPage();
        setPagination();
        goToPage(1);
    });
});

function toggleFilter(btn) {
    btn.toggleClass('active');
    $('.filter-row').slideToggle();
}