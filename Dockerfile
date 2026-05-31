# ═══════════════════════════════════════════════════════════════════
#  DOCKERFILE — Servidor Apache + PHP 8.2 para Render
# ═══════════════════════════════════════════════════════════════════
#
#  Este archivo le dice a Render EXACTAMENTE cómo construir tu servidor.
#  Es como una "receta de cocina" que se ejecuta paso a paso.
#
#  USO EN RENDER:
#  ──────────────
#  1. Sube tu proyecto a GitHub.
#  2. En Render, crea un "Web Service" → elige "Docker".
#  3. Apunta al repositorio de GitHub.
#  4. Render detectará este Dockerfile y construirá el servidor automáticamente.
#
#  RESULTADO FINAL:
#  ────────────────
#  Un servidor Apache con PHP 8.2, extensiones mysqli y pdo_mysql,
#  y permisos de escritura en la carpeta de imágenes.
# ═══════════════════════════════════════════════════════════════════

# ─── PASO 1: Imagen base ──────────────────────────────────────────
# Usamos la imagen oficial de PHP 8.2 con Apache preinstalado.
# Esta imagen ya viene con un servidor web listo para servir archivos.
FROM php:8.2-apache

# ─── PASO 2: Instalar extensiones de MySQL ────────────────────────
# docker-php-ext-install es un comando especial de esta imagen oficial
# que compila e instala extensiones de PHP de forma limpia.
# - mysqli:    extensión clásica para conectar PHP con MySQL (la que usamos)
# - pdo_mysql: extensión alternativa por si el comprador prefiere PDO
RUN docker-php-ext-install mysqli pdo_mysql

# ─── PASO 3: Habilitar mod_rewrite de Apache ──────────────────────
# mod_rewrite permite usar URLs amigables (por si en el futuro
# quieres rutas como /servicios en lugar de /sistema/obtener_datos.php)
RUN a2enmod rewrite

# ─── PASO 4: Configurar Apache para permitir .htaccess ────────────
# Por defecto Apache ignora los archivos .htaccess.
# Esta configuración lo habilita dentro de /var/www/html/
RUN echo '<Directory /var/www/html/>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# ─── PASO 5: Copiar todo el proyecto al servidor ──────────────────
# Copia TODOS los archivos del directorio actual (donde está el Dockerfile)
# hacia la raíz del servidor web de Apache.
# NOTA: Como el Dockerfile está dentro de /sistema/, copiamos desde
# el contexto del build (que será la raíz del proyecto).
COPY . /var/www/html/

# ─── PASO 6: Crear la carpeta de imágenes si no existe ────────────
# Nos aseguramos de que la carpeta img/ exista dentro de sistema/
RUN mkdir -p /var/www/html/sistema/img

# ─── PASO 7: Dar permisos de escritura a la carpeta de imágenes ───
# Apache corre como el usuario "www-data". Si este usuario no es dueño
# de la carpeta img/, PHP no podrá guardar las imágenes subidas.
# chown = cambiar dueño. -R = recursivo (incluye subcarpetas).
RUN chown -R www-data:www-data /var/www/html/sistema/img

# ─── PASO 8: Dar permisos 755 a la carpeta de imágenes ────────────
# 7 = dueño puede leer+escribir+ejecutar
# 5 = grupo puede leer+ejecutar
# 5 = otros pueden leer+ejecutar
RUN chmod -R 755 /var/www/html/sistema/img

# ─── PASO 9: Exponer el puerto 80 ─────────────────────────────────
# Le dice a Docker (y a Render) que este contenedor escucha en el puerto 80.
# Render redirigirá automáticamente el tráfico HTTPS hacia este puerto.
EXPOSE 80

# ─── PASO 10: Arrancar Apache en primer plano ─────────────────────
# "-D FOREGROUND" mantiene Apache corriendo sin cerrarse.
# Docker necesita que el proceso principal NO se desconecte (no daemon).
CMD ["apache2-foreground"]
