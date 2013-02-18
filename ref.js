window.addEventListener('load', function(){

  var tip  = document.createElement('div'),      
      refs = document.querySelectorAll('.ref'),
      nfos = document.querySelectorAll('.ref .rHasTip');

  for(var i = 0, max = refs.length; i < max; i++)
    refs[i].onmousemove = function(e){
      if(tip.className.indexOf('visible') < 0)
        return;
      tip.style.top = ((document.documentElement.clientHeight - e.clientY) < tip.offsetHeight + 20 ? (e.pageY - tip.offsetHeight) : e.pageY) + 'px';
      tip.style.left = ((document.documentElement.clientWidth - e.clientX) < tip.offsetWidth + 20 ? (e.pageX - tip.offsetWidth) : e.pageX) + 'px';
    };

  for(var i = 0, max = nfos.length; i < max; i++){
    nfos[i].onmouseover = function(){ 
      tip.className = 'ref visible';      
      tip.innerHTML = this.getElementsByTagName('q')[0].innerHTML;
      clearTimeout(tip.fadeOut);
    };
    nfos[i].onmouseout = function(){
      tip.className = 'ref visible fadingOut';
      tip.fadeOut = setTimeout(function(){
        tip.innerHTML = '';
        tip.className = '';
      }, 250);
    
    };  
  }

  tip.id = 'rTip';
  document.body.appendChild(tip);
});
