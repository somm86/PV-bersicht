import json, pathlib, subprocess, tempfile
root = pathlib.Path(__file__).resolve().parents[1]
values = {}
for key, n in dict(pv=6000,battery=1000,grid=-2000,ev=500,soc=89,car=61).items():
    values[key] = dict(value=n,label=key,archive=True,history=[dict(t=1700000000-3600,v=n),dict(t=1700000000,v=n+10)])
page = (root/'assets/tile.html').read_text().replace('/*__DATA__*/null', json.dumps(dict(updated=1700000000,values=values)))
checks = '''<script>
try {
function check(ok,msg){if(!ok)throw Error(msg);}
check(document.querySelector('#house .value').textContent==='2,5 kW','Hausbilanz');
check(document.querySelector('#batteryLine').classList.contains('reverse'),'Laderichtung');
check(document.querySelector('#gridLine').classList.contains('reverse'),'Einspeiserichtung');
document.querySelector('#pv').dispatchEvent(new MouseEvent('click',{bubbles:true}));check(document.querySelector('dialog').open,'Dialog');check(document.querySelectorAll('#chart circle').length===2,'Archivpunkte');
v.pv.history=[];chart('pv');check(document.querySelector('#message').textContent.includes('Keine Archivwerte'),'Leeres Archiv');
v.pv.archive=false;chart('pv');check(document.querySelector('#message').textContent.includes('nicht aktiv'),'Archiv deaktiviert');
chart('battery');document.querySelector('#extra button').click();check(document.querySelector('#title').textContent.startsWith('soc'),'SOC Verlauf');
document.body.dataset.test='PASS';
}catch(e){document.body.dataset.test='FAIL: '+e.message;}
</script>'''
with tempfile.TemporaryDirectory() as d:
    f=pathlib.Path(d)/'test.html';f.write_text(page+checks)
    result=subprocess.run(['chromium','--headless','--user-data-dir='+d+'/profile','--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--disable-background-networking','--no-first-run','--disable-extensions','--timeout=10000','--dump-dom',f.as_uri()],capture_output=True,text=True,timeout=30)
    if 'data-test="PASS"' not in result.stdout:
        print(result.stderr[-2000:]);print(result.stdout[-2000:]);raise SystemExit(1)
    print('PASS: Bilanz, Flussrichtung, Dialog, Archivpunkte, fehlendes Archiv, SOC-Verlauf')
