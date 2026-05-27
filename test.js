const { db } = require('./api/config/db.js');
db.collection('ajustes').doc('global').get()
  .then(doc => { console.log(JSON.stringify(doc.data(), null, 2)); process.exit(0); })
  .catch(console.error);
