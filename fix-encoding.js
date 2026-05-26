const fs = require('fs');
const path = require('path');

const dict = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ','¿','¡','—','“','”','‘','’','…'];
const replacements = {};

for (const char of dict) {
  replacements[Buffer.from(char, 'utf8').toString('binary')] = char;
}
replacements['✂️'] = '✂️';
replacements[''] = '';

function walk(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    file = path.join(dir, file);
    const stat = fs.statSync(file);
    if (stat && stat.isDirectory() && !file.includes('node_modules') && !file.includes('.git') && !file.includes('.gemini')) {
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
let changed = 0;

files.forEach(f => {
  let content = fs.readFileSync(f, 'utf8');
  let original = content;

  for (const [bad, good] of Object.entries(replacements)) {
    content = content.split(bad).join(good);
  }

  if (content !== original) {
    fs.writeFileSync(f, content, 'utf8');
    changed++;
  }
});

console.log('Fixed ' + changed + ' files');
