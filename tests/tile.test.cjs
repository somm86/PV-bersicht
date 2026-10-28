const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const PHPParser = require('php-parser');
const root = path.join(__dirname, '..');
function render(overrides = {}) {
  const values = {};
  for (const [key,value] of Object.entries({pv:6000,battery:1000,grid:-2000,ev:500,soc:89,car:61})) {
    values[key] = {value,label:key,archive:true,history:[{t:1700000000-3600,v:value},{t:1700000000,v:value+10}]};
  }
  Object.assign(values, overrides);
  const html = fs.readFileSync(path.join(root,'assets/tile.html'),'utf8').replace('/*__DATA__*/null',JSON.stringify({updated:1700000000,values}));
  // jsdom lacks native dialog methods: this checks app logic, not browser dialog support.
  const dom = new JSDOM(html, {runScripts:'dangerously',beforeParse(window) {
    window.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
    window.HTMLDialogElement.prototype.close=function(){this.open=false;};
  }});
  return dom;
}
test('PHP source parses using PHP 8 grammar', () => {
  const parser = new PHPParser({parser:{version:'8.0',suppressErrors:false}});
  assert.ok(parser.parseCode(fs.readFileSync(path.join(root,'tile.php'),'utf8')));
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
