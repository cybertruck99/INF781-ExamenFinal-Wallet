# SecureWallet - INF781 Examen Final

Proyecto opción A: API REST en Laravel 13, frontend React/Vite y base de datos PostgreSQL.

## Estructura

```txt
INF781-ExamenFinal-Wallet/
├─ backend/    API Laravel
├─ frontend/   SPA React/Vite
├─ .gitignore
└─ README.md
```

Las colecciones Postman de entrega están en `backend/tests`.

## Requisitos

- PHP 8.3 o superior
- Composer
- Node.js y npm
- PostgreSQL
- Newman opcional, solo para ejecutar las colecciones desde consola

## Instalación desde cero

1. Clonar el repositorio:

```cmd
git clone https://github.com/cybertruck99/INF781-ExamenFinal-Wallet.git
cd INF781-ExamenFinal-Wallet
```

2. Crear la base de datos PostgreSQL:

```cmd
psql -U postgres -c "CREATE DATABASE securewallet;"
```

Si `psql` no está en el PATH, crea la base `securewallet` desde pgAdmin o SQL Shell.

3. Configurar y levantar el backend:

```cmd
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API local:

```txt
http://127.0.0.1:8000/api/v1
```

4. Configurar y levantar el frontend en otra terminal:

```cmd
cd frontend
npm install
copy .env.example .env
npm run dev
```

Frontend local:

```txt
http://localhost:5173
```

Para probar desde celular en la misma red Wi-Fi, cambia `frontend/.env` a `VITE_API_BASE_URL=http://TU_IP_LOCAL:8000/api/v1`, levanta Laravel con `php artisan serve --host=0.0.0.0 --port=8000` y abre `http://TU_IP_LOCAL:5173`.

## Variables de entorno

Backend: copia `backend/.env.example` a `backend/.env` y ajusta:

```env
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=securewallet
DB_USERNAME=postgres
DB_PASSWORD=admin

BCRYPT_ROUNDS=12
ACCESS_TOKEN_TTL_MINUTES=15
REFRESH_TOKEN_TTL_DAYS=7
CORS_ALLOWED_ORIGINS=http://localhost:5173
CORS_ALLOWED_ORIGINS_PATTERNS=^http://(192\.168\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}):5173$

RECAPTCHA_SITE_KEY=your-google-recaptcha-v2-site-key
RECAPTCHA_SECRET_KEY=your-google-recaptcha-v2-secret-key
CAPTCHA_TEST_TOKEN=postman-test-captcha
```

Frontend: copia `frontend/.env.example` a `frontend/.env`:

```env
VITE_API_BASE_URL=
VITE_RECAPTCHA_SITE_KEY=optional-google-recaptcha-v2-site-key
```

Las claves reales y `.env` locales no se suben al repositorio. Para Postman local se usa `CAPTCHA_TEST_TOKEN`; en producción debe usarse reCAPTCHA real.

## Usuarios semilla

Después de `php artisan migrate:fresh --seed` quedan estos usuarios de demo:

| Rol | Correo | Contraseña | Uso |
|---|---|---|---|
| ADMIN | admin@securewallet.test | Admin123*Secure | Endpoints `/admin/*` |
| USER | manuel123@gmail.com | Manuel_123*Seguro | Usuario A, wallet con saldo |
| USER | carmen123@gmail.com | Carmen_123*Seguro | Usuario B, pruebas BOLA/IDOR |

MFA de prueba: la colección `EndPoints-Parte5` crea/usa un flujo real de MFA con `/auth/mfa/enable`, `/auth/mfa/enable/confirm` y `/auth/mfa/verify`. El secreto TOTP no se expone en JSON de perfil, usuarios ni auditoría.

## Colecciones Postman

Importa estos archivos desde `backend/tests`:

```txt
INF781-ExamenFinal.postman_collection.json
INF781-ExamenFinal-Local.postman_environment.json
EndPoints-Parte5.postman_collection.json
EndPoints-Parte5.postman_environment.json
```

`INF781-ExamenFinal` contiene 10 peticiones OWASP RS-01 a RS-10 con ejemplos válidos e inválidos. `EndPoints-Parte5` contiene los endpoints principales del sistema y pruebas auxiliares dentro de los scripts de Postman.

## Endpoints principales

Prefijo base: `/api/v1`.

| Método | Endpoint | Acceso | Descripción |
|---|---|---|---|
| GET | `/health` | Público | Estado de API |
| GET | `/auth/captcha/site-key` | Público | Site key reCAPTCHA |
| POST | `/auth/register` | Público | Registro con reCAPTCHA |
| POST | `/auth/login` | Público | Login con reCAPTCHA, rate limit y MFA si aplica |
| POST | `/auth/mfa/verify` | Público con ticket | Verifica TOTP y emite tokens |
| POST | `/auth/refresh` | Público | Refresh token rotativo |
| POST | `/auth/logout` | USER | Revoca sesión |
| POST | `/auth/mfa/enable` | USER | Inicia activación MFA |
| POST | `/auth/mfa/enable/confirm` | USER | Confirma MFA |
| GET | `/me` | USER | Perfil propio sin secretos |
| GET | `/wallet` | USER | Wallet propia |
| POST | `/wallet/topup` | USER | Recarga |
| POST | `/transfers` | USER | Crea transferencia con `Idempotency-Key` |
| POST | `/transfers/{uuid}/confirm` | USER propietario | Confirma transferencia |
| GET | `/transactions` | USER | Historial propio |
| GET | `/admin/users` | ADMIN | Usuarios |
| PATCH | `/admin/users/{uuid}/block` | ADMIN | Bloqueo/desbloqueo |
| GET | `/admin/audit-logs` | ADMIN | Bitácora |

## Controles de seguridad implementados

- RS-01: UUID públicos y validación de propiedad del objeto en servidor. Manipular UUID ajenos devuelve 403/404 sin datos financieros.
- RS-02: RBAC con roles `USER` y `ADMIN`; middleware `EnsureAdmin` protege `/admin/*`.
- RS-03: contraseñas con bcrypt `BCRYPT_ROUNDS=12`; respuestas públicas no incluyen password, hash, secretos MFA ni hashes de tokens.
- RS-04: Form Requests estrictos, rechazo de campos no permitidos y consultas con Eloquent/Query Builder.
- RS-05: transferencias en `DB::transaction()` con `lockForUpdate()`, control de saldo no negativo e idempotencia por header `Idempotency-Key`.
- RS-06: CORS restringido, cabeceras de seguridad y errores JSON genéricos en producción.
- RS-07: access token de 15 minutos y refresh token persistente con rotación; reutilizar un refresh ya usado revoca la familia.
- RS-08: login limitado a 5 intentos/minuto, transferencias a 10/minuto y reCAPTCHA en registro/login.
- RS-09: bitácora de auditoría con usuario, IP, user-agent y timestamp; solo ADMIN puede consultarla.
- RS-10: frontend React sin `dangerouslySetInnerHTML`; autenticación por `Authorization: Bearer`. En laboratorio los tokens se guardan en `sessionStorage`, con riesgo XSS mitigado por escape de React, CSP, tokens cortos y refresh rotation.

## Validación rápida

Con el backend levantado en `127.0.0.1:8000`:

```cmd
cd backend
php artisan migrate:fresh --seed --force
cd ..
newman run backend/tests/INF781-ExamenFinal.postman_collection.json -e backend/tests/INF781-ExamenFinal-Local.postman_environment.json
newman run backend/tests/EndPoints-Parte5.postman_collection.json -e backend/tests/EndPoints-Parte5.postman_environment.json
```

También puedes importar ambas colecciones en Postman y ejecutarlas con sus environments locales.
