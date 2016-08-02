window.addEventListener('load', function(){

  var tip  = document.createElement('div'),
      refs = document.querySelectorAll('.ref');

  for(var i = 0, m = refs.length; i < m; i++){
    var kbds     = refs[i].querySelectorAll('[data-toggle]'),
        tippable = refs[i].querySelectorAll('[data-tip],[h]'),
        tips     = refs[i].querySelectorAll('div, t');        

    for(var j = 0, n = kbds.length; j < n; j++){        
      if(kbds[j].parentNode !== refs[i])
        kbds[j].onclick = function(e){
          ('exp' in this.dataset) ? delete this.dataset.exp : this.dataset.exp = 1;
        }
    }

    [].filter.call(tips, function(node){
      return node.parentNode == refs[i];
    });

    for(var j = 0, n = tippable.length; j < n; j++){
      tippable[j].tipRef = tips[tippable[j].dataset.tip] || tips[tippable[j].getAttribute('h')];
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
      tip.style.top = ((document.documentElement.clientHeight - e.clientY) < tip.offsetHeight + 20 ? Math.max(e.pageY - tip.offsetHeight, 0) : e.pageY) + 'px';
      tip.style.left = ((document.documentElement.clientWidth - e.clientX) < tip.offsetWidth + 20 ? Math.max(e.pageX - tip.offsetWidth, 0) : e.pageX) + 'px';
    };    
  }

  tip.id = 'rTip';
  document.body.appendChild(tip);

  window.addEventListener('keydown', function(e){
    if((e.keyCode != 88) || (['input', 'textarea', 'select'].indexOf(e.target.tagName.toLowerCase()) > -1))
      return;

    e.preventDefault();

    if(e.ctrlKey && e.keyCode == 88){
      var d = refs[0].style.display !== 'none' ? 'none' : 'block';
      for(var i = 0, n = refs.length; i < n; i++)
        refs[i].style.display = d;

      return;
    }

    var kbds = document.querySelectorAll('.ref [data-toggle]'),
        m = kbds.length,
        partlyExp = document.querySelectorAll('.ref [data-toggle][data-exp]').length !== m;

    for(var i = 0; i < m; i++)
      partlyExp ? (kbds[i].dataset.exp = 1) : (delete kbds[i].dataset.exp);    

  });

});

