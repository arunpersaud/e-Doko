/* some code to highlight the current trick and to switch between different tricks */
/* which trick is currently highlighted*/
var current=0;

/* do the higlighting */
function hl(num) {
    var i;
    for(i=0;i<14;i++){	$("#trick"+i).hide(); $("#tricks"+i).removeClass('active'); }
    $("#trick"+num).css('display', 'block');
    $("#tricks"+num).addClass('active');
    current=num;

    if(document.getElementById("tricks0"))
	min=0;
    else
	min=1;

    if(document.getElementById("tricks13"))
	max=13;
    else
	max=12;

    if(current==min)
	$("#prevtr").addClass('disabled');
    else
	$("#prevtr").removeClass('disabled');
    if(current==max)
	$("#nexttr").addClass('disabled');
    else
	$("#nexttr").removeClass('disabled');

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
	    $(this).parent().children(".gamessession").hide(300);
	    $(this).parent().children(".gamesshowsession").show();
	    $(this).hide();
	});

	$(".gamesshowsession").click( function () {
	    $(this).parent().children(".gamessession").show(300);
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

	$(".message div div").parent().click ( function() { $(this).hide(); });

    });
