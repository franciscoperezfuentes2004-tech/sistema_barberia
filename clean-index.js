const fs = require('fs');

let content = fs.readFileSync('index.html', 'utf8');

// Replace the duplicate logic for stats
content = content.replace(/if \(d\.stat_exp\) document\.getElementById\('stat-exp'\)\.textContent = d\.stat_exp;\s+if \(d\.stat_clientes\) document\.getElementById\('stat-clientes'\)\.textContent = d\.stat_clientes;\s+if \(d\.stat_rating\) \{\s+document\.getElementById\('stat-rating'\)\.textContent = d\.stat_rating\.includes\('★'\) \? d\.stat_rating : d\.stat_rating \+ '★';\s+\}/g, '');
content = content.replace(/if \(d\.stat_exp\) document\.getElementById\('stat-exp'\)\.textContent = d\.stat_exp;\s+if \(d\.stat_clientes\) document\.getElementById\('stat-clientes'\)\.textContent = d\.stat_clientes;\s+if \(d\.stat_rating\) \{\s+document\.getElementById\('stat-rating'\)\.textContent = d\.stat_rating\.includes\('~\.'\) \? d\.stat_rating : \s*d\.stat_rating \+ '~\.';/g, '');

content = content.replace(/if \(d\.stat_exp\) document\.getElementById\('stat-exp'\)\.textContent = d\.stat_exp;\s+if \(d\.stat_clientes\) document\.getElementById\('stat-clientes'\)\.textContent = d\.stat_clientes;\s+if \(d\.stat_rating\) document\.getElementById\('stat-rating'\)\.textContent = d\.stat_rating;/g, `if (d.stat_exp) document.getElementById('stat-exp').textContent = d.stat_exp;
            if (d.stat_clientes) document.getElementById('stat-clientes').textContent = d.stat_clientes;
            if (d.stat_rating) document.getElementById('stat-rating').textContent = d.stat_rating.includes('★') ? d.stat_rating : d.stat_rating + '★';`);

content = content.replace(/4\.9[^<]+/g, '4.9★');

fs.writeFileSync('index.html', content, 'utf8');
console.log('Fixed index.html!');
