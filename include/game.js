/* some code to highlight the current trick and to switch between different tricks */
/* which trick is currently highlighted*/
var current=0;

/* do the higlighting */
function hl(num) {
    var i;
    for(i=0;i<14;i++){	$("#trick"+i).hide(); }
    $("#trick"+num).css('display', 'block');
    current=num;
}

/* highlight the last trick, useful when a page is called the first time*/
function high_last(){
    if(document.getElementById){
	var i;
	for(i=13;i>=0;i--) {
	    if(document.getElementById("trick"+i))
		{
		    hl(i);
		    current=i;
		    break;
		}
	}
    }
}

/* highlight the next trick */
function hl_next()
{
    if(document.getElementById("trick"+(current+1)))
	hl(current+1);
}

/* highlight the previous trick */
function hl_prev()
{
    if(document.getElementById("trick"+(current-1)))
	hl(current-1);
}

$(document).ready(
    function()
    {
	$("#ScoreTable").tablesorter({ widgets: ['zebra']});

	$(".gameshidesession").click( function () {
	    $(this).parenthesis().children(".gamessession").hide(300);
	    $(this).parent().children(".gamesshowsession").show();
	    $(this).hide();
	});

	$(".gamesshowsession").click( function () {
	    $(this).parenthesis().children(".gamessession").show(300);
	    $(this).parent().children(".gameshidesession").show();
	    $(this).hide();
	});

	$(".gameshowall").click( function () {
	    $(".gamessession").show(300);
	    $(".gamesshowsession").hide();
	    $(".gameshidesession").show();
	});
	$(".gamehideall").click( function () {
	    $(".gamessession").hide(300);
	    $(".gamesshowsession").show();
	    $(".gameshidesession").hide();
	});

	$("ul.loginregister").click(function () {
	    $(".dologin").slideToggle();
	    $(".doregister").slideToggle();
	});

	$(".message div div").parent().click ( function() { $(this).hide(); });

    });
