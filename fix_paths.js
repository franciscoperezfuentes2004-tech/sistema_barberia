const fs = require('fs');
const path = require('path');
const dir = 'api';
fs.readdirSync(dir).forEach(f => {
  const p = path.join(dir, f);
  if (p.endsWith('.js')) {
    let c = fs.readFileSync(p, 'utf8');
    c = c.replace(/'\.\/config\//g, "'./_config/");
    fs.writeFileSync(p, c);
  }
});
