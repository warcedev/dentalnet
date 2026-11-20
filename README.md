# DentalWeb

Sistema de gestión para consultorios odontológicos: pacientes, ficha clínica, odontograma, agenda, recordatorios, tratamientos y cobros.

- **Stack:** Laravel 13 · PHP 8.3+ · Inertia.js 3 · React 19 + TypeScript · Tailwind CSS 4 · shadcn/ui (Radix) · SQLite
- **Región:** interfaz en español, guaraníes (PYG, sin decimales), fechas dd/mm/aaaa, zona horaria `America/Asuncion`

## Estado de las fases

| Fase | Contenido | Estado |
|---|---|---|
| 1 | Base, autenticación, roles, auditoría y respaldos | ✅ Terminada |
| 2 | Pacientes, ficha clínica y consentimientos | ✅ Terminada |
| 3 | Odontograma con historial y línea de tiempo | ✅ Terminada |
| 4 | Agenda y recordatorios | Pendiente |
| 5 | Plan de tratamiento, consultas y cobros | Pendiente |
| 6 | Documentos, periodontograma, portal del paciente y reportes | Pendiente |

## Requisitos

- PHP 8.3 o superior con las extensiones `pdo_sqlite`, `sqlite3`, `mbstring`, `intl`, `gd`, `zip`, `sodium` y `fileinfo`
- Composer 2
- Node.js 22 o superior (se usa la versión 24 LTS)
- SQLite 3.35 o superior (necesario para `VACUUM INTO`)

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # roles y permisos; en entornos que no son de producción, también usuarios de demostración
npm install
npm run build
```

Desarrollo (servidor, cola, logs y Vite en paralelo):

```bash
composer dev
```

Abrir http://localhost:8000.

### Datos de demostración

Se cargan solo fuera de producción: 5 usuarios, 30 pacientes (4 de ellos menores con tutor), antecedentes, alergias, condiciones de riesgo, 4 plantillas de consentimiento **de ejemplo**, 5 consentimientos firmados y unos 190 registros de odontograma repartidos en dos años (con una corrección y una anulación de ejemplo).

Todos los usuarios usan la contraseña `password`.

| Correo | Rol |
|---|---|
| admin@consultorio.test | Administrador |
| maria.benitez@consultorio.test | Odontóloga + Administradora |
| carlos.gimenez@consultorio.test | Odontólogo |
| recepcion@consultorio.test | Recepción |
| pedro.ramirez@consultorio.test | Recepción (desactivado: no puede ingresar) |

### Instalación en producción

```bash
APP_ENV=production php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # idempotente: repetir después de cada actualización
php artisan clinic:create-admin                                  # crea el primer administrador
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

En el servidor también hay que configurar:

1. **Tareas programadas.** Agregar al cron: `* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1`
2. **Procesador de colas**, con supervisor o systemd: `php artisan queue:work --tries=3 --max-time=3600`
3. **HTTPS obligatorio**, con `SESSION_SECURE_COOKIE=true`.
4. **Permisos:** `storage/` y `database/` deben tener permiso de escritura para el usuario del servidor web y **no** deben ser accesibles desde la web. Solo se publica `public/`.

## Variables de entorno

Además de las estándar de Laravel:

| Variable | Valor por defecto | Descripción |
|---|---|---|
| `APP_TIMEZONE` | `America/Asuncion` | Zona horaria de la aplicación |
| `APP_LOCALE` | `es` | Idioma |
| `DB_BUSY_TIMEOUT` | `5000` | Milisegundos que SQLite espera cuando la base está bloqueada |
| `DB_JOURNAL_MODE` | `wal` | Modo WAL: permite lecturas concurrentes mientras se escribe |
| `DB_SYNCHRONOUS` | `normal` | Valor recomendado junto con WAL |
| `SESSION_LIFETIME` | `30` | Minutos de inactividad antes de cerrar la sesión |
| `SESSION_ENCRYPT` | `true` | Cifra los datos de sesión |
| `SESSION_SECURE_COOKIE` | `true` en producción | Envía la cookie de sesión solo por HTTPS |
| `CLINIC_REQUIRE_ADMIN_2FA` | `true` | Obliga a los administradores a activar la verificación en dos pasos |
| `BACKUP_PATH` | `storage/app/backups` | Carpeta de destino de los respaldos |
| `BACKUP_KEEP_DAYS` | `30` | Días que se conservan los respaldos |
| `CLINIC_CLEANING_INTERVAL_MONTHS` | `6` | Meses entre limpiezas para calcular la próxima recomendada |
| `BACKUP_PASSWORD` | *(vacío)* | Si se define, cifra los `.zip` con AES-256. **Muy recomendado**: el respaldo contiene datos de salud |

Las claves foráneas de SQLite (`foreign_keys = ON`) se activan siempre, desde `config/database.php`.

## Comandos

| Comando | Qué hace |
|---|---|
| `composer ci:check` | Ejecuta todas las verificaciones (lo mismo que corre en CI) |
| `composer test` | Pint (`--test`), PHPStan nivel 7 y pruebas de Pest |
| `php artisan test` / `./vendor/bin/pest` | Solo las pruebas de backend |
| `npm run test` | Pruebas de frontend (Vitest) |
| `npm run check` | TypeScript, ESLint (incluye jsx-a11y), Prettier y Vitest |
| `composer lint` / `npm run lint:fix` / `npm run format` | Corrige el formato |
| `php artisan clinic:backup` | Genera un respaldo y borra los vencidos |
| `php artisan audit:verify` | Verifica que la auditoría no haya sido alterada |
| `php artisan clinic:create-admin` | Crea un administrador de forma interactiva |

## Respaldos

`php artisan clinic:backup` genera `storage/app/backups/dentalweb-AAAAMMDD-HHMMSS.zip`, que contiene:

- `database.sqlite`: copia consistente obtenida con `VACUUM INTO`. Es segura aunque la aplicación esté en uso.
- `files/storage/app/private/...`: los archivos adjuntos privados.

El Scheduler lo ejecuta todos los días a las 02:00, y a las 03:00 verifica la auditoría.

> ⚠️ Un respaldo guardado en el mismo disco no protege contra la falla o el robo del servidor. Copie los `.zip` a otro lugar (otro equipo, almacenamiento en la nube cifrado) y **pruebe restaurarlos** periódicamente.

### Restaurar un respaldo

```bash
php artisan down
cp database/database.sqlite database/database.sqlite.antes-de-restaurar
unzip -P "$BACKUP_PASSWORD" dentalweb-AAAAMMDD-HHMMSS.zip -d /tmp/restauracion
cp /tmp/restauracion/database.sqlite database/database.sqlite
rm -f database/database.sqlite-wal database/database.sqlite-shm
rsync -a /tmp/restauracion/files/storage/app/private/ storage/app/private/
php artisan audit:verify
php artisan up
```

## Arquitectura

```
app/
├── Actions/          Casos de uso (CreateStaffUser, UpdateStaffUser, SyncUserRoles…)
├── Data/             Objetos de datos tipados que van de los Form Requests a las Actions
├── Enums/            Role y Permission: la matriz de permisos es código, no datos sueltos
├── Http/
│   ├── Controllers/  Solo coordinan: autorizan, llaman a la Action y responden
│   ├── Middleware/   Usuario activo, 2FA para administradores, cabeceras de seguridad
│   └── Requests/     Validación con mensajes en español
├── Models/Concerns/  Auditable (registra cambios) e IsImmutable (bloquea UPDATE y DELETE)
├── Policies/         Autorización de cada acción
├── Services/         Auditoría (logger, hasher, verificador) y respaldos
└── Support/          ImmutableTable (triggers de solo inserción para SQLite, MySQL y PostgreSQL)
resources/js/
├── lib/format.ts     Fechas dd/mm/aaaa y montos en guaraníes
├── lib/navigation.ts Menú filtrado según los permisos del usuario
└── pages/            Pantallas de Inertia
```

### Decisiones

- **Portabilidad de la base de datos:** todo el acceso es con Eloquent o Query Builder, sin SQL propio de SQLite. Las partes específicas de cada motor (triggers y pragmas) están aisladas en `ImmutableTable` y en `config/database.php`. No se agregó una capa Repository porque Eloquent ya cumple esa función.
- **Auditoría inmutable, con doble barrera:**
  - el modelo lanza `ImmutableRecordException` ante cualquier `update` o `delete`;
  - triggers en la base rechazan `UPDATE` y `DELETE` aunque se use SQL directo.
- **Auditoría a prueba de manipulación:** cada registro guarda un HMAC-SHA256 que incluye el hash del registro anterior. `audit:verify` detecta registros alterados o borrados.
  - Rotar `APP_KEY` invalida esta verificación para los registros anteriores. Si hay que rotarla, ejecute `audit:verify` antes y conserve la clave anterior.
  - Los valores se guardan como `TEXT` y no como `JSON`, porque MySQL reordena el JSON y eso alteraría el hash.
- **Qué guarda la auditoría:** quién, qué, cuándo, IP, user agent, nombre de la ruta (no la URL, que puede llevar datos en la query string) y los valores anteriores y nuevos.
  - Nunca guarda contraseñas, tokens ni secretos de 2FA.
  - Los campos con cast `encrypted` se registran como `[cifrado]`.
- **Los usuarios no se borran:** se desactivan. La sesión de un usuario desactivado se corta en la siguiente petición. Siempre debe quedar al menos un administrador activo.
- **Sin registro público:** el administrador da de alta al personal y cada persona recibe un enlace para crear su propia contraseña.

### Pacientes y ficha clínica (Fase 2)

- **Búsqueda** por nombre (sin importar acentos ni mayúsculas), documento o teléfono. Usa columnas normalizadas (`search_name`, `search_phone`) en lugar de funciones propias de cada motor, y escapa los comodines de `LIKE` con `ESCAPE '!'`, que funciona igual en SQLite, MySQL y PostgreSQL.
- **Documento único** por tipo y número, también contra pacientes archivados. Las cédulas se guardan sin puntos.
- **Archivo lógico:** solo el administrador archiva y restaura. El borrado físico está bloqueado en el modelo.
- **Antecedentes médicos versionados:** cada cambio crea una versión nueva y la anterior queda intacta, protegida además por triggers en la base. A partir de la segunda versión se exige el motivo del cambio.
- **Alergias y condiciones de riesgo:** no se borran, se desactivan registrando quién, cuándo y por qué.
- **Cifrado en reposo** (cast `encrypted`): antecedentes, medicación, hábitos, alérgenos, reacciones, notas y motivos. En la auditoría quedan como `[cifrado]`.
- **Alertas clínicas:** alergias y condiciones activas se muestran en todas las pantallas del paciente, también a recepción, aunque recepción no puede abrir la ficha clínica.
- **Consentimientos:**
  - Al editar una plantilla se publica una versión nueva.
  - Cada consentimiento firmado guarda una copia literal del texto aceptado, la versión de la plantilla, el hash SHA-256 del texto y de la firma, quién firmó y con qué vínculo, el profesional presente, la IP y la fecha.
  - La firma se captura en un canvas (dedo, lápiz o mouse) y se guarda como PNG en `storage/app/private`. Solo se entrega por una ruta autorizada y sin caché.
  - Se rechazan firmas vacías o que no sean PNG.
  - Los menores de edad no pueden firmar por sí mismos: firma el padre, la madre o el tutor legal.

### Odontograma (Fase 3)

- **Catálogo FDI:** la tabla `teeth` tiene 32 piezas permanentes (11–48) y 20 temporales (51–85). Se carga en la migración, así que también existe en producción.
- **Eventos inmutables:** `tooth_events` solo admite inserciones (triggers en la base, igual que la auditoría). Cada evento registra diente, superficie (opcional), condición, estado, fecha de detección, fecha de tratamiento, profesional, notas (cifradas) y la consulta asociada.
  - La relación con las consultas queda preparada: la clave foránea se agrega en la Fase 5, cuando exista esa tabla.
- **Alcance de cada condición:**
  - Por superficie: caries, restauración, sellante, fractura.
  - Por diente completo: ausente, extracción, implante, corona, puente, endodoncia, prótesis, perno.
  - De toda la boca: limpieza.
  - El servidor rechaza combinaciones inválidas, como una caries sin superficie o una corona con superficie.
- **Reconstrucción a una fecha** (`App\Services\Odontogram\OdontogramState`):
  - Solo cuentan los eventos vigentes, es decir, los que no fueron corregidos ni anulados.
  - Un evento aplica desde su fecha de detección.
  - Si a la fecha consultada ya estaba tratado, se muestra con su estado registrado; si el tratamiento fue posterior, se muestra como "diagnosticado".
  - En cada superficie gana el evento más reciente. En el diente completo se conserva el último de cada condición, por ejemplo endodoncia y corona a la vez.
- **Correcciones (regla 1):** se registran como un evento nuevo con `corrects_event_id` y un motivo obligatorio, y el original queda intacto. "Anular" sirve para los registros cargados por error. Solo se puede corregir el registro más reciente de la cadena.
- **Consultas rápidas:**
  - Última limpieza y próxima recomendada (regla 5): se calcula desde la última limpieza realizada, con el intervalo de `CLINIC_CLEANING_INTERVAL_MONTHS`, que por defecto es 6.
  - Última intervención de cada diente.
  - Lista de arreglos con su fecha.
- **Interfaz:**
  - Gráfico SVG con vistas permanente, mixta y temporal; por defecto se elige según la edad del paciente.
  - Un solo punto de tabulación: las flechas recorren los dientes y Enter selecciona. Con el mouse se puede elegir directamente la superficie.
  - Cada diente tiene una descripción completa para lectores de pantalla, y el mismo estado se puede ver en forma de tabla.
  - La línea de tiempo recorre las fechas con cambios y recarga solo el gráfico (recarga parcial de Inertia).

### Matriz de permisos

Se define en [`app/Enums/Role.php`](app/Enums/Role.php) y la verifica `tests/Feature/Admin/RolePermissionMatrixTest.php`.

| Permiso | Administrador | Odontólogo | Recepción |
|---|:-:|:-:|:-:|
| Usuarios, auditoría, configuración | ✅ | — | — |
| Ver, crear y editar pacientes | ✅ | ✅ | ✅ |
| Archivar pacientes | ✅ | — | — |
| Ver alertas clínicas (alergias, riesgos) | ✅ | ✅ | ✅ |
| Ficha clínica, odontograma, consultas, recetas, documentos | ✅ | ✅ | — |
| Consentimientos | ✅ | ✅ | ✅ |
| Agenda | ✅ | ✅ | ✅ |
| Recordatorios | ✅ | — | ✅ |
| Gestionar planes de tratamiento | ✅ | ✅ | ver |
| Registrar cobros | ✅ | ver | ✅ |
| Anular cobros | ✅ | — | — |
| Reportes | ✅ | ✅ | — |

## Seguridad

- Policies y Form Requests en todas las acciones. El frontend recibe la lista de permisos solo para mostrar u ocultar opciones; la autorización real se valida en el servidor.
- CSRF con el mecanismo de Laravel. Cookies `HttpOnly` y `SameSite=Lax`, con `Secure` en producción.
- Sesión cifrada que expira tras 30 minutos de inactividad.
- Límite de 5 intentos de inicio de sesión por minuto (por combinación de correo e IP). Los intentos fallidos y los bloqueos quedan auditados.
- Verificación en dos pasos (TOTP) y passkeys. La 2FA es obligatoria para administradores.
- Cabeceras de seguridad: `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS bajo HTTPS y `Cache-Control: no-store` en páginas autenticadas.
- Las URLs no llevan datos sensibles. La auditoría guarda el nombre de la ruta y no la URL.

## Cumplimiento normativo (Paraguay)

> Esta sección **no constituye asesoramiento legal**. Antes de usar el sistema con pacientes reales, conviene revisar con un abogado especializado:

1. **Protección de datos personales.** Verificar la ley general de protección de datos personales vigente, sus plazos de entrada en vigor y obligaciones como el registro de bases de datos, el consentimiento expreso para datos sensibles (los datos de salud lo son) y la notificación de brechas.
2. **Historia clínica.** Plazos mínimos de conservación y requisitos de formato según el Código Sanitario y las resoluciones del MSPBS, incluida la validez de una historia clínica solo electrónica.
3. **Firma de consentimientos.** La firma capturada en pantalla es una firma electrónica *simple*, no una firma digital con certificado según la Ley 6822/2021. Hay que confirmar si alcanza como prueba, y si conviene además imprimir y archivar el documento firmado. **Las plantillas incluidas son ejemplos**: un asesor legal debe revisar los textos antes de usarlos.
6. **Menores de edad.** El sistema considera menor a quien tiene menos de 18 años y exige la firma del padre, la madre o el tutor. Hay que confirmar los casos especiales (menores emancipados, adolescentes que consienten ciertos tratamientos).
4. **Comprobantes.** Los recibos que emite el sistema son internos y no reemplazan a la factura electrónica (SIFEN/SET).
5. **Comunicaciones.** Consentimiento para enviar recordatorios por email y, más adelante, por WhatsApp o SMS.
# dentalnet
