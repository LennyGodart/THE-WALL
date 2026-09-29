import {readFileSync} from 'fs';
// Filamentbedarf aus den STL-Dateien. Laeuft aus jedem Ordner: node hardware/vol.mjs
function volume(f){
  const txt=readFileSync(new URL(f, import.meta.url),'utf8');
  const nums=[]; let v=0; const re=/vertex\s+(-?[\d.eE+-]+)\s+(-?[\d.eE+-]+)\s+(-?[\d.eE+-]+)/g; let m;
  while((m=re.exec(txt))) nums.push([+m[1],+m[2],+m[3]]);
  for(let i=0;i+2<nums.length;i+=3){const [a,b,c]=[nums[i],nums[i+1],nums[i+2]];
    v += (a[0]*(b[1]*c[2]-c[1]*b[2]) - b[0]*(a[1]*c[2]-c[1]*a[2]) + c[0]*(a[1]*b[2]-b[1]*a[2]))/6;
  }
  return Math.abs(v)/1000;
}
const sets={
  'Standfuss, 2 Teile':['standfuss/fuss_links.stl','standfuss/fuss_rechts.stl'],
  'Rahmen, 6 Teile':['archiv-rahmen/leiste_lang.stl','archiv-rahmen/leiste_lang.stl','archiv-rahmen/leiste_kurz.stl','archiv-rahmen/leiste_kurz.stl','archiv-rahmen/rueckplatte.stl','archiv-rahmen/rueckplatte2.stl'],
};
console.log('Variante              Volumen    Filament   bei 22 EUR/kg');
for(const [name,files] of Object.entries(sets)){
  let cm3=0; for(const f of files) cm3+=volume(f);
  const g=cm3*1.24*0.45;
  console.log(name.padEnd(22)+(cm3.toFixed(0)+' cm3').padEnd(11)+(g.toFixed(0)+' g').padEnd(11)+(g/1000*22).toFixed(2)+' EUR');
}
