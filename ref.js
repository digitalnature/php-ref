window.addEventListener('load', function(){

  var tip  = document.createElement('div'),
      refs = document.querySelectorAll('.ref'),
      nfos = document.querySelectorAll('.ref b > q');

  for(var i = 0, max = refs.length; i < max; i++)
    refs[i].onmousemove = function(e){
      if(tip.className.indexOf('visible') < 0)
        return;
      tip.style.top = ((document.documentElement.clientHeight - e.clientY) < tip.offsetHeight + 20 ? (e.pageY - tip.offsetHeight) : e.pageY) + 'px';
      tip.style.left = ((document.documentElement.clientWidth - e.clientX) < tip.offsetWidth + 20 ? (e.pageX - tip.offsetWidth) : e.pageX) + 'px';
    };

  for(var i = 0, max = nfos.length; i < max; i++){
    nfos[i].parentNode.setAttribute('data-has-tip', true);
    nfos[i].parentNode.onmouseover = function(){ 
      tip.className = 'ref visible';      
      tip.innerHTML = this.getElementsByTagName('q')[0].innerHTML;
      window.clearTimeout(tip.fadeOut);
    };
    nfos[i].parentNode.onmouseout = function(){
      tip.className = 'ref visible fadingOut';
      tip.fadeOut = window.setTimeout(function(){
        tip.innerHTML = '';
        tip.className = '';
      }, 250);    
    };  
  }

  tip.id = 'rTip';
  document.body.appendChild(tip);
});

window.addEventListener('keydown', function(ev){
  if(ev.keyCode != 88)
    return;

  var haveCollapsed = !!document.querySelector('.ref input[type="checkbox"]:not(:checked)'),
      inputs = document.querySelectorAll('.ref input[type="checkbox"]');

  ev.preventDefault();
  for(var i = 0, max = inputs.length; i < max; i++)
    inputs[i].checked = !!haveCollapsed;
});
