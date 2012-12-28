window.addEventListener('load', function(){

  this.rTip = { e: document.getElementById('rTip') };

  if(!this.rTip.e){
    this.rTip.e = document.createElement('div');
    this.rTip.e.id = 'rTip';
    document.getElementsByTagName('body').item(0).appendChild(this.rTip.e);
  }

  document.onmousemove = function(evt){ 
    rTip.e.style.left = evt.pageX + 'px';
    rTip.e.style.top = evt.pageY + 'px';
  };

  var tags = document.getElementsByTagName('code');

  for(var i in tags){

    if(!tags[i].innerHTML)
      continue;

    tags[i].parentNode.setAttribute('txt', tags[i].innerHTML);
    tags[i].parentNode.onmouseover = function(){ 
      if(!rTip.e) return;
      rTip.e.innerHTML = this.getAttribute('txt');
      rTip.e.style.display = 'block';
    };

    tags[i].parentNode.onmouseout = function(){ 
      if(!rTip.e) return;          
      rTip.e.innerHTML = '';
      rTip.e.style.display = 'none';
    };
  
  }
});      

document.addEventListener('click', function(evt){
  var target = evt.target || evt.srcElement;
  if(target.className.indexOf('rToggle') !== -1)
    target.className = target.className.replace(/\bexp\b|\bcol\b/, function(m){ return m !== 'col' ? 'col' : 'exp'; });
});