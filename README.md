# Barbería — Documentación del Proyecto Frontend

## Estructura de Carpetas

```
barberia/
├── index.html                  ← Entrada principal (redirige a pages/)
│
├── pages/                      ← Pantallas de la app
│   ├── booking.html            ← ✅ Paso 1: Servicios + Barbero
│   ├── calendar.html           ← ⏳ Paso 2: Fecha y Hora
│   └── confirmation.html       ← ⏳ Paso 3: Confirmación (pendiente)
│
├── assets/
│   ├── css/
│   │   └── main.css            ← Design tokens + animaciones custom
│   ├── js/
│   │   ├── booking.js          ← Lógica de selección (Step 1)
│   │   ├── calendar.js         ← (pendiente)
│   │   └── confirmation.js     ← (pendiente)
│   ├── img/
│   │   ├── barbers/            ← Fotos de los barberos (carlos.png, miguel.png, sofia.png)
│   │   ├── services/           ← Imágenes de servicios (pendiente)
│   │   └── hero-bg.png         ← Imagen de fondo hero
│   └── fonts/                  ← Fuentes locales (si se usan en producción)
│
└── components/                 ← Fragmentos HTML reutilizables (pendiente)
    ├── header.html
    └── footer.html
```

## Stack Tecnológico

| Capa        | Tecnología                              |
|-------------|------------------------------------------|
| Estructura  | HTML5 semántico (WAI-ARIA)              |
| Estilos     | Tailwind CSS CDN + `assets/css/main.css` |
| Tipografía  | Google Fonts (Playfair Display + Inter)  |
| Scripts     | Vanilla JS (ES2020+, sin frameworks)     |
| Servidor    | XAMPP (Apache local, PHP en siguientes fases) |

## Paleta de Color

| Token          | Valor     | Uso                     |
|----------------|-----------|-------------------------|
| `--gold`       | `#C9A84C` | Acento principal        |
| `--gold-light` | `#E8C97A` | Hover/gradiente claro   |
| `--gold-dark`  | `#9A7B32` | Botones / bordes activos|
| `--charcoal`   | `#0F0F0F` | Fondo base              |
| `--surface`    | `#181818` | Fondo cards             |
| `--surface-2`  | `#222222` | Cards hover             |

## Flujo de Reserva (3 pasos)

```
[booking.html] → [calendar.html] → [confirmation.html]
  Servicios         Fecha/Hora        Resumen + Pago
  + Barbero
```
