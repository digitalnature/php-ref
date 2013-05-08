window.addEventListener('load', function(){
  var tip  = document.createElement('div'),
      refs = document.querySelectorAll('.ref');

  for(var i = 0, m = refs.length; i < m; i++){
    var tippable = refs[i].querySelectorAll('b[data-tip]'),
        tips     = refs[i].querySelectorAll('q');

    [].filter.call(tips, function(node){
      return node.parentNode == refs[i];
    });

    for(var j = 0, n = tippable.length; j < n; j++){
      tippable[j].tipRef = tips[tippable[j].dataset.tip];
      tippable[j].onmouseover = function(){ 
        tip.className = 'ref visible'; 
        tip.innerHTML = this.tipRef.innerHTML;
        window.clearTimeout(tip.fadeOut);
      };
      tippable[j].onmouseout = function(){
        tip.className = 'ref visible fadingOut';
        tip.fadeOut = window.setTimeout(function(){
          tip.innerHTML = '';
          tip.className = '';
        }, 250);    
      };  
    }

    refs[i].onmousemove = function(e){
      if(tip.className.indexOf('visible') < 0)
        return;
      tip.style.top = ((document.documentElement.clientHeight - e.clientY) < tip.offsetHeight + 20 ? (e.pageY - tip.offsetHeight) : e.pageY) + 'px';
      tip.style.left = ((document.documentElement.clientWidth - e.clientX) < tip.offsetWidth + 20 ? (e.pageX - tip.offsetWidth) : e.pageX) + 'px';
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
  for(var i = 0, m = inputs.length; i < m; i++)
    inputs[i].checked = !!haveCollapsed;
});
