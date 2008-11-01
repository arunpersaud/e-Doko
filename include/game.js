/* some code to highlight the current trick and to switch between different tricks */
/* which trick is currently highlighted*/
var current=0;

/* do the higlighting */
function hl(num) {
    if(document.getElementById){
	var i;
	for(i=0;i<14;i++){
	    if(document.getElementById("trick"+i))
		document.getElementById("trick"+i).style.display = 'none';
	}
	document.getElementById("trick"+num).style.display = 'block';
	current=num;
    }
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
