# SIBI — Sistema de Gestión Bibliotecaria (IEST La Recoleta · v6)

Sistema web completo en **PHP 8 + PDO + MySQL**, acompañado de la **demo HTML autónoma v6** (`demo/index.html`), los **diagramas UML y DER editables en draw.io** (`docs/SIBI-Diagramas-UML-DER.drawio`) y el **Informe Final v6 con todos los diagramas integrados** (`docs/Informe-Final-SIBI-IEST-La-Recoleta-v6.docx`).

## Qué incluye esta versión (v6)
- **Identidad visual oficial de SIBI aplicada según contexto:**
  - En el **Inicio de sesión** (`index.php` y `demo/index.html`): se muestra únicamente el **isotipo a color** (`assets/img/sibi-icono-color.svg`) centrado sobre la tarjeta de acceso y el logo institucional blanco del IEST La Recoleta (`assets/img/logo-recoleta-blanco.svg`) en el panel izquierdo.
  - **Dentro del sistema** (`php/layout.php` y `demo/index.html`): la barra superior utiliza el **logotipo horizontal completo** (`assets/img/sibi-logo-color.svg`, isotipo + texto `SIBI`).
- **Diagramas completos en draw.io (`docs/SIBI-Diagramas-UML-DER.drawio`):** Archivo editable de 5 pestañas listo para abrir en [app.diagrams.net](https://app.diagrams.net/) o draw.io Desktop:
  1. Diagrama de Casos de Uso (UML)
  2. Diagrama de Clases (UML)
  3. Modelo Entidad-Relación (DER — 8 tablas reales de `sql/database.sql`)
  4. Diagrama de Secuencia — Registrar Préstamo (Transacción InnoDB)
  5. Diagrama de Flujo — Proceso de Préstamo (RN-01, RN-04, RN-05)
- **Informe Final v6 sincronizado:** Integra visualmente las 5 figuras de diagramas en el Capítulo 3 más los 2 wireframes y el control de versiones v6.
- **Un solo menú superior responsivo** (cero barras laterales duplicadas), **SQL UTF-8 (`SET NAMES utf8mb4;`) con hashes bcrypt verificados para `123456`** y **exportación CSV limpia para Excel**.

## Instalación rápida en XAMPP
1. Copia la carpeta `sibi-biblioteca` dentro de `C:\xampp\htdocs\`.
2. Inicia **Apache** y **MySQL** en XAMPP Control Panel.
3. En `http://localhost/phpmyadmin`, importa `sql/database.sql`.
4. Abre `http://localhost/sibi-biblioteca/` e inicia sesión con las cuentas de `USUARIOS.md` (clave `123456`).
