$("table").addClass("table table-striped");
$("ul.tricks").wrap('<div class="container text-center">');
$("ul.tricks").addClass('pagination');

$(".navbar .nav").addClass("pull-right");

// user page
$(".user ul").addClass("list-group");
$(".user ul li").addClass("list-group-item");

$(".user .gamestatuspre").addClass("btn btn-warning");
$(".user .gamestatusplay").addClass("btn btn-success");
$(".user .gamestatusover").addClass("btn btn-info");

// enable tabs on login page
$('a[data-toggle="tab"]').on('shown', function (e) {
  e.target // activated tab
  e.relatedTarget // previous tab
})

$('ul.loginregister').wrap('<div class="container text-center">');
$('.tab-content').addClass('container');

$('.login input.submitbutton').addClass('btn btn-primary');
$('.login input[type="submit"]').addClass('btn');

$('.login form label').addClass("form-label col-xs-4");

$(".welcomestats").addClass("container");

$(".gravatar").addClass('img-polaroid');

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
