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
</style></head><body><div id="w"><div id="g"><div id="bg"></div><div id="sn"></div><div id="road"></div><div id="ps"></div><div id="car"><svg width="70" height="42" viewBox="0 0 70 42"><rect x="0" y="12" width="70" height="22" rx="6" fill="#0074dd"/><path d="M14 13 23 1h24l10 12z" fill="#0074dd"/><path d="M25 4h20l6 8H19z" fill="#9fd7ff"/><path d="M36 4v8" stroke="#0074dd" stroke-width="2"/><rect x="64" y="17" width="6" height="4" fill="#ffd166"/><circle cx="17" cy="34" r="8" fill="#0b1020"/><circle cx="17" cy="34" r="3.5" fill="#c9d3e8"/><circle cx="53" cy="34" r="8" fill="#0b1020"/><circle cx="53" cy="34" r="3.5" fill="#c9d3e8"/></svg><svg id="lg" width="28" height="12.17" viewBox="0 0 28 12.17"><path d="M 11.89 0.47 C 10.77 0.19 9.46 0 7.96 0 C 6.37 0 4.87 0.28 3.65 0.84 C 2.53 1.4 1.59 2.15 0.94 3.18 C 0.28 4.12 0 5.24 0 6.46 C 0 7.12 0.19 8.33 0.94 9.27 C 2.06 10.86 4.21 12.08 7.87 12.08 C 10.39 12.08 12.64 11.14 13.2 10.86 L 13.58 8.99 C 13.48 8.9 13.11 8.71 12.36 8.71 H 11.61 C 10.96 9.08 10.02 9.36 8.43 9.36 C 7.4 9.36 6.27 9.27 5.62 8.99 C 4.96 8.71 4.49 8.24 4.21 7.77 C 3.84 7.3 3.65 6.84 3.65 6.27 C 3.65 5.62 3.84 5.06 4.21 4.49 C 4.59 3.93 5.06 3.56 5.81 3.28 C 6.56 2.9 7.4 2.72 8.33 2.72 C 9.55 2.72 10.68 2.9 11.71 3.28 C 12.74 3.65 13.67 4.03 14.42 4.49 L 14.98 1.69 C 14.14 1.22 13.11 0.75 11.89 0.47 Z M 22.47 3 C 23.04 3 23.51 3 23.97 3.37 C 24.35 3.65 24.44 4.03 24.35 4.78 H 26.41 C 27.63 4.78 27.91 4.49 27.91 4.49 C 27.91 4.31 27.91 4.03 27.91 3.75 C 27.91 2.43 27.44 1.5 26.5 0.94 C 25.66 0.47 24.63 0.37 23.13 0.37 H 16.11 L 13.86 11.8 H 17.42 L 19.1 3 H 22.47 Z" fill="#fff"/><path d="M 5.9 5.34 H 11.05 C 12.92 5.34 14.23 6.18 13.67 8.52 C 13.67 8.52 13.3 8.24 12.36 8.24 H 8.99 C 7.02 8.24 5.53 7.02 5.9 5.34 Z M 26.69 5.34 H 22.76 C 20.98 5.34 19.67 6.56 19.38 8.24 H 24.16 C 25.85 8.24 27.44 6.84 27.81 5.06 C 27.81 5.06 27.44 5.34 26.69 5.34 Z" fill="#ec1c24"/></svg></div><div id="hud"><span id="sc">СЧЁТ 0000</span><span id="bs">РЕКОРД 0000</span></div><div id="hint">ТАП — ПОЕХАЛИ</div></div><div id="o" hidden><p id="t">АВАРИЯ!</p><p id="s"></p><button id="r" type="button">Ещё раз</button></div></div><script>
var W=document.getElementById('w'),G=document.getElementById('g'),bg=document.getElementById('bg'),sn=document.getElementById('sn'),rd=document.getElementById('road'),ps=document.getElementById('ps'),car=document.getElementById('car'),sc=document.getElementById('sc'),bs=document.getElementById('bs'),hint=document.getElementById('hint'),o=document.getElementById('o'),T=document.getElementById('t'),S=document.getElementById('s'),R=document.getElementById('r');
var DW=360,DH=220,GY=176,CX=64,CW=70,CH=42;
var phase=0,carY=GY-CH,vy=0,speed=280,score=0,best=0,record=0,obs=[],gap=540,since=0,t=0,road=0,last=0,lastS=-1,lastB=-1,lastW=0,i,pool=[],fc=99;
var PUMP='<svg width="42" height="58" viewBox="0 0 42 58"><rect x="2" width="30" height="58" rx="4" fill="#dfe7f7"/><rect x="2" width="30" height="8" fill="#e5484d"/><rect x="6" y="12" width="22" height="13" fill="#101a2e"/><rect x="8" y="14" width="18" height="9" fill="#34d3c8"/><rect x="2" y="51" width="30" height="7" fill="#8a94ad"/><path d="M32 21Q41 31 35 44" stroke="#8a94ad" stroke-width="2" fill="none"/><rect x="33" y="42" width="5" height="7" fill="#8a94ad"/><text x="9" y="53" font-size="7" font-weight="700" fill="#0b1020">АЗС</text></svg>';
try{record=parseInt(localStorage.getItem('ny27best'),10)||0}catch(e){record=0}
best=record;
function rnd(n){return Math.random()*n|0}
function pad(n){return(''+Math.floor(n)).padStart(4,'0')}
function buildBg(){
  var h='<svg width="360" height="220" viewBox="0 0 360 220"><defs><linearGradient id="gg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#060a14"/><stop offset=".5" stop-color="#0d1830"/><stop offset="1" stop-color="#1c2c4c"/></linearGradient></defs><rect width="360" height="176" fill="url(#gg)"/>',k;
  for(i=0;i<22;i++)h+='<rect x="'+rnd(358)+'" y="'+rnd(140)+'" width="1.5" height="1.5" fill="#dfe7f7"/>';
  h+='<circle cx="282" cy="38" r="19" fill="#f4e7c3"/><circle cx="290" cy="33" r="17" fill="#0d1830"/>';
  for(i=0;i<360;i+=24){k=16+rnd(20);var d=16+rnd(42);h+='<rect x="'+i+'" y="'+(176-d)+'" width="'+k+'" height="'+d+'" fill="#0a1224"/><rect x="'+(i+4)+'" y="'+(183-d)+'" width="3" height="4" fill="#ffd166"/><rect x="'+(i+k-8)+'" y="'+(188-d)+'" width="3" height="4" fill="#ffd166"/>'}
  h+='<rect y="176" width="360" height="44" fill="#141c2e"/><rect y="176" width="360" height="2" fill="#2b3652"/></svg>';
  bg.innerHTML=h;
}
function buildSnow(){
  var h='';for(i=0;i<10;i++)h+='<i style="left:'+rnd(360)+'px;animation-duration:'+(5+rnd(4))+'s;animation-delay:-'+rnd(9)+'s"></i>';
  sn.innerHTML=h;
}
function buildRoad(){
  var h='';for(i=0;i<8;i++)h+='<i style="left:'+(i*54)+'px"></i>';
  rd.innerHTML=h;
}
function buildPumps(){
  for(i=0;i<7;i++){var d=document.createElement('div');d.className='p';d.innerHTML=PUMP;ps.appendChild(d);pool.push(d)}
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
function restart(){phase=1;carY=GY-CH;vy=0;speed=280;score=0;obs=[];since=0;gap=540;road=0;best=Math.max(best,record);o.hidden=1;hint.style.display='none'}
function render(){
  car.style.transform='translate('+CX+'px,'+(carY+(phase===1&&carY>=GY-CH-.5?Math.sin(t*26):0))+'px)';
  rd.style.transform='translateX(-'+(road%54)+'px)';
  var pi=0,j,k,d;
  for(j=0;j<obs.length;j++)for(k=0;k<obs[j].n;k++){d=pool[pi++];if(d){if(d.style.display!=='block')d.style.display='block';d.style.transform='translate('+(obs[j].x+k*58)+'px,'+(GY-58)+'px)'}}
  for(;pi<pool.length;pi++){d=pool[pi];if(d.style.display!=='none')d.style.display='none'}
  if(++fc>7){fc=0;var s=Math.floor(score),b=Math.floor(best);if(s!==lastS){lastS=s;sc.textContent='СЧЁТ '+pad(s)}if(b!==lastB){lastB=b;bs.textContent='РЕКОРД '+pad(b)}}
}
function loop(ts){
  var dt=last?Math.min(.05,(ts-last)/1000):0;last=ts;t+=dt;
  if(phase===1){
    speed=Math.min(720,280+score*.2);score+=speed*dt/10;road+=speed*dt;
    if(carY<GY-CH||vy<0){vy+=2000*dt;carY+=vy*dt;if(carY>=GY-CH){carY=GY-CH;vy=0}}
    since+=speed*dt;if(since>=gap)spawn();
    for(i=0;i<obs.length;i++)obs[i].x-=speed*dt;
    obs=obs.filter(function(q){return q.x+(q.n-1)*58+34>0});
    if(hit())die();
    if(score>best)best=score;
  }
  render();requestAnimationFrame(loop);
}
G.addEventListener('pointerdown',function(e){e.preventDefault();if(phase===0){restart()}else if(phase===1&&carY>=GY-CH-.5){vy=-720}});
R.addEventListener('click',restart);
buildBg();buildSnow();buildRoad();buildPumps();size();render();
if(window.ResizeObserver)new ResizeObserver(size).observe(W);
window.addEventListener('resize',size);
requestAnimationFrame(loop);
</script></body></html>`;
