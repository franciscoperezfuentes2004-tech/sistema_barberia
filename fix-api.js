const fs = require('fs');
const path = require('path');

function walk(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    file = path.join(dir, file);
    const stat = fs.statSync(file);
    if (stat && stat.isDirectory() && !file.includes('node_modules') && !file.includes('.git') && !file.includes('.gemini') && !file.includes('api')) {
      results = results.concat(walk(file));
    } else {
      if (file.endsWith('.html') || file.endsWith('.js') || file.endsWith('.css')) {
        results.push(file);
      }
    }
  });
  return results;
}

const files = walk('.');

files.forEach(f => {
  let content = fs.readFileSync(f, 'utf8');
  let original = content;

  // Replace API constant logic
  content = content.replace(/const API = \(\(\) => \{[\s\S]*?\}\)\(\);/g, "const API = '/api';");

  // Replace any lingering broken accents
  content = content.replace(/é/g, 'é')
                   .replace(/á/g, 'á')
                   .replace(/Ã\xAD/g, 'í')
                   .replace(/ó/g, 'ó')
                   .replace(/ú/g, 'ú')
                   .replace(/ñ/g, 'ñ')
                   .replace(/—/g, '—')
                   .replace(/¿/g, '¿')
                   .replace(/¡/g, '¡')
                   .replace(/É/g, 'É')
                   .replace(//g, '')
                   .replace(/\ufffd/g, ''); // Fix FFFD character

  if (content !== original) {
    fs.writeFileSync(f, content, 'utf8');
    console.log('Fixed ' + f);
  }
});
