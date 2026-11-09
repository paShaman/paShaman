export const GAME_HTML = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
html,body{margin:0;background:#060a14;color:#e8ecff;font:600 14px system-ui,sans-serif}
#w{position:relative;width:100%;height:220px;aspect-ratio:360/220;overflow:hidden;background:#060a14}
#g{position:absolute;left:0;top:0;width:360px;height:220px;transform-origin:0 0}
#bg,#sn,#ps{position:absolute;left:0;top:0;width:360px;height:220px}
#sn i{position:absolute;top:-6px;width:2px;height:2px;border-radius:50%;background:#cfe0ff;animation:snow 6s linear infinite}
@keyframes snow{to{transform:translateY(232px)}}
#road{position:absolute;left:0;top:200px;width:420px;height:4px}
#road i{position:absolute;top:0;width:26px;height:4px;background:#ffd166}
#car{position:absolute;left:0;top:0;width:70px;height:42px}
#car svg{display:block}
#lg{position:absolute;left:20px;top:13px}
.p{position:absolute;left:0;top:0;width:42px;height:58px;display:none}
#hud{position:absolute;left:12px;right:12px;top:10px;display:flex;justify-content:space-between;font-weight:700;font-size:14px}
#hint{position:absolute;left:0;right:0;top:98px;text-align:center;color:#ffd166;font-size:16px;font-weight:700}
#o{position:absolute;top:0;right:0;bottom:0;left:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:rgba(6,10,20,.82);text-align:center;padding:10px;box-sizing:border-box}
#o[hidden]{display:none}
#t{margin:0;font-size:22px;letter-spacing:.08em;color:#ffd166}
#s{margin:0;font-size:14px;color:#dfe7f7}
button{margin-top:12px;padding:8px 14px;border:0;border-radius:10px;background:#2f81f7;color:#fff;font:inherit;font-size:14px}
</style></head><body><div id="w"><div id="g"><div id="bg"></div><div id="sn"></div><div id="road"></div><div id="ps"></div><div id="car"><svg width="70" height="42" viewBox="0 0 70 42"><rect x="0" y="12" width="70" height="22" rx="6" fill="#0074dd"/><path d="M14 13 23 1h24l10 12z" fill="#0074dd"/><path d="M25 4h20l6 8H19z" fill="#9fd7ff"/><path d="M36 4v8" stroke="#0074dd" stroke-width="2"/><rect x="64" y="17" width="6" height="4" fill="#ffd166"/><circle cx="17" cy="34" r="8" fill="#0b1020"/><circle cx="17" cy="34" r="3.5" fill="#c9d3e8"/><circle cx="53" cy="34" r="8" fill="#0b1020"/><circle cx="53" cy="34" r="3.5" fill="#c9d3e8"/></svg><svg id="lg" width="28" height="12.2" viewBox="0 0 28 12.2"><path d="M 11.9 0.5 C 10.8 0.2 9.5 0 8 0 C 6.4 0 4.9 0.3 3.7 0.8 C 2.5 1.4 1.6 2.2 0.9 3.2 C 0.3 4.1 0 5.2 0 6.5 C 0 7.1 0.2 8.3 0.9 9.3 C 2.1 10.9 4.2 12.1 7.9 12.1 C 10.4 12.1 12.6 11.1 13.2 10.9 L 13.6 9 C 13.5 8.9 13.1 8.7 12.4 8.7 H 11.6 C 11 9.1 10 9.4 8.4 9.4 C 7.4 9.4 6.3 9.3 5.6 9 C 5 8.7 4.5 8.2 4.2 7.8 C 3.8 7.3 3.7 6.8 3.7 6.3 C 3.7 5.6 3.8 5.1 4.2 4.5 C 4.6 3.9 5.1 3.6 5.8 3.3 C 6.6 2.9 7.4 2.7 8.3 2.7 C 9.6 2.7 10.7 2.9 11.7 3.3 C 12.7 3.7 13.7 4 14.4 4.5 L 15 1.7 C 14.1 1.2 13.1 0.7 11.9 0.5 Z M 22.5 3 C 23 3 23.5 3 24 3.4 C 24.3 3.7 24.4 4 24.3 4.8 H 26.4 C 27.6 4.8 27.9 4.5 27.9 4.5 C 27.9 4.3 27.9 4 27.9 3.7 C 27.9 2.4 27.4 1.5 26.5 0.9 C 25.7 0.5 24.6 0.4 23.1 0.4 H 16.1 L 13.9 11.8 H 17.4 L 19.1 3 H 22.5 Z" fill="#fff"/><path d="M 5.9 5.3 H 11.1 C 12.9 5.3 14.2 6.2 13.7 8.5 C 13.7 8.5 13.3 8.2 12.4 8.2 H 9 C 7 8.2 5.5 7 5.9 5.3 Z M 26.7 5.3 H 22.8 C 21 5.3 19.7 6.6 19.4 8.2 H 24.2 C 25.8 8.2 27.4 6.8 27.8 5.1 C 27.8 5.1 27.4 5.3 26.7 5.3 Z" fill="#ec1c24"/></svg></div><div id="hud"><span id="sc">СЧЁТ 0000</span><span id="bs">РЕКОРД 0000</span></div><div id="hint">ТАП — ПОЕХАЛИ</div></div><div id="o" hidden><p id="t">АВАРИЯ!</p><p id="s"></p><button id="r" type="button">Ещё раз</button></div></div><script>
var W=document.getElementById('w'),G=document.getElementById('g'),bg=document.getElementById('bg'),sn=document.getElementById('sn'),rd=document.getElementById('road'),ps=document.getElementById('ps'),car=document.getElementById('car'),sc=document.getElementById('sc'),bs=document.getElementById('bs'),hint=document.getElementById('hint'),o=document.getElementById('o'),T=document.getElementById('t'),S=document.getElementById('s'),R=document.getElementById('r');
var DW=360,DH=220,GY=176,CX=64,CW=70,CH=42;
var phase=0,carY=GY-CH,vy=0,speed=280,score=0,best=0,record=0,obs=[],gap=540,since=0,t=0,road=0,last=0,lastCarY=-1,lastS=-1,lastB=-1,lastW=0,i,pool=[],fc=99;
var PUMP='<svg width="42" height="58" viewBox="0 0 42 58"><rect x="2" width="30" height="58" rx="4" fill="#dfe7f7"/><rect x="2" width="30" height="8" fill="#e5484d"/><rect x="6" y="12" width="22" height="13" fill="#101a2e"/><rect x="8" y="14" width="18" height="9" fill="#34d3c8"/><rect x="2" y="51" width="30" height="7" fill="#8a94ad"/><path d="M32 21Q41 31 35 44" stroke="#8a94ad" stroke-width="2" fill="none"/><rect x="33" y="42" width="5" height="7" fill="#8a94ad"/><text x="9" y="53" font-size="7" font-weight="700" fill="#0b1020">АЗС</text></svg>';
try{record=parseInt(localStorage.getItem('ny27best'),10)||0}catch(e){record=0}
best=record;
function rnd(n){return Math.random()*n|0}
function pad(n){return(''+Math.floor(n)).padStart(4,'0')}
function buildBg(){
  var h='<svg width="360" height="220" viewBox="0 0 360 220"><defs><linearGradient id="gg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#060a14"/><stop offset=".5" stop-color="#0d1830"/><stop offset="1" stop-color="#1c2c4c"/></linearGradient></defs><rect width="360" height="176" fill="url(#gg)"/>',k;
  for(i=0;i<16;i++)h+='<rect x="'+rnd(358)+'" y="'+rnd(140)+'" width="1.5" height="1.5" fill="#dfe7f7"/>';
  h+='<circle cx="282" cy="38" r="19" fill="#f4e7c3"/><circle cx="290" cy="33" r="17" fill="#0d1830"/>';
  for(i=0;i<360;i+=30){k=16+rnd(20);var d=16+rnd(42);h+='<rect x="'+i+'" y="'+(176-d)+'" width="'+k+'" height="'+d+'" fill="#0a1224"/><rect x="'+(i+4)+'" y="'+(183-d)+'" width="3" height="4" fill="#ffd166"/><rect x="'+(i+k-8)+'" y="'+(188-d)+'" width="3" height="4" fill="#ffd166"/>'}
  h+='<rect y="176" width="360" height="44" fill="#141c2e"/><rect y="176" width="360" height="2" fill="#2b3652"/></svg>';
  bg.innerHTML=h;
}
function buildSnow(){
  var h='';for(i=0;i<6;i++)h+='<i style="left:'+rnd(360)+'px;animation-duration:'+(5+rnd(4))+'s;animation-delay:-'+rnd(9)+'s"></i>';
  sn.innerHTML=h;
}
function buildRoad(){
  var h='';for(i=0;i<8;i++)h+='<i style="left:'+(i*54)+'px"></i>';
  rd.innerHTML=h;
}
function buildPumps(){
  for(i=0;i<5;i++){var d=document.createElement('div');d.className='p';d.style.display='none';d.innerHTML=PUMP;ps.appendChild(d);pool.push(d)}
}
function size(){
  var w=W.getBoundingClientRect().width||360;if(w===lastW)return;lastW=w;
  W.style.height=Math.round(DH*w/DW)+'px';G.style.transform='scale('+(w/DW)+')';
}
function spawn(){var n=score>140&&Math.random()<.35?2:1;obs.push({x:DW+20,n:n});since=0;gap=340+Math.random()*220+(n-1)*70}
function hit(){
  var ax=CX+12,ay=carY+9,aw=46,ah=30;
  for(var j=0;j<obs.length;j++){var q=obs[j];for(var k=0;k<q.n;k++){var px=q.x+k*58+5;if(ax<px+24&&ax+aw>px&&ay<GY&&ay+ah>GY-55)return true}}
  return false;
}
function die(){
  phase=2;var f=Math.floor(score);
  if(f>record){record=f;try{localStorage.setItem('ny27best',''+record)}catch(e){}}
  best=Math.max(best,record);T.textContent='АВАРИЯ!';S.textContent='Счёт '+f+' · рекорд '+record;o.hidden=0;
}
function restart(){phase=1;carY=GY-CH;vy=0;speed=280;score=0;obs=[];since=0;gap=540;road=0;best=Math.max(best,record);o.hidden=1;hint.style.display='none';lastS=lastB=-1;fc=99;render()}
function render(){
  var by=carY+(phase===1&&carY>=GY-CH-.5?Math.sin(t*22)*.5:0);
  if(by!==lastCarY){lastCarY=by;car.style.transform='translate('+CX+'px,'+by+'px)'}
  rd.style.transform='translateX(-'+(road%54)+'px)';
  var pi=0,j,k,d;
  for(j=0;j<obs.length;j++)for(k=0;k<obs[j].n;k++){d=pool[pi++];if(d){if(d.style.display!=='block')d.style.display='block';d.style.transform='translate('+(obs[j].x+k*58)+'px,'+(GY-58)+'px)'}}
  for(;pi<pool.length;pi++){d=pool[pi];if(d.style.display!=='none')d.style.display='none'}
  if(++fc>2){fc=0;var s=Math.floor(score),b=Math.floor(best);if(s!==lastS){lastS=s;sc.textContent='СЧЁТ '+pad(s)}if(b!==lastB){lastB=b;bs.textContent='РЕКОРД '+pad(b)}}
}
function loop(ts){
  if(!last)last=ts;
  var e=ts-last;if(e<32){requestAnimationFrame(loop);return}
  var dt=Math.min(.05,e/1000);last=ts;t+=dt;
  if(phase===1){
    speed=Math.min(720,280+score*.2);score+=speed*dt/10;road+=speed*dt;
    if(carY<GY-CH||vy<0){vy+=2000*dt;carY+=vy*dt;if(carY>=GY-CH){carY=GY-CH;vy=0}}
    since+=speed*dt;if(since>=gap)spawn();
    for(i=0;i<obs.length;i++)obs[i].x-=speed*dt;
    for(i=obs.length-1;i>=0;i--)if(obs[i].x+(obs[i].n-1)*58+34<=0)obs.splice(i,1);
    if(hit())die();
    if(score>best)best=score;
    render();
  }
  requestAnimationFrame(loop);
}
G.addEventListener('pointerdown',function(e){e.preventDefault();if(phase===0){restart()}else if(phase===1&&carY>=GY-CH-.5){vy=-720}});
R.addEventListener('click',restart);
buildBg();buildSnow();buildRoad();buildPumps();size();render();
if(window.ResizeObserver)new ResizeObserver(size).observe(W);
window.addEventListener('resize',size);
requestAnimationFrame(loop);
</script></body></html>`;
