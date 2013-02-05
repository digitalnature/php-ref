
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
    rTip.e.style.left = evt.pageX + 'px';
    rTip.e.style.top = evt.pageY + 'px';
  };

  ref.parentNode.insertBefore(this.rTip.e, ref.nextSibling);
});      
