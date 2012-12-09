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
