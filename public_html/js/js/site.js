$(function(){
	$(".totop a").on('click', function(){
		$.scrollTo({top:0,left:0}, 1000);
		return false;
	});
	check_h();
	$(window).scroll(function(){
		check_h();
	});
	
	$(".s_more").on('click', function(e){
		e.preventDefault();
		e.stopPropagation();
		load_projects();
	});
	$(".s_all").on('click', function(e){
		e.preventDefault();
		e.stopPropagation();	
		load_projects(true);
	});
	
	$(window).resize(function(){
		res();
	});
    setTimeout(function(){
        res();
    }, 500);

	$(".filter .p_tags > span").each(function(){
		var s = $(this);
		s.hover(
			function(){
				$(".item").stop().animate({'opacity':0.2}, 500);
				var v = $(this);
				v = v.text().replace(v.find('span').text(), '');
				
				$(".item .tags span").each(function(){
					var el = $(this);
					if(el.text() == v){
						el.closest(".item").stop().animate({'opacity':1}, 500);
					}
				});
			},
			function(){
				$(".item").stop().animate({'opacity':1}, 500);
			}
		);
	});	
	
	if($(".pv").size()){
		$(".pv_nav").append(
			'<a class="pv_prev" href="/projects/'+$("[prev]").attr('prev')+'/">&larr;</a>'+
			'<a class="pv_next" href="/projects/'+$("[next]").attr('next')+'/">&rarr;</a>'
		);
	}

	$('.p_tags_toggle').on('click', function () {
		$('.p_tags_content').toggle();
	});
});

function res(){
	if($(window).width() < 1024 || $(window).height() < $(".filter").height()+120){
		$(".filter").fadeOut();
	}else{
		$(".filter").fadeIn();
	}
}

function check_h(){
	if($(window).width() > 1023){
		var h = $(window).scrollTop();
		$("header").height(210-h > 70 ? 210-h : 70);
		if($("header").height() <= 100){
			$("header").addClass('on-scroll');
		}else{
			$("header").removeClass('on-scroll');
		}
		
		if(h>140)
			$(".filter").css('top', h-140);
		else
			$(".filter").css('top', 0);	
	}
}

function load_projects(show_all){
	
	var total = parseInt($("[name=total]").val());
	
	if(show_all !== true){
		show_all = false;
	}
	
	$.post('/api/load_projects/', {offset:$(".item").size(), all:show_all}, function(data){
		if(data.success){
			$(".show_more span").text(parseInt($(".item").size()) + data.data.length);
			if(parseInt($(".show_more span").text()) == total){
				$(".s_more, .s_all").fadeOut();
			}
			
			for(var i in data.data){
				var tags = '';
				for(var y in data.data[i].tags){
					tags = tags + '<span>'+data.data[i].tags[y]+'</span>';
				}
				var tpl = $('<div class="item"><div><a href="/projects/'+data.data[i].link+'/"><img src="/img/projects/'+data.data[i].link+'/preview.jpg"></a><div class="tags">'+tags+'</div><div class="info"><span><span>'+data.data[i].name+'</span></span><span>'+data.data[i].timestamp+'</span></div></div></div>');
				if(data.data[i].is_active == 2 && data.data[i].name == 'Hidden'){
					tpl.addClass('hidden_item');
					tpl.find('a').attr('href', '#');
				}

				$(".list").append(tpl);
			}
		}
	}, 'json')
}