'use strict';
(()=>{
 const root=document.querySelector('.hero-carousel');if(!root)return;
 const slides=[...root.querySelectorAll('.hero-slide')],selectors=[...root.querySelectorAll('[data-slide]')];
 const pause=root.querySelector('#carouselPause'),status=root.querySelector('#carouselStatus');
 const reduce=matchMedia('(prefers-reduced-motion: reduce)');
 let index=0,userPaused=reduce.matches,hovered=false,focused=false,visible=true,timer=null,touch=null;
 const interval=6500;
 function syncPause(){pause.setAttribute('aria-pressed',String(userPaused));pause.setAttribute('aria-label',userPaused?'Iniciar passagem automática':'Pausar passagem automática');pause.innerHTML=`<svg class="icon"><use href="#${userPaused?'play':'pause'}"/></svg>`;root.classList.toggle('autoplay-paused',userPaused||hovered||focused||document.hidden||!visible)}
 function schedule(){clearTimeout(timer);syncPause();if(userPaused||hovered||focused||document.hidden||!visible)return;timer=setTimeout(()=>{show(index+1);schedule()},interval)}
 function show(next,manual=false){index=(next+slides.length)%slides.length;slides.forEach((slide,i)=>{const active=i===index;slide.classList.toggle('is-active',active);slide.setAttribute('aria-hidden',String(!active));slide.inert=!active;if(active){const im=slide.querySelector('img');if(im)im.loading='eager'}});selectors.forEach((b,i)=>{b.classList.toggle('active',i===index);b.setAttribute('aria-pressed',String(i===index))});root.querySelector('#slideCounter').textContent=`${String(index+1).padStart(2,'0')} / ${String(slides.length).padStart(2,'0')}`;status.setAttribute('aria-live',manual?'polite':'off');status.textContent=`Capa ${index+1} de ${slides.length}: ${selectors[index].textContent.trim()}`;if(manual)schedule();const nextImage=slides[(index+1)%slides.length].querySelector('img');if(nextImage)nextImage.loading='eager'}
 root.querySelector('#carouselPrevious').addEventListener('click',()=>show(index-1,true));root.querySelector('#carouselNext').addEventListener('click',()=>show(index+1,true));selectors.forEach(b=>b.addEventListener('click',()=>show(Number(b.dataset.slide),true)));
 pause.addEventListener('click',()=>{userPaused=!userPaused;if(!userPaused){focused=false;hovered=false}schedule()});
 root.addEventListener('mouseenter',()=>{if(matchMedia('(hover: hover)').matches){hovered=true;schedule()}});root.addEventListener('mouseleave',()=>{hovered=false;schedule()});
 root.addEventListener('focusin',()=>{focused=true;schedule()});root.addEventListener('focusout',()=>{setTimeout(()=>{focused=root.contains(document.activeElement);schedule()},0)});
 root.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();show(index+1,true)}else if(e.key==='ArrowLeft'){e.preventDefault();show(index-1,true)}});
 root.querySelector('.hero-stage').addEventListener('touchstart',e=>{if(e.touches.length===1)touch={x:e.touches[0].clientX,y:e.touches[0].clientY}},{passive:true});
 root.querySelector('.hero-stage').addEventListener('touchend',e=>{if(!touch||!e.changedTouches.length)return;const dx=e.changedTouches[0].clientX-touch.x,dy=e.changedTouches[0].clientY-touch.y;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy)*1.5)show(index+(dx<0?1:-1),true);touch=null},{passive:true});
 document.addEventListener('visibilitychange',schedule);reduce.addEventListener('change',()=>{userPaused=reduce.matches;schedule()});
 if('IntersectionObserver'in window)new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;schedule()},{threshold:.1}).observe(root);
 show(0);schedule();
})();
