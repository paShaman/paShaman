export const GAME_HTML = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
html,body{margin:0;color:#e8ecff;font:600 14px system-ui,sans-serif}
#w{position:relative;background:#060a14}
canvas{display:block;width:100%;height:auto;aspect-ratio:360/220;touch-action:none}
#o{position:absolute;top:0;right:0;bottom:0;left:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:rgba(6,10,20,.82);text-align:center;padding:10px;box-sizing:border-box}
#o[hidden]{display:none}
#t{margin:0;font-size:22px;letter-spacing:.08em;color:#ffd166}
#s{margin:0;font-size:14px;color:#dfe7f7}
button{margin-top:12px;padding:8px 14px;border:0;border-radius:10px;background:#2f81f7;color:#fff;font:inherit;font-size:14px}
</style></head><body><div id="w"><canvas id="c" width="360" height="220"></canvas><div id="o" hidden><p id="t">АВАРИЯ!</p><p id="s"></p><button id="r" type="button">Ещё раз</button></div></div><script>
var c=document.getElementById('c'),x=c.getContext('2d'),o=document.getElementById('o'),T=document.getElementById('t'),S=document.getElementById('s'),R=document.getElementById('r');
var DW=360,DH=220,G=176,CX=64,CW=70,CH=42,DPR=1;
var phase=0,carY=G-CH,vy=0,speed=280,score=0,best=0,record=0,obs=[],gap=540,since=0,t=0,road=0,last=0,lastW=0,st=[],sn=[],bld=[],bgc=null,carCv=null,pumpCv=null,i;
var LW='M127 5C115 2 101 0 85 0C68 0 52 3 39 9C27 15 17 23 10 34C3 44 0 56 0 69C0 76 2 89 10 99C22 116 45 129 84 129C111 129 135 119 141 116L145 96C144 95 140 93 132 93H124C117 97 107 100 90 100C79 100 67 99 60 96C53 93 48 88 45 83C41 78 39 73 39 67C39 60 41 54 45 48C49 42 54 38 62 35C70 31 79 29 89 29C102 29 114 31 125 35C136 39 146 43 154 48L160 18C151 13 140 8 127 5Z M240 32C246 32 251 32 256 36C260 39 261 43 260 51H282C295 51 298 48 298 48C298 46 298 43 298 40C298 26 293 16 283 10C274 5 263 4 247 4H172L148 126H186L204 32H240Z';
var LR='M63 57H118C138 57 152 66 146 91C146 91 142 88 132 88H96C75 88 59 75 63 57Z M285 57H243C224 57 210 70 207 88H258C276 88 293 73 297 54C297 54 293 57 285 57Z';
try{record=parseInt(localStorage.getItem('ny27best'),10)||0}catch(e){record=0}
best=record;
function size(){
  var r=c.getBoundingClientRect(),w=Math.round(r.width||0)||DW;w=Math.max(200,w);
  var dpr=Math.min(window.devicePixelRatio||1,2);
  if(w===lastW&&dpr===DPR&&bgc){if(phase===0)carY=G-CH;return}
  lastW=w;DPR=dpr;
  var k=w/DW;
  c.width=Math.round(w*DPR);c.height=Math.round(DH*k*DPR);x.setTransform(DPR*k,0,0,DPR*k,0,0);
  st=[];sn=[];bld=[];
  for(i=0;i<28;i++)st.push([Math.random()*DW,Math.random()*G*.82,Math.random()*1.2+.4]);
  for(i=0;i<16;i++)sn.push([Math.random()*DW,Math.random()*G,Math.random()*1.3+.6,Math.random()*16+8]);
  for(i=0;i<DW;i+=22+Math.random()*20)bld.push([i,14+Math.random()*20,16+Math.random()*42]);
  bgc=document.createElement('canvas');bgc.width=c.width;bgc.height=c.height;
  var b=bgc.getContext('2d');b.setTransform(DPR*k,0,0,DPR*k,0,0);bg(b);
  carCv=document.createElement('canvas');carCv.width=Math.ceil(CW*DPR*k);carCv.height=Math.ceil(CH*DPR*k);
  var cg=carCv.getContext('2d');cg.setTransform(DPR*k,0,0,DPR*k,0,0);drawCar(cg,0,0);
  pumpCv=document.createElement('canvas');pumpCv.width=Math.ceil(42*DPR*k);pumpCv.height=Math.ceil(58*DPR*k);
  var pg=pumpCv.getContext('2d');pg.setTransform(DPR*k,0,0,DPR*k,0,0);drawPump(pg,0,0);
  if(phase===0)carY=G-CH;
}
function pad(n){return(''+Math.floor(n)).padStart(4,'0')}
function spawn(){var n=score>140&&Math.random()<.35?2:1;obs.push({x:DW+20,n:n});since=0;gap=340+Math.random()*220+(n-1)*70}
function hit(){
  var ax=CX+12,ay=carY+9,aw=46,ah=30;
  for(var j=0;j<obs.length;j++){var q=obs[j];for(var k=0;k<q.n;k++){var px=q.x+k*58+5;if(ax<px+24&&ax+aw>px&&ay<G&&ay+ah>G-55)return true}}
  return false;
}
function die(){
  phase=2;var f=Math.floor(score);
  if(f>record){record=f;try{localStorage.setItem('ny27best',''+record)}catch(e){}}
  best=Math.max(best,record);T.textContent='АВАРИЯ!';S.textContent='Счёт '+f+' · рекорд '+record;o.hidden=0;
}
function restart(){phase=1;carY=G-CH;vy=0;speed=280;score=0;obs=[];since=0;gap=540;best=Math.max(best,record);o.hidden=1}
function drawPump(g,ox,oy){
  g.fillStyle='#dfe7f7';g.beginPath();g.roundRect(ox+2,oy,30,58,4);g.fill();
  g.fillStyle='#e5484d';g.fillRect(ox+2,oy,30,8);
  g.fillStyle='#101a2e';g.fillRect(ox+6,oy+12,22,13);
  g.fillStyle='#34d3c8';g.fillRect(ox+8,oy+14,18,9);
  g.fillStyle='#8a94ad';g.fillRect(ox+2,oy+51,30,7);
  g.strokeStyle='#8a94ad';g.lineWidth=2;g.beginPath();g.moveTo(ox+32,oy+21);g.quadraticCurveTo(ox+41,oy+31,ox+35,oy+44);g.stroke();
  g.fillStyle='#8a94ad';g.fillRect(ox+33,oy+42,5,7);
  g.fillStyle='#0b1020';g.font='bold 7px system-ui,sans-serif';g.fillText('АЗС',ox+9,oy+53);
}
function drawLogo(g,ox,oy){
  g.save();
  try{g.translate(ox,oy);g.scale(28/299,12/130);g.fillStyle='#fff';g.fill(new Path2D(LW));g.fillStyle='#ec1c24';g.fill(new Path2D(LR))}catch(e){}
  g.restore();
}
function drawCar(g,ox,cy){
  var y=cy,k,wx;
  g.fillStyle='#0074dd';g.beginPath();g.roundRect(ox,y+12,CW,22,6);g.fill();
  g.beginPath();g.moveTo(ox+14,y+13);g.lineTo(ox+23,y+1);g.lineTo(ox+47,y+1);g.lineTo(ox+57,y+13);g.closePath();g.fill();
  g.fillStyle='#9fd7ff';g.beginPath();g.moveTo(ox+25,y+4);g.lineTo(ox+45,y+4);g.lineTo(ox+51,y+12);g.lineTo(ox+19,y+12);g.closePath();g.fill();
  g.strokeStyle='#0074dd';g.lineWidth=2;g.beginPath();g.moveTo(ox+36,y+4);g.lineTo(ox+36,y+12);g.stroke();
  drawLogo(g,ox+20,y+13);
  g.fillStyle='#ffd166';g.fillRect(ox+CW-6,y+17,6,4);
  for(k=0;k<2;k++){wx=ox+17+k*36;g.fillStyle='#0b1020';g.beginPath();g.arc(wx,y+34,8,0,7);g.fill();g.fillStyle='#c9d3e8';g.beginPath();g.arc(wx,y+34,3.5,0,7);g.fill()}
}
function bg(g){
  var gr=g.createLinearGradient(0,0,0,G);gr.addColorStop(0,'#060a14');gr.addColorStop(.5,'#0d1830');gr.addColorStop(1,'#1c2c4c');
  g.fillStyle=gr;g.fillRect(0,0,DW,G);
  g.fillStyle='#dfe7f7';
  for(i=0;i<st.length;i++){var s=st[i];g.fillRect(s[0],s[1],s[2],s[2])}
  if(lastW>=300){var mx=DW-78,my=38;g.fillStyle='#f4e7c3';g.beginPath();g.arc(mx,my,19,0,7);g.fill();g.fillStyle='#0d1830';g.beginPath();g.arc(mx+8,my-5,17,0,7);g.fill()}
  for(i=0;i<bld.length;i++){var b=bld[i];g.fillStyle='#0a1224';g.fillRect(b[0],G-b[2],b[1],b[2]);g.fillStyle='#ffd166';g.fillRect(b[0]+4,G-b[2]+7,3,4);g.fillRect(b[0]+b[1]-8,G-b[2]+12,3,4)}
  g.fillStyle='#141c2e';g.fillRect(0,G,DW,DH-G);
  g.fillStyle='#2b3652';g.fillRect(0,G,DW,2);
}
function roadLines(){
  x.fillStyle='#ffd166';var off=road%54;
  for(var j=-1;j<(DW/54|0)+2;j++)x.fillRect(j*54-off,G+26,26,4);
}
function hud(){
  var k=lastW/DW,fs=Math.min(24,Math.max(14,Math.round(14/k))),hs=Math.min(28,Math.max(16,Math.round(16/k)));
  x.fillStyle='rgba(232,236,255,.92)';x.font='bold '+fs+'px system-ui,sans-serif';x.textAlign='left';x.fillText('СЧЁТ '+pad(score),12,24);
  x.textAlign='right';x.fillText('РЕКОРД '+pad(best),DW-12,24);x.textAlign='left';
  if(phase===0){x.textAlign='center';x.font='bold '+hs+'px system-ui,sans-serif';x.fillStyle='#ffd166';x.fillText('ТАП — ПОЕХАЛИ',DW/2,G-78);x.textAlign='left'}
}
function draw(){
  x.drawImage(bgc,0,0,DW,DH);
  x.fillStyle='#cfe0ff';
  for(i=0;i<sn.length;i++){var f=sn[i];x.fillRect(f[0],f[1],f[2],f[2])}
  roadLines();
  for(var j=0;j<obs.length;j++)for(var k=0;k<obs[j].n;k++)x.drawImage(pumpCv,obs[j].x+k*58,G-58,42,58);
  var bob=phase===1&&carY>=G-CH-.5?Math.sin(t*26):0;
  x.drawImage(carCv,CX,carY+bob,CW,CH);hud();
}
function loop(ts){
  var dt=last?Math.min(.05,(ts-last)/1000):0;last=ts;t+=dt;
  for(i=0;i<sn.length;i++){var f=sn[i];f[1]+=f[3]*dt;f[0]-=(phase===1?speed*.02:3)*dt+Math.sin(t+i)*3*dt;if(f[1]>G){f[1]=-2;f[0]=Math.random()*DW}if(f[0]<-2)f[0]=DW+2;if(f[0]>DW+2)f[0]=-2}
  if(phase===1){
    speed=Math.min(720,280+score*.2);score+=speed*dt/10;road+=speed*dt;
    if(carY<G-CH||vy<0){vy+=2000*dt;carY+=vy*dt;if(carY>=G-CH){carY=G-CH;vy=0}}
    since+=speed*dt;if(since>=gap)spawn();
    for(i=0;i<obs.length;i++)obs[i].x-=speed*dt;
    obs=obs.filter(function(q){return q.x+(q.n-1)*58+34>0});
    if(hit())die();
    if(score>best)best=score;
  }
  draw();requestAnimationFrame(loop);
}
c.addEventListener('pointerdown',function(e){e.preventDefault();if(phase===0){restart()}else if(phase===1&&carY>=G-CH-.5){vy=-720}});
R.addEventListener('click',restart);
if(window.ResizeObserver)new ResizeObserver(size).observe(c);
window.addEventListener('resize',size);
size();draw();requestAnimationFrame(loop);
</script></body></html>`;
