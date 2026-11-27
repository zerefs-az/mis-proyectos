# Credenciales de Acceso — SIBI (IEST La Recoleta)

Este archivo concentra las cuentas iniciales de prueba cargadas por `sql/database.sql` (con `SET NAMES utf8mb4;` y hash bcrypt `$2y$10$` verificado para `123456`) y disponibles también en la demo interactiva (`demo/index.html`). Por diseño profesional y de seguridad, **las credenciales no se muestran en la pantalla de inicio de sesión**.

| Rol | Nombre completo | Correo electrónico | Contraseña | Permisos principales |
| :--- | :--- | :--- | :--- | :--- |
| **Administrador** | Ana Torres Medina | `admin@sibi.edu.pe` | `123456` | Control total: catálogo, portadas, eliminar libros, crear/editar/activar/eliminar usuarios, mostrador, moras, parámetros y reportes CSV/PDF. |
| **Bibliotecario** | Carlos Quispe Ramos | `biblio@sibi.edu.pe` | `123456` | Catálogo, subir/editar portadas, eliminar libros, agregar copias físicas, registrar lectores y operar mostrador (prestar, devolver, renovar). |
| **Lector (Estudiante)** | Mateo Flores Vargas | `mateo@sibi.edu.pe` | `123456` | Explorar catálogo, ver disponibilidad por ejemplar, consultar sus préstamos activos (tiene 1 libro vigente) e historial. |
| **Lector (Estudiante)** | Valeria Ccahuana Díaz | `valeria@sibi.edu.pe` | `123456` | Explorar catálogo y consultar su cuenta (cuenta de prueba con 1 préstamo vencido y mora calculada). |
| **Lector (Estudiante)** | Diego Salazar Huamán | `diego@sibi.edu.pe` | `123456` | Explorar catálogo y consultar su historial (1 devolución cerrada sin deuda). |
| **Lector (Estudiante)** | Sofía Paredes Condori | `sofia@sibi.edu.pe` | `123456` | Cuenta limpia sin préstamos (ideal para probar préstamos rápidos o eliminación de usuario). |

## Códigos de ejemplares iniciales (para probar el Mostrador)

| Código | Libro | Estado inicial |
| :--- | :--- | :--- |
| `REC-PRG-001` | Código Limpio | Disponible |
| `REC-PRG-002` | Código Limpio | Prestado (Mateo Flores) |
| `REC-BDD-001` | Fundamentos de Bases de Datos | Disponible |
| `REC-BDD-002` | Fundamentos de Bases de Datos | Prestado — Vencido (Valeria Ccahuana) |
| `REC-PRG-003` | Eloquent JavaScript | Disponible |
| `REC-ADM-001` | Teoría General de la Administración | Disponible |
| `REC-ADM-002` | Fundamentos de Marketing | Disponible |
| `REC-LIT-001` | La ciudad y los perros | Disponible |
| `REC-PRG-004` | Arquitectura Limpia | Disponible |
| `REC-ADM-003` | Gestión del Talento Humano | Mantenimiento |

> **Nota para producción:** Antes de publicar el sistema en un servidor real, cambia las contraseñas desde MySQL o crea cuentas nuevas desde el módulo **Lectores** como Administrador y elimina las cuentas de prueba.
