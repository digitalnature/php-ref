
var tooltip = { name: 'rTip', offsetX: 0, offsetY: 15, tip: null };

tooltip.init = function () {
  
  var tipContainer = document.getElementById(tooltip.name);

  if(!tipContainer){
    tipContainer = document.createElement('div');
    tipContainer.setAttribute('id', tooltip.name);          
    tipContainer.className = 'reflekt';
    document.getElementsByTagName('body').item(0).appendChild(tipContainer);
  }
  
  this.tip = document.getElementById(this.name);

  if(this.tip)
    document.onmousemove = function(e){ tooltip.move(e); };

  var nfoTags = document.getElementsByTagName('code');
  if(nfoTags){
    for(var i = 0; i < nfoTags.length; i ++){      
      var title = nfoTags[i].innerHTML;
      if(title){
        nfoTags[i].parentNode.setAttribute('tip', title);              
        nfoTags[i].parentNode.onmouseover = function(){ tooltip.show(this.getAttribute('tip')); };
        nfoTags[i].parentNode.onmouseout = function(){ tooltip.hide(); };
      }
    }
  }
  
};

tooltip.move = function(evt){
  var x = 0, y = 0;
  if(document.all){
    x = (document.documentElement && document.documentElement.scrollLeft) ? document.documentElement.scrollLeft : document.body.scrollLeft;
    y = (document.documentElement && document.documentElement.scrollTop) ? document.documentElement.scrollTop : document.body.scrollTop;
    x += window.event.clientX;
    y += window.event.clientY;
    
  }else{
    x = evt.pageX;
    y = evt.pageY;
  };
  this.tip.style.left = (x + this.offsetX) + 'px';
  this.tip.style.top = (y + this.offsetY) + 'px';
};

tooltip.show = function(text){
  if(!this.tip) return;
  this.tip.innerHTML = text;
  this.tip.style.display = 'block';
};

tooltip.hide = function(){
  if(!this.tip) return;
  this.tip.innerHTML = '';
  this.tip.style.display = 'none';
};



var addEvent = function(evt, elem, func){      
  if(elem.addEventListener){
    elem.addEventListener(evt, func, false);
    return;
  }  
  
  elem.attachEvent('on' + evt, func);          
};      

addEvent('load', window, function(e){
  tooltip.init();
});      

addEvent('click', document, function(e){
  var target = e.target || e.srcElement;
  if(target.className.indexOf('rToggle') !== -1)
    target.className = target.className.replace(/\bexp\b|\bcol\b/, function(m){ return m == 'col' ? 'exp' : 'col'; });
});
