
window.addEventListener('load', function(){

  this.rTip = {};
  this.rTip.e = document.createElement('div');
  this.rTip.e.id = 'rTip';

  var ref = document.getElementsByClassName('ref')[0],
      tags = document.getElementsByClassName('rHasTip');

  for(var i in tags){

    tags[i].onmouseover = function(){ 
      rTip.e.innerHTML = this.getElementsByTagName('q')[0].innerHTML;
      rTip.e.className = 'ref visible';
    };

    tags[i].onmouseout = function(){        
      rTip.e.innerHTML = '';
      rTip.e.className = '';
    };
  
  }

  document.onmousemove = function(evt){
    if(rTip.e.className.indexOf('visible') < 0)
      return;
    rTip.e.style.top = ((document.documentElement.clientHeight - event.clientY) < rTip.e.offsetHeight + 20 ? (event.pageY - rTip.e.offsetHeight) : event.pageY) + 'px';
    rTip.e.style.left = ((document.documentElement.clientWidth - event.clientX) < rTip.e.offsetWidth + 20 ? (event.pageX - rTip.e.offsetWidth) : event.pageX) + 'px';
  };

  ref.parentNode.insertBefore(this.rTip.e, ref.nextSibling);
});      
