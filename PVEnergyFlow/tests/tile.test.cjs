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

test('detail values use their units and open their own archive',()=>{
  const dom=render({pvTotal:{value:40377.7,label:'PV-Erzeugung gesamt',unit:'kWh',archive:true,history:[{t:1700000000,v:40377.7}]},temperature:{value:27.9,label:'Speichertemperatur',unit:'°C',archive:false}}),d=dom.window.document;
  assert.match(d.querySelector('#pvTotal').textContent,/40\.377,7 kWh/);
  assert.match(d.querySelector('#temperature').textContent,/27,9 °C/);
  d.querySelector('#pvTotal').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true}));
  assert.equal(d.querySelector('#title').textContent,'PV-Erzeugung gesamt · letzte 24 Stunden (kWh)');
  assert.equal(d.querySelectorAll('#chart circle').length,1);
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

test('additional energy readings distinguish available energy, health and optional daily totals',()=>{
  const dom=render({batteryAvailable:{id:44710,value:8.847,label:'Speicher verfügbar',unit:'kWh'},batteryCapacity:{id:20923,value:9.2,unit:'kWh'},batteryHealth:{id:47552,value:96,unit:'%'},pvToday:{id:0,value:null,unit:'kWh'},importToday:{id:12345,value:2.3,unit:'kWh',label:'Netzbezug heute',history:[],archive:false}}),d=dom.window.document;
  const field=key=>d.querySelector('.reading[data-key="'+key+'"]');
  assert.equal(field('batteryAvailable').querySelector('strong').textContent,'8,8 kWh');
  assert.equal(field('batteryCapacity').querySelector('strong').textContent,'9,2 kWh');
  assert.equal(field('batteryHealth').querySelector('strong').textContent,'96 %');
  assert.equal(field('pvToday').hidden,true);
  assert.equal(field('importToday').hidden,false);
  field('importToday').click();
  assert.match(d.querySelector('#title').textContent,/Netzbezug heute/);
  assert.equal(field('evTotal').hidden,true);
  dom.window.close();
});
