# SIBI - Sistema de Gestión Bibliotecaria

Proyecto web para la biblioteca del IEST La Recoleta. Sirve para buscar libros en el catálogo, controlar ejemplares físicos y registrar préstamos, renovaciones y devoluciones con cálculo automático de mora.

Está hecho en PHP 8 (PDO), MySQL y JavaScript puro, pensado para correr en XAMPP o en un hosting compartido sin instalar librerías externas.

## Funcionalidades principales

- Acceso con roles separados (Administrador, Bibliotecario y Lector) y contraseñas cifradas con `password_hash()`.
- Catálogo con buscador por título, autor o categoría, vista de ejemplares disponibles y subida de portadas.
- Mostrador para registrar préstamos, devoluciones y renovaciones aplicando las reglas de días, tope de renovaciones y mora por retraso.
- Gestión de lectores y personal: registro, edición de datos, historial de préstamos y restablecimiento de contraseña desde el rol administrador.
- Edición de perfil propio (`perfil.php`) con cambio de clave y foto de perfil en proporción 1:1.
- Panel de administración con resumen general, cobro o condonación de moras, configuración de parámetros y descarga de reportes en CSV.

## Tecnologías

- PHP 8 + PDO
- MySQL / MariaDB (motor InnoDB)
- HTML5, CSS3 y JavaScript
- Apache (XAMPP)

## Estructura de carpetas

```text
sibi-biblioteca/
├── index.php              # Inicio de sesión
├── catalogo.php           # Catálogo y búsqueda
├── libro.php              # Detalle del libro y ejemplares
├── mostrador.php          # Préstamos, renovaciones y devoluciones
├── catalogo_admin.php     # Mantenimiento de libros, autores y categorías
├── lectores.php           # Gestión de lectores y personal
├── perfil.php             # Perfil propio, foto y cambio de contraseña
├── panel.php              # Panel del administrador y configuración
├── exportar_csv.php       # Exportación de préstamos a CSV
├── config/db.php          # Conexión a la base de datos
├── php/                   # Lógica del servidor, sesiones y plantilla
├── css/ y js/             # Estilos y scripts del cliente
├── assets/img/            # Logos, portadas y avatares
├── sql/database.sql       # Tablas y datos de prueba
├── demo/index.html        # Demo estática para probar sin servidor
└── docs/                  # Informe y diagramas (.drawio)
```

## Instalación en local (XAMPP)

1. Copia la carpeta `sibi-biblioteca` dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL en XAMPP.
3. Entra a `http://localhost/phpmyadmin`, abre la pestaña Importar y sube `sql/database.sql`.
4. Si tu MySQL tiene contraseña, cámbiala en `config/db.php`.
5. Abre `http://localhost/sibi-biblioteca/` en el navegador.

## Despliegue en hosting compartido (ej. InfinityFree)

Si vas a subirlo a un servidor gratuito:
- Crea primero la base de datos desde el panel del hosting.
- Quita las líneas `CREATE DATABASE` y `USE sibi_biblioteca;` antes de importar el archivo SQL en phpMyAdmin.
- Cambia `$DB_HOST`, `$DB_NAME`, `$DB_USER` y `$DB_PASS` en `config/db.php` por los datos de tu cuenta.

## Cuentas de prueba

Todas vienen con la clave `123456`:

| Rol | Correo | Clave |
| :--- | :--- | :--- |
| Administrador | `admin@sibi.edu.pe` | `123456` |
| Bibliotecario | `biblio@sibi.edu.pe` | `123456` |
| Lector | `mateo@sibi.edu.pe` | `123456` |

En `USUARIOS.md` está la lista completa de cuentas y códigos de ejemplares de ejemplo.
