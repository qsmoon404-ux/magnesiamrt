(function(){
  'use strict';
  var $=function(s,c){return (c||document).querySelector(s)}, $$=function(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))};

  /* Мобильное меню */
  var burger=$('#burger'), nav=$('#nav');
  function closeNav(){nav.classList.remove('open');burger.setAttribute('aria-expanded','false');document.body.style.overflow=''}
  burger.addEventListener('click',function(){
    var open=!nav.classList.contains('open');
    nav.classList.toggle('open',open);burger.setAttribute('aria-expanded',String(open));document.body.style.overflow=open?'hidden':'';
  });
  $$('a',nav).forEach(function(a){a.addEventListener('click',closeNav)});

  /* Подпись номера среза в анимации МРТ */
  var no=$('#sliceNo');
  if(no && !matchMedia('(prefers-reduced-motion: reduce)').matches){
    var n=1,dir=1; setInterval(function(){n+=dir; if(n>=24||n<=1)dir=-dir; no.textContent='Срез '+(n<10?'0':'')+n;},290);
  }

  /* Cookie */
  var ck=$('#cookie');
  try{ if(!localStorage.getItem('mg_cookie')) ck.hidden=false; }catch(e){ ck.hidden=false; }
  $('#cookieOk').addEventListener('click',function(){ck.hidden=true;try{localStorage.setItem('mg_cookie','1')}catch(e){}});

  /* Прайс: фильтр и поиск */
  var tabs=$('#priceTabs'), search=$('#priceSearch');
  if(tabs&&search){
    var group='all';
    var apply=function(){
      var q=search.value.trim().toLowerCase(), any=false;
      $$('.pgroup').forEach(function(g){
        var show=group==='all'||g.dataset.g===group, vis=0;
        $$('.prow',g).forEach(function(r){var m=!q||r.textContent.toLowerCase().indexOf(q)>-1;r.hidden=!m;if(m)vis++});
        g.hidden=!show||vis===0; if(!g.hidden)any=true;
      });
      $('#priceEmpty').hidden=any;
    };
    tabs.addEventListener('click',function(e){
      var b=e.target.closest('.chip'); if(!b)return;
      $$('.chip',tabs).forEach(function(c){c.classList.remove('on')}); b.classList.add('on'); group=b.dataset.g; apply();
    });
    search.addEventListener('input',apply);
  }

  /* Форма записи */
  var form=$('#bookingForm'); if(!form)return;
  var phone=$('#f-phone');
  phone.addEventListener('input',function(){
    var d=phone.value.replace(/\D/g,''); if(d[0]==='8')d='7'+d.slice(1); if(d&&d[0]!=='7')d='7'+d; d=d.slice(0,11);
    var s=''; if(d.length>0)s='+7'; if(d.length>1)s+=' ('+d.slice(1,4); if(d.length>=4)s+=')'; if(d.length>4)s+=' '+d.slice(4,7); if(d.length>7)s+='-'+d.slice(7,9); if(d.length>9)s+='-'+d.slice(9,11);
    phone.value=s;
  });
  function clearErrors(){
    $$('.err',form).forEach(function(p){p.textContent=''});
    $$('.field.invalid',form).forEach(function(f){f.classList.remove('invalid')});
    var fe=$('#formError'); fe.hidden=true;
  }
  function showErrors(errs){
    var first=null;
    Object.keys(errs).forEach(function(k){
      var p=$('.err[data-for="'+k+'"]',form); if(p){p.textContent=errs[k]; var f=p.closest('.field'); if(f)f.classList.add('invalid'); if(!first)first=f;}
    });
    if(first){var i=$('input,select,textarea',first); if(i)i.focus({preventScroll:true}); first.scrollIntoView({behavior:'smooth',block:'center'});}
  }
  function validate(){
    var e={}, v=function(n){var el=form.elements[n];return el?el.value.trim():''};
    if(!/^[A-Za-zА-Яа-яЁё\s.\-']{2,100}$/.test(v('name')))e.name='Укажите имя буквами, без цифр.';
    if(v('phone').replace(/\D/g,'').length!==11)e.phone='Введите номер полностью, например +7 (900) 123-45-67.';
    if(!v('service'))e.service='Выберите исследование из списка.';
    if(!v('date'))e.date='Выберите дату.';
    if(!form.querySelector('input[name=time]:checked'))e.time='Выберите удобное время.';
    if(!form.elements.consent.checked)e.consent='Нужно согласие на обработку персональных данных.';
    return e;
  }
  form.addEventListener('submit',function(ev){
    ev.preventDefault(); clearErrors();
    var errs=validate(); if(Object.keys(errs).length){showErrors(errs);return;}
    var btn=$('#submitBtn'), label=btn.textContent; btn.disabled=true; btn.textContent='Отправляем…';
    fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin'})
      .then(function(r){return r.json().catch(function(){return {ok:false,error:'Сервер вернул неожиданный ответ.'}})})
      .then(function(d){
        if(d.ok){
          $('#okId').textContent=d.id||''; form.hidden=true; var ok=$('#bookingOk'); ok.hidden=false; ok.focus(); ok.scrollIntoView({behavior:'smooth',block:'center'});
        }else{
          if(d.errors)showErrors(d.errors);
          var fe=$('#formError'); fe.textContent=d.error||'Не удалось отправить заявку. Позвоните нам.'; fe.hidden=false;
        }
      })
      .catch(function(){var fe=$('#formError'); fe.textContent='Нет связи с сервером. Проверьте интернет или позвоните нам.'; fe.hidden=false;})
      .finally(function(){btn.disabled=false; btn.textContent=label;});
  });
  $('#againBtn').addEventListener('click',function(){form.reset();form.hidden=false;$('#bookingOk').hidden=true;form.scrollIntoView({behavior:'smooth',block:'start'})});
})();
