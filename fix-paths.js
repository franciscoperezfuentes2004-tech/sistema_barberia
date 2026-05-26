const fs = require('fs');

['pages/admin.html', 'pages/booking.html', 'pages/calendar.html', 'pages/confirm.html', 'pages/login.html', 'pages/staff.html', 'assets/js/booking.js', 'assets/js/api.js'].forEach(p => {
  if (fs.existsSync(p)) {
    let content = fs.readFileSync(p, 'utf8');
    let fixed = content.replace(/const API = '\/api';/g, "const API = '../api';");
    fixed = fixed.replace(/const API_BASE = '\/api';/g, "const API_BASE = '../api';");
    if (content !== fixed) {
      fs.writeFileSync(p, fixed, 'utf8');
      console.log('Fixed path in ' + p);
    }
  }
});
