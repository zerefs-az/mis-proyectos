# Guía de Estilo Visual — SIBI (v6 · Identidad Oficial SIBI + Menú Único)

## 1. Paleta de Colores (Regla 60-30-10)
- **60% Fondo y Superficies Limpias:**
  - Lienzo base: `#F4F7FB` (gris azulado luminoso, limpio y descansado).
  - Superficies y tarjetas: `#FFFFFF` puro con bordes nítidos `#E2E8F0`.
- **30% Identidad Institucional (Zafiro & Azul Real):**
  - Zafiro profundo (títulos, barra superior unificada): `#0F294A`.
  - Azul real vivo (enlaces, foco, ítems activos, acentos de navegación): `#2563EB` (`#EFF6FF` en fondos suaves).
  - Tinta principal: `#0F172A` / Tinta secundaria: `#475569`.
- **10% Acento Primario Vivo y Semántica:**
  - Naranja coral vibrante (botones CTA principales): `#EA580C` con degradado `#F97316 → #EA580C` y hover `#C2410C`.
  - Disponible / A tiempo / Activo: `#059669` sobre `#D1FAE5`.
  - Próximo a vencer / Mantenimiento: `#D97706` sobre `#FEF3C7`.
  - Vencido / Mora / Eliminar: `#DC2626` sobre `#FEE2E2`.
  - Prestado / Inactivo: `#475569` sobre `#E2E8F0`.

## 2. Tipografía
- Títulos editoriales: `Lora` (serif académica).
- Interfaz, tablas y formularios: `Plus Jakarta Sans` / `Inter` / `Segoe UI`.
- Códigos de ejemplar e ISBN: `JetBrains Mono` / monoespaciada.

## 3. Fotografía y Portadas Reales
- Login: `assets/img/bg-login-biblioteca.jpg` (fotografía arquitectónica real de biblioteca al atardecer).
- Hero Catálogo: `assets/img/hero-biblioteca.jpg` (fotografía real luminosa de sala de lectura moderna).
- Portadas del catálogo: `assets/img/portada-1.jpg` a `portada-8.jpg` (arte editorial de alta resolución) + carga de portadas propias en `assets/img/portadas/`.

## 4. Identidad Institucional y Navegación (v5)
- Logo institucional blanco vectorial: `assets/img/logo-recoleta-blanco.svg` ubicado junto a la etiqueta `IEST La Recoleta · Arequipa` en la vista de acceso.
- Un solo menú horizontal superior (`topbar` / `.topnav`) filtrado por rol, sin barras laterales duplicadas.

## 5. Uso de Logotipos SIBI (v6)
- **Pantalla de inicio de sesión (`index.php`):** Solo isotipo (`assets/img/sibi-icono-color.svg`) centrado en la tarjeta de login + isotipo blanco institucional (`assets/img/logo-recoleta-blanco.svg`) en la cabecera del panel visual.
- **Interior del sistema (`php/layout.php`):** Logotipo horizontal completo (`assets/img/sibi-logo-color.svg`) en la barra superior.
- **Favicon del navegador:** `assets/img/sibi-icono-color.svg`.
