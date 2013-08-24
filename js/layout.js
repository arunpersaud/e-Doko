$("table").addClass("table table-striped");
$("ul.tricks").wrap('<div class="pagination">');

$(".navigation").addClass("navbar-inner");
$(".navbar-inner").wrap('<div class="navbar navbar-fixed-top">');
$(".navbar .nav").addClass("pull-right");
$(".navbar p").addClass("navbar-text");


// enable tabs on login page
$('a[data-toggle="tab"]').on('shown', function (e) {
  e.target // activated tab
  e.relatedTarget // previous tab
})

$('div.login').addClass("row-fluid");
$('div.login ul').addClass("offset3 span6");
$('div.login .tab-content').addClass("offset3 span6");

$('.login input.submitbutton').addClass('btn btn-primary');
$('.login input[type="submit"]').addClass('btn');

$(".doregister div").addClass('control-group');
$(".doregister label").wrap('<div class="control-label">');
$(".doregister input").wrap('<div class="controls">');
$(".doregister select").wrap('<div class="controls">');

$(".gravatar").addClass('img-polaroid');

$(".welcomestats").addClass('offset1');

// about
$('div.about').addClass('row-fluid');
$('div.code').addClass('offset2 span2');
$('div.database').addClass('span2');
$('div.graphics').addClass('span2');
$('div.translation').addClass('span2');

// favicon as indicator if it's your turn
document.head || (document.head = document.getElementsByTagName('head')[0]);

function checkFavicon() {
    /* check if it's your turn */
    url=window.location.href;
    url=url.substring(0, url.lastIndexOf('index.php'))+"testfav.php";

    $.getJSON(url)
	.done(function( json ) {

	    var link = document.createElement('link'),
	    oldLink = document.getElementById('favicon');
	    link.id = 'favicon';
	    link.rel = 'shortcut icon';

	    if(json.turn=="yes")
		link.href = "pics/edoko-favicon-your-turn.png";
	    else
		link.href = "pics/edoko-favicon.png";

	    if (oldLink)
		document.head.removeChild(oldLink);

	    document.head.appendChild(link);
	});
}
checkFavicon();
setInterval(checkFavicon,30000);
