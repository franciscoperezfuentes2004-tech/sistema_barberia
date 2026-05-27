const admin = require('firebase-admin');

if (!admin.apps.length) {
  try {
    // En producción (Vercel) usará variables de entorno, en local usa este objeto directo
    const serviceAccount = process.env.FIREBASE_SERVICE_ACCOUNT 
      ? JSON.parse(process.env.FIREBASE_SERVICE_ACCOUNT)
      : {
          "type": "service_account",
          "project_id": "proyectosventas-c0003",
          "private_key_id": "0f8744e96ff6e27ef44c7b651f51065baa918106",
          "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCxxAiyle1XNRoU\nscnHqQCHAiE0Ap2qGS8ObZzE+dG6apdd198YfIH9kErwvzkJ36tYxocR8mMm3SQu\n9ybQv11P3IOQT9lBLWIm8QZ6R77VJCYpkeMtiV87ZKTseWpY2v566UYvSSceOqoz\nxjKHFZNOT/joP9akarLjPbNA1yeQ4+1XvqJPxFtu6Q1c5cTTzaWc+6ai/s3Qmuhs\nz3k+tnmH4y43kJZN9JMiMEPsWwKBO5t34mcaim389l5l61REjmmKxUCmes/YTtpn\nxfBD5eoPuOsnV4MIQQCZeLGJP/dT4VSWLHRAn02rBZExclCXFOgEBUKqR5wndKcu\nYlMGVE6PAgMBAAECggEAAlzhY4PkDAQyYAykhELGG7h9OeRzaKOw2rq9pwEBEIuP\njn/d9CsRGUGcbv3DsGw1XVbQmP4rr9EOd/dXoAlJhrqFUlsIjFOIZsC0ytXlGFuR\nWRa0BwXSUVPho09r1Yy3oQLOKLbxGt3bWrI4N4M9M+dfnuRDIjVVQl/IE0UPPnkO\noOnl+hhRSvRQxycxcR7EB+sgzoEQmHs+4vk/kXBT9ayj8HETL0V3FtEAFS/vj/w5\nNM5OTPsNhwhmHLLPbgcinpRP2ZZot/de3oBHpIaR5FzJUKI2dWYKghfC0v6Cmvpl\nLL6zKaJ8c9v9+7LB08grsgLzEO0y9ZLhVCJ+4g3vNQKBgQDcVoVGB/hGM4nlqbCZ\nADmZ4tDC6sVofJA45j6GxzoUKQa4C9NalNVWvLWmpawaFZctML8f8Onww5tASwfd\nEH3FpYfpYGpo2KlUZxr11pFpzt3AxtySAbsHCyP+sVZLAZALUUyXxL+94JP8T0W5\nGhASKttcOwEfOKx/EU38v40E6wKBgQDOiZO7UtlII/Jo1VX4RpnojeRDMTGyOTLA\nRW6M/kpZNANlmek/vFcOO6mqPWunOz6D5h+/3RWguqfsyuMxXbBZzb/QyzRQFUeH\noJVsgpp4C2fLW/JCdOe/RPUuo64S+kzGNy/iBzAlzcfhy4iRr/p/VboONC+g0tn+\nnPgtjygD7QKBgQCdRPxXojSqFvtkfBxa+PgkSOrtVZmWHOLsWhtjJCzmWuo6z+YK\nD5W/FW8rBbGz5JlFXjftWo4Alf3ohCWWusCrJJ3ADFunfo5OelGaC487ULajdM3X\nQXj3bBJDJt0LKJBiI6Nh6MNbikLWotaHanzyGrj8OflxCYjGIdnif+7uBQKBgBPe\ntoDCErdXBf5B7/hnymzOIdS5Cd/sks5en6ke2cZFM8J1kTQZiYKMCOGg8Rdwoq4L\n2Kgbu/XvnzIvvrXEHrA1FCwhMJI3yd7pexaqZfQAnOa6nM758kW7e58WDiwzOmmj\na47iRCaO6pj1fNkPRhk0BSdSq/Zb8q8FKPcxG5dtAoGAY7s2+XQNCHH1sixS870F\nuGNJHKe/887Vv3rBqYo4hmK3WhP61N7isv+xdLSPqGhH33RqHtJUrFAL2uDxDiqS\nb66Am5e7w3mR4pAjYMBgqfd0wYicO1uSa0XGF1EBxfxKD8H+jPqNCVbzIzd47Rqc\nKIEmzG7HJEbZa6fCK9L8x/w=\n-----END PRIVATE KEY-----\n",
          "client_email": "firebase-adminsdk-fbsvc@proyectosventas-c0003.iam.gserviceaccount.com"
        };

    admin.initializeApp({
      credential: admin.credential.cert(serviceAccount),
      storageBucket: "proyectosventas-c0003.appspot.com"
    });
    console.log("Conexión exitosa a Firebase Firestore y Storage.");
  } catch (error) {
    console.error("Error inicializando Firebase:", error);
  }
}

const db = admin.firestore();
const bucket = admin.storage().bucket();

module.exports = { db, bucket };
