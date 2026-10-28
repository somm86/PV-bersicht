const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const PHPParser = require('php-parser');
const root = path.join(__dirname, '../..');
function render(overrides = {}) {
  const values = {};
  for (const [key,value] of Object.entries({pv:6000,battery:1000,grid:-2000,ev:500,soc:89,car:61})) {
    values[key] = {value,label:key,archive:true,history:[{t:1700000000-3600,v:value},{t:1700000000,v:value+10}]};
  }
  Object.assign(values, overrides);
  const html = fs.readFileSync(path.join(root,'PVEnergyFlow/assets/tile.html'),'utf8').replace('/*__DATA__*/null',JSON.stringify({updated:1700000000,values}));
  // jsdom lacks native dialog methods: this checks app logic, not browser dialog support.
  const dom = new JSDOM(html, {runScripts:'dangerously',beforeParse(window) {
    window.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
    window.HTMLDialogElement.prototype.close=function(){this.open=false;};
  }});
  return dom;
}
test('PHP source parses using PHP 8 grammar', () => {
  const parser = new PHPParser({parser:{version:'8.0',suppressErrors:false}});
  for (const file of ['tile.php','PVEnergyFlow/module.php']) {
    assert.ok(parser.parseCode(fs.readFileSync(path.join(root,file),'utf8')), file);
  }
});
test('house balance, charge and export directions', () => {
  const dom=render(),d=dom.window.document;
  assert.equal(d.querySelector('#house .value').textContent,'2,5 kW');
  assert.ok(d.querySelector('#batteryLine').classList.contains('reverse'));
  assert.ok(d.querySelector('#gridLine').classList.contains('reverse'));
  dom.window.close();
});
test('discharge, grid import and zero flow', () => {
  const dom=render({battery:{value:-1000},grid:{value:2000},ev:{value:0}}),d=dom.window.document;
  assert.equal(d.querySelector('#batteryLine').classList.contains('reverse'),false);
  assert.equal(d.querySelector('#gridLine').classList.contains('reverse'),false);
  assert.equal(d.querySelector('#evLine').classList.contains('active'),false);
  assert.match(d.querySelector('#battery .sub').textContent,/Entladen/);
  assert.match(d.querySelector('#grid .sub').textContent,/Bezug/);
  dom.window.close();
});
test('archive popup, points, SOC switch and close', () => {
  const dom=render(),d=dom.window.document;
  d.querySelector('#battery').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.ok(d.querySelector('dialog').open);
  assert.equal(d.querySelectorAll('#chart circle').length,2);
  d.querySelector('#extra button').click();
  assert.match(d.querySelector('#title').textContent,/^soc/);
  d.querySelector('#close').click();
  assert.equal(d.querySelector('dialog').open,false);
  dom.window.close();
});
for(const [name,entry,message] of [
  ['empty archive',{value:0,history:[],archive:true},/Keine Archivwerte/],
  ['disabled archive',{value:0,history:[],archive:false},/nicht aktiv/],
  ['archive error',{value:0,history:[],error:'Archiv derzeit nicht lesbar'},/nicht lesbar/]
]) test(name,()=>{
  const dom=render({pv:{label:'PV',...entry}}),d=dom.window.document;
  d.querySelector('#pv').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.match(d.querySelector('#message').textContent,message);
  assert.equal(d.querySelector('#chart').style.display,'none');
  dom.window.close();
});
test('missing input suppresses house estimate; inconsistent balance warns',()=>{
  let dom=render({pv:{value:null}});
  assert.equal(dom.window.document.querySelector('#house .value').textContent,'—');
  dom.window.close();
  dom=render({pv:{value:0}});
  assert.match(dom.window.document.querySelector('#foot').textContent,/unplausibel/);
  dom.window.close();
});

test('Symcon library discovery and configuration reference an implemented module',()=>{
  const read=p=>JSON.parse(fs.readFileSync(path.join(root,p),'utf8'));
  const library=read('library.json'), module=read('PVEnergyFlow/module.json'), form=read('PVEnergyFlow/form.json');
  const guid=/^\{[0-9A-F]{8}(-[0-9A-F]{4}){3}-[0-9A-F]{12}\}$/i;
  assert.match(library.id,guid);
  assert.match(module.id,guid);
  assert.notEqual(library.id,module.id);
  assert.equal(module.type,3);
  const source=fs.readFileSync(path.join(root,'PVEnergyFlow/module.php'),'utf8');
  assert.ok(source.includes('class '+module.name+' extends IPSModule'));
  for(const element of form.elements.filter(e=>e.name)) {
    assert.ok(source.includes("RegisterPropertyInteger('"+element.name+"'"),element.name);
  }
  for(const action of form.actions) assert.ok(action.onClick.startsWith(module.prefix+'_Update('));
  assert.ok(fs.existsSync(path.join(root,'PVEnergyFlow/assets/tile.html')));
});

test('every published top-level directory is a valid Symcon module',()=>{
  // Hidden Git/CI directories and ignored development dependencies are not modules.
  const directories=new Set(fs.readdirSync(root,{withFileTypes:true})
    .filter(e=>e.isDirectory()&&!e.name.startsWith('.')&&e.name!=='node_modules').map(e=>e.name));
  assert.ok(directories.has('PVEnergyFlow'));
  for(const directory of directories) {
    assert.ok(fs.existsSync(path.join(root,directory,'module.json')),directory+' must contain module.json');
  }
});

test('native tile callback updates its isolated frame',()=>{
  const dom=new JSDOM('<iframe title="PV Energiefluss"></iframe>'+fs.readFileSync(path.join(root,'PVEnergyFlow/assets/native.html'),'utf8'),{runScripts:'dangerously'});
  const frame=dom.window.document.querySelector('iframe');
  dom.window.handleMessage('<html><body>Aktualisiert</body></html>');
  assert.equal(frame.srcdoc,'<html><body>Aktualisiert</body></html>');
  dom.window.handleMessage(null);
  assert.equal(frame.srcdoc,'<html><body>Aktualisiert</body></html>');
  dom.window.close();
});

test('energy counters open their own raw counter graph rather than the power graph',()=>{
  const counters={};
  for(const key of ['pvTotal','gridImport','gridExport','evSession'])counters[key]={value:40377.7,label:key,unit:'kWh',kind:'counter',archive:true,history:[{t:1699996400,v:40375.2},{t:1700000000,v:40377.7}]};
  const dom=render(counters),d=dom.window.document;
  assert.match(d.querySelector('#pvTotal').textContent,/Zählerstand · 40\.377,7 kWh/);
  for(const key of Object.keys(counters)) {
    const metric=d.querySelector('#'+key);
    metric.dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
    assert.match(d.querySelector('#title').textContent,new RegExp('^'+key));
    assert.match(d.querySelector('#message').textContent,/Archivierte Zählerstände/);
    assert.match(d.querySelector('#message').textContent,/Zuwachs.*2,5 kWh/);
    assert.equal(d.querySelectorAll('#chart circle').length,2);
    assert.equal(metric.getAttribute('tabindex'),'0');
    d.querySelector('#close').click();
    metric.dispatchEvent(new dom.window.KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
    assert.match(d.querySelector('#title').textContent,new RegExp('^'+key));
    d.querySelector('#close').click();
  }
  d.querySelector('#ev .value').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.equal(d.querySelector('dialog').open,true);
  assert.equal(d.querySelector('#house').textContent.trim(),'2,5 kW');
  dom.window.close();
});
test('charge bars reflect SOC and clamp invalid percentages; missing details stay empty',()=>{
  const dom=render({soc:{value:150},car:{value:-10}}),d=dom.window.document;
  assert.equal(d.querySelector('#batteryBar').getAttribute('width'),'170');
  assert.equal(d.querySelector('#carBar').getAttribute('width'),'0');
  assert.equal(d.querySelector('#evSession').textContent,'Ladezyklus · —');
  assert.equal(d.querySelectorAll('symbol').length,5);
  dom.window.close();
});

test('removed readings stay absent; configured daily values remain optional',()=>{
  const dom=render({batteryAvailable:{id:44710,value:8.847,unit:'kWh'},temperature:{value:27.9},pvToday:{id:0,value:null,unit:'kWh'},importToday:{id:12345,value:2.3,unit:'kWh',label:'Netzbezug heute',history:[],archive:false}}),d=dom.window.document;
  for(const key of ['temperature','batteryAvailable','batteryCapacity','batteryHealth','evTotal']) {
    assert.equal(d.querySelector('#'+key),null);
    assert.equal(d.querySelector('[data-key="'+key+'"]'),null);
  }
  assert.equal(d.querySelector('[data-key="pvToday"]').hidden,true);
  d.querySelector('[data-key="importToday"]').click();
  assert.match(d.querySelector('#title').textContent,/Netzbezug heute/);
  dom.window.close();
  const empty=render();
  assert.equal(empty.window.document.querySelector('.details').hidden,true);
  empty.window.close();
});

test('counter resets split the curve and bounded data is clearly marked',()=>{
  const dom=render({evSession:{value:1.5,label:'Ladezyklus',unit:'kWh',kind:'counter',limited:true,archive:true,history:[{t:1699992800,v:6},{t:1699996400,v:0},{t:1700000000,v:1.5}]}}),d=dom.window.document;
  d.querySelector('#evSession').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.match(d.querySelector('#message').textContent,/Rücksetzung erkannt/);
  assert.match(d.querySelector('#message').textContent,/2000 Archivwerte begrenzt/);
  assert.doesNotMatch(d.querySelector('#message').textContent,/Zuwachs/);
  assert.equal(d.querySelectorAll('#chart polyline').length,2);
  dom.window.close();
});
test('wallbox displays phases and unconverted source time',()=>{
  let dom=render({phases:{value:3},remaining:{value:5400,display:'1 h 30 min'}}),d=dom.window.document;
  assert.equal(d.querySelector('#evPhases').textContent,'3~');
  assert.equal(d.querySelector('#evRemaining').textContent,'Restladezeit · 1 h 30 min');
  d.querySelector('#evRemaining').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.equal(d.querySelector('dialog').open,false);
  dom.window.close();
  dom=render({phases:{value:7},remaining:{value:null}});d=dom.window.document;
  assert.equal(d.querySelector('#evPhases').textContent,'—');
  assert.equal(d.querySelector('#evRemaining').textContent,'Restladezeit · —');
  dom.window.close();
});

test('remaining time preserves formatted values and raw integers without adding units',()=>{
 for(const [entry,expected] of [[{value:90},'90'],[{value:0,display:'Fertig'},'Fertig'],[{value:130,display:'01:30'},'01:30']]) {
  const dom=render({remaining:entry}),d=dom.window.document;
  assert.equal(d.querySelector('#evRemaining').textContent,'Restladezeit · '+expected);
  dom.window.close();
 }
});

test('phase and remaining-time section is visible only above 100 W',()=>{
 for(const [power,visible] of [[null,false],[0,false],[-500,false],[99,false],[100,false],[100.1,true],[11000,true]]) {
  const dom=render({ev:{value:power},phases:{value:3},remaining:{value:90,display:'90'}}),d=dom.window.document;
  assert.equal(d.querySelector('#chargingInfo').style.display!=='none',visible,String(power)+' W');
  assert.ok(d.querySelector('#chargingInfo #evPhases'));
  assert.ok(d.querySelector('#chargingInfo #evRemaining'));
  assert.ok(d.querySelector('#evSession'));
  dom.window.close();
 }
});

test('PV, grid and wallbox cards and connections respect their idle thresholds',()=>{
 for(const [key,cases] of [['pv',[[null,true],[-1,false],[0,false],[0.99,false],[1,true],[5,true]]],['grid',[[null,true],[-10.01,true],[-10,false],[0,false],[10,false],[10.01,true]]],['ev',[[null,true],[-1,false],[0,false],[99.99,false],[100,true],[100.01,true]]]]) {
  for(const [power,visible] of cases) {
   const dom=render({[key]:{value:power}}),d=dom.window.document;
   for(const id of [key,key+'Wire',key+'Line',key+'Port'])assert.equal(d.getElementById(id).style.display!=='none',visible,key+' '+power+' W: '+id);
   assert.notEqual(d.querySelector('#house').style.display,'none');
   dom.window.close();
  }
 }
});

test('import and export daily counters show kWh bars and distinguish missing from zero',()=>{
 for(const key of ['gridImport','gridExport']) {
  const dom=render({[key]:{value:46133.19,label:key,unit:'kWh',kind:'dailyCounter',archive:true,days:[{day:'2026-10-26',label:'26.10.'},{day:'2026-10-27',label:'27.10.'},{day:'2026-10-28',label:'28.10.',today:true}],history:[{t:1,day:'2026-10-26',v:0},{t:2,day:'2026-10-28',v:12.75}]}}),d=dom.window.document;
  d.querySelector('#'+key).dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.match(d.querySelector('#title').textContent,/Tageswerte \(kWh\)/);
  assert.equal(d.querySelectorAll('.daily-bar').length,2);
  assert.equal(d.querySelectorAll('#chart polyline').length,0);
  assert.match(d.querySelector('#chart').textContent,/12,75/);
  assert.match(d.querySelector('#chart').textContent,/28\.10\.\*/);
  assert.match(d.querySelector('#chart').textContent,/—/);
  assert.match(d.querySelector('#message').textContent,/Heute vorläufig/);
  dom.window.close();
 }
});
test('daily counters never plot unconfigured aggregation as consumption',()=>{
 const dom=render({gridImport:{label:'Bezug',kind:'dailyCounter',archive:true,error:'Diese Variable muss im Archive Control als Zähler archiviert sein.',history:[],days:[]}}),d=dom.window.document;
 d.querySelector('#gridImport').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
 assert.match(d.querySelector('#message').textContent,/als Zähler/);
 assert.equal(d.querySelector('#chart').style.display,'none');
 assert.equal(d.querySelectorAll('.daily-bar').length,0);
 dom.window.close();
});
