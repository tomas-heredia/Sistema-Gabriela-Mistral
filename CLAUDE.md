# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción del proyecto

Sistema de gestión escolar para un colegio de 650 alumnos: gestión de mora, recibos de sueldo docente y libretas/boletines de alumnos.

Módulos:
- **Alumnos/Tutores**: datos de alumnos y sus tutores/apoderados.
- **Cobranzas**: matrícula, cuotas, promociones por hermanos y por beca.
- **Mora**: control de cuotas vencidas y envío de avisos.
- **Boletines**: libretas de alumnos, cargadas por trimestre y enviadas por el cobrador (sin aprobación de un segundo actor — quien carga es quien envía).
- **Recibos de sueldo**: recibos de sueldo docente.

## Stack tecnológico

- **Backend**: Laravel + MySQL.
- **Frontend**: Blade + Livewire (sin SPA separada). Se eligió por sobre Inertia+Vue/React para minimizar la superficie de tecnologías dado que es un panel administrativo interno, no una app de cara al público con alta interactividad.
- **Roles y permisos**: `spatie/laravel-permission`, reforzado con Policies de Laravel en cada acción (nunca solo ocultando opciones en el menú).
- **Auditoría**: `spatie/laravel-activitylog` sobre los modelos financieros (trait `LogsActivity`) — ver reglas más abajo.
- **Generación de PDF**: `barryvdh/laravel-dompdf` para el PDF acumulativo de boletines. Se eligió por sobre `spatie/laravel-pdf` (que renderiza vía Chrome/Puppeteer) porque los boletines son layouts tabulares simples — dompdf es PHP puro, sin depender de Node/Chromium en el VPS.
- **Tests**: Pest, corriendo contra MySQL (base `sistema_gabriela_mistral_test`), no SQLite en memoria — se prioriza fidelidad con producción sobre velocidad, dado que hay lógica financiera sensible a comportamientos específicos de MySQL (precisión decimal, colación, etc.).
- **Formato de código**: Laravel Pint.
- **Infraestructura**: VPS de Hostinger, todo corriendo en Docker (app Laravel, MySQL, n8n).
- **Automatización de avisos**: n8n consulta la tabla `outbox` directamente en MySQL para procesar y enviar los avisos de mora (no hay endpoint API intermedio).

## Estructura de carpetas

El proyecto NO usa la estructura por defecto de Laravel (`app/Models`, `app/Http/Controllers` planos). En su lugar, `app/` está dividido por módulo de dominio:

```
app/
├── Core/            # User, Controller base, autenticación (Breeze), infraestructura compartida entre módulos
├── Alumnos/         # Alumnos y tutores/apoderados
├── Cobranzas/       # Matrícula, cuotas, promociones por hermano/beca
├── Mora/            # Control de cuotas vencidas y tabla outbox de avisos
├── Boletines/       # Libretas por trimestre, cargadas y enviadas por el cobrador
└── Sueldos/         # Recibos de sueldo docente
```

Cada módulo de dominio (`Alumnos`, `Cobranzas`, `Mora`, `Boletines`, `Sueldos`) tiene la misma subestructura interna: `Models/`, `Http/Controllers/`, `Livewire/`, `Policies/`. Un controlador de un módulo extiende la clase base en `App\Core\Http\Controllers\Controller`.

No hace falta configurar autoload especial en `composer.json`: como el mapeo PSR-4 ya es `"App\\": "app/"`, cualquier subcarpeta de `app/` funciona como namespace automáticamente (`App\Alumnos\Models\Alumno` vive en `app/Alumnos/Models/Alumno.php`, etc.).

**Qué queda fuera de los módulos, y por qué:** `app/Providers` y `app/View/Components` se mantienen en la ubicación estándar de Laravel. Los service providers se registran por ruta fija en `bootstrap/providers.php`, y los componentes Blade de clase (`AppLayout`, `GuestLayout`) se autodescubren por convención solo si viven en `App\View\Components` — moverlos exigiría registro manual sin ganar nada a cambio. Lo mismo aplica a `database/seeders` y `database/factories`, que Laravel espera en esa ubicación fija.

**Nota sobre factories fuera de `App\Models`:** como `User` vive en `App\Core\Models` y no en `App\Models`, Laravel no puede adivinar la relación modelo↔factory por convención de namespace. Por eso `User` declara `newFactory()` explícito y `UserFactory` declara `protected $model = User::class;`. Cualquier modelo nuevo dentro de un módulo (`App\Alumnos\Models\Alumno`, etc.) va a necesitar el mismo patrón.

## Comandos habituales

```bash
composer install                 # instalar dependencias PHP
php artisan serve                # levantar servidor de desarrollo
php artisan migrate              # correr migraciones
php artisan migrate:fresh --seed # resetear DB con datos de prueba
./vendor/bin/pest                # correr toda la suite de tests
./vendor/bin/pest --filter=NombreDelTest   # correr un test puntual
./vendor/bin/pint                # formatear código según el estándar del proyecto
```

## Roles

- **Administrador**: acceso total al sistema.
- **Cobrador**: cobranzas (matrícula, cuotas, mora), gestión de avisos, y carga/envío de boletines. Sin acceso a recibos de sueldo.
- **Profesor**: solo consulta de sus propios recibos de sueldo. Sin acceso a cobranzas ni a boletines — no carga notas ni ve las de otros.

Los tres roles se crean vía `database/seeders/RoleSeeder.php` (nombres: `administrador`, `cobrador`, `profesor`). Se asignan a un `User` con `$user->assignRole('...')`.

## Convenciones de código

- **Idioma del dominio**: modelos, tablas, rutas y variables de negocio en español (`Alumno`, `Tutor`, `Cuota`, `Mora`, `Boletin`, `ReciboSueldo`). La sintaxis y convenciones propias de Laravel (nombres de métodos de controlador, contratos de Eloquent, etc.) se mantienen en inglés como es estándar en el framework.
- **Autorización**: toda acción sobre datos de alumnos, cobranzas, boletines o recibos pasa por una Policy — no confiar en validaciones solo del lado del frontend/Livewire.
- **Validación**: Form Requests para toda entrada de usuario, especialmente en cobranzas y boletines.
- **Montos monetarios**: se almacenan como enteros (centavos), nunca como `float`, para evitar errores de redondeo.
- **Cálculos de mora y cuotas**: siempre en el backend. Nunca confiar en montos o fechas calculados en el cliente.

## Reglas del proyecto

1. **Nunca acceder a datos de otro usuario/rol sin verificar permisos.** Toda consulta o acción sobre alumnos, cobranzas, boletines o recibos de sueldo debe pasar por una Policy que valide el rol y, cuando aplica, la pertenencia (ej. un docente solo ve su propio recibo de sueldo).
2. **Todo cambio de dinero queda auditado.** Pagos, cuotas, matrícula, promociones y recibos de sueldo registran quién hizo el cambio, cuándo, y el valor anterior/nuevo. No se permiten updates directos a estos modelos sin pasar por el log de auditoría.
3. **Los boletines no son visibles para tutores hasta que el cobrador confirma el envío del trimestre.** Flujo mínimo: pendiente → cargado (borrador editable) → enviado. No hay aprobación de un segundo actor — quien carga el trimestre es quien confirma su envío — pero el paso de "confirmar y enviar" es explícito, nunca automático al guardar.
4. **La tabla `outbox` es una interfaz mínima hacia n8n.** Solo debe contener los datos estrictamente necesarios para enviar el aviso de mora (destinatario, mensaje, estado de envío) — nunca datos sensibles adicionales del alumno/tutor que n8n no necesite. El usuario de MySQL que usa n8n debe tener permisos acotados a esa tabla (no acceso de lectura/escritura al resto de la base).
5. **El cobrador no tiene acceso a recibos de sueldo; el profesor no tiene acceso a cobranzas ni a boletines.** Estos límites se implementan con Policies/roles de Spatie, no ocultando menús.
