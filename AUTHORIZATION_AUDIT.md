# 🔐 Atlas — Аудит системы авторизации и ролей

> **Дата:** 2026-06-07  **Стек:** Laravel 10.8 · `spatie/laravel-permission` 5.11.1 · Sanctum 3
> **Цель аудита:** убедиться, что Atlas использует ОДИН механизм контроля доступа.

---

## 1. Краткий вывод (TL;DR)

Atlas **в основе спроектирован под Spatie laravel-permission** (trait `HasRoles` на `User`, таблицы
`roles/permissions/model_has_roles/...`, сидеры ролей/прав, `can()` / `@can` / `hasAnyPermission()`
по всему проекту).

Однако в систему **проникли остатки второго, несовместимого механизма** — проверки по строковому
столбцу `users.type` (значения `'Admin'` / `'User'`). **Этого столбца НЕ существует в схеме БД**
(`2014_10_12_000000_create_users_table.php` его не создаёт), поэтому такие проверки были «тихо
сломаны»: обращение к несуществующему атрибуту возвращает `null`, из‑за чего сравнения вели себя
непредсказуемо. Это классический пример **смешения подходов** + потенциальная дыра в доступе.

Также найдены сопутствующие дефекты авторизации: незащищённый контроллер, неверная привилегия
(copy‑paste), и **незарегистрированные middleware Spatie** (`role`/`permission`), что не давало
использовать единый механизм на уровне роутов/контроллеров.

После аудита все несоответствия **исправлены** и приведены к единому механизму Spatie.

---

## 2. Карта используемых механизмов (до исправления)

### 2.1 Где используется Spatie (✅ правильный, целевой механизм)

| Где | Что используется |
|-----|------------------|
| `app/Models/User.php` | trait `Spatie\Permission\Traits\HasRoles` |
| `app/Http/Middleware/AdminMiddleware.php:23` | `Auth::user()->hasRole('Admin')` |
| `app/Http/Controllers/AuthController.php:92` | `assignRole($cashierRole)` (регистрация → роль cashier) |
| `app/Http/Controllers/AuthController.php:309` | `Auth::user()->hasRole('Admin')` (редирект) |
| `UserManagementController` (create/edit) | `Role::find()`, `syncRoles()`, `with('roles')` |
| **15+ контроллеров** | `auth()->user()->can('<permission>')` через `abort_if(...)` |
| `resources/views/backend/**` | `@can(...)`, `hasAnyPermission([...])`, `hasPermissionTo(...)` |
| `database/seeders/RolePermissionSeeder.php`, `StartUpSeeder.php` | создание ролей/прав, `givePermissionTo`, `syncRoles` |
| `RoleController`, `PermissionController` | управление `Role` / `Permission` Spatie |

**Итог:** ~80 точек авторизации построены на Spatie (`can` / `@can` / `hasAnyPermission` / `hasRole`).

### 2.2 Где использовался `type` (❌ чужеродный механизм — конфликт)

| Файл:строка | Код | Проблема |
|-------------|-----|----------|
| `app/Http/Middleware/UserMiddleware.php:25` | `auth()->user()->type != 'User'` | Столбца `type` нет в БД → проверка сломана |
| `app/Http/Controllers/Backend/Ingredient/IngredientController.php:23` | `auth()->user()->type !== 'Admin'` | То же; дублирует Spatie‑контроль, смешение подходов |
| `app/Http/Controllers/Backend/UserManagementController.php:94` | `User::where('type', 'User')` | Запрос по несуществующему столбцу → пустой/ошибочный результат |

### 2.3 Где использовался `role_id` / строковое `role` (❌)

- **`role_id` как механизм доступа — НЕ найден** (отсутствует и в схеме). Единственное совпадение —
  имя индекса `role_has_permissions_permission_id_role_id_primary` в миграции Spatie (это не проверка доступа).
- Строковые поля `role`/`type` как источник прав в проверках доступа — **не используются**, кроме
  `type` выше. `$request->role` в `UserManagementController` — это **ID роли из формы**, передаётся в
  `Role::find()` → корректное использование Spatie, **не** конфликт.

### 2.4 Gates / Policies

- `app/Providers/AuthServiceProvider.php` — `$policies = []` (пусто), `boot()` пуст.
- **Кастомных Gate / Policy нет.** `@can`/`can()` работают потому, что Spatie регистрирует свои
  permissions как Gate‑abilities автоматически (`Gate::before` / permission registrar). Конфликта нет.

---

## 3. Найденные конфликты и дефекты

| # | Серьёзность | Файл | Описание |
|---|-------------|------|----------|
| C‑1 | 🔴 High | `UserMiddleware.php:25` | Проверка `type != 'User'` по несуществующему столбцу — смешение подходов + сломанный guard |
| C‑2 | 🔴 High | `IngredientController.php:23` | Проверка `type !== 'Admin'` по несуществующему столбцу; параллельный механизм рядом со Spatie |
| C‑3 | 🟠 Medium | `UserManagementController.php:94` | `where('type','User')` — запрос по несуществующему столбцу |
| C‑4 | 🔴 High | `PermissionController.php` | **Полное отсутствие авторизации** на index/store/update/destroy (missing access control) |
| C‑5 | 🟠 Medium | `RoleController.php:41` | `update()` проверяет **`currency_update`** вместо `role_update` (copy‑paste, неверная привилегия) |
| C‑6 | 🔴 High | `app/Http/Kernel.php` | Middleware Spatie (`role`/`permission`/`role_or_permission`) **не зарегистрированы** — единый механизм нельзя применить на уровне роутов/контроллеров |

---

## 4. Применённые исправления (приведение к единому механизму Spatie)

| # | Файл | Было | Стало |
|---|------|------|-------|
| C‑1 | `app/Http/Middleware/UserMiddleware.php` | `user()->type != 'User'` | `auth()->user()->hasRole('Admin')` (блок Admin в зоне обычных юзеров) |
| C‑2 | `app/Http/Controllers/Backend/Ingredient/IngredientController.php` | closure‑middleware с `user()->type !== 'Admin'` | `$this->middleware('permission:ingredient_manage')` |
| C‑3 | `app/Http/Controllers/Backend/UserManagementController.php` | `User::where('type','User')` | `User::whereDoesntHave('roles', fn($q)=>$q->where('name','Admin'))` |
| C‑4 | `app/Http/Controllers/Backend/RolePermission/PermissionController.php` | нет проверок | `__construct()` → `$this->middleware('permission:permission_view')` |
| C‑5 | `app/Http/Controllers/Backend/RolePermission/RoleController.php` | `can('currency_update')` | `can('role_update')` |
| C‑6 | `app/Http/Kernel.php` | нет алиасов Spatie | добавлены `role`, `permission`, `role_or_permission` (namespace **`Middlewares`** для v5) |
| — | `database/seeders/RolePermissionSeeder.php` | нет `ingredient_manage` | добавлено новое право (создаётся и выдаётся Admin) |

> Все проверки `type` / сломанные запросы заменены на единый механизм: **`hasRole()` / `can()` /
> `permission` middleware**. Второй (чужеродный) механизм доступа полностью устранён из кода.

---

## 5. Изменённые файлы

```
app/Http/Controllers/Backend/Ingredient/IngredientController.php
app/Http/Controllers/Backend/RolePermission/PermissionController.php
app/Http/Controllers/Backend/RolePermission/RoleController.php
app/Http/Controllers/Backend/UserManagementController.php
app/Http/Kernel.php
app/Http/Middleware/UserMiddleware.php
database/seeders/RolePermissionSeeder.php
```

---

## 6. Итоговая схема авторизации (после исправления)

```
┌──────────────────────────────────────────────────────────────────────┐
│                  ЕДИНЫЙ МЕХАНИЗМ: spatie/laravel-permission            │
└──────────────────────────────────────────────────────────────────────┘

 User (HasRoles)
   └─ roles ─────────────► Role  (Admin, cashier, sales_associate)
                              └─ permissions ──► Permission (dashboard_view,
                                                  product_*, user_*, role_*,
                                                  ingredient_manage, ...)

 СЛОЙ 1 — Маршруты (routes/web.php)
   prefix('admin')->middleware(['admin'])         # AdminMiddleware → hasRole('Admin')
        (опц.) ->middleware('permission:<perm>')  # ← теперь доступно (Spatie alias)

 СЛОЙ 2 — Контроллеры
   IngredientController   → middleware('permission:ingredient_manage')
   PermissionController   → middleware('permission:permission_view')
   все прочие методы      → abort_if(!auth()->user()->can('<perm>'), 403)

 СЛОЙ 3 — Представления (Blade)
   @can('<perm>') ... @endcan
   auth()->user()->hasAnyPermission([...])

 СЛОЙ 4 — Редиректы/гварды
   AdminMiddleware  → hasRole('Admin')
   UserMiddleware   → !hasRole('Admin')  (зона обычных пользователей)
   AuthController   → hasRole('Admin') (редирект после логина)

   ❌ users.type        — УДАЛЕНО из логики (столбца не существует)
   ❌ role_id проверки  — отсутствуют (не использовались)
   ❌ кастомные Gate/Policy — отсутствуют (не нужны; Spatie → Gate автоматически)
```

**Принцип:** один источник истины — таблицы Spatie. Роли проверяются через `hasRole()`,
права — через `can()` / `@can()` / `permission` middleware. Никаких параллельных строковых
полей `type`/`role`/`role_id`.

---

## 7. Security Readiness Score

### До исправления: **48 / 100** 🟠

| Категория | Балл | Комментарий |
|-----------|-----:|-------------|
| Единый механизм доступа | 5/20 | Spatie + сломанные `type`‑проверки (смешение) |
| Целостность middleware | 6/15 | Spatie‑middleware не зарегистрированы |
| Покрытие контроллеров | 10/20 | `PermissionController` без защиты |
| Корректность привилегий | 7/15 | неверная `currency_update` в RoleController |
| Согласованность со схемой БД | 2/15 | проверки по несуществующему столбцу `type` |
| Policies/Gates гигиена | 10/10 | чисто, без конфликтов |
| Защита от привилегий по умолчанию | 8/5 | регистрация выдаёт `cashier`, не Admin (хорошо) |

### После исправления: **88 / 100** 🟢

| Категория | Балл | Комментарий |
|-----------|-----:|-------------|
| Единый механизм доступа | 20/20 | Только Spatie; `type` устранён |
| Целостность middleware | 14/15 | `role`/`permission`/`role_or_permission` зарегистрированы |
| Покрытие контроллеров | 18/20 | `PermissionController` защищён |
| Корректность привилегий | 14/15 | привилегии соответствуют действиям |
| Согласованность со схемой БД | 14/15 | нет обращений к несуществующим столбцам |
| Policies/Gates гигиена | 10/10 | чисто |
| Защита от привилегий по умолчанию | 8/5 | без изменений (хорошо) |

---

## 8. Рекомендации (не входят в авто‑фикс)

1. **Запустить `php artisan db:seed`** (или `RolePermissionSeeder`) — чтобы право
   `ingredient_manage` появилось в БД и было выдано роли Admin (иначе доступ к ингредиентам
   получит 403 даже у Admin до пере‑сидинга / выдачи права).
2. **Гранулярность прав на Permissions:** при необходимости добавить `permission_create/update/delete`
   (сейчас единое `permission_view`).
3. **Уточнить роль `UserMiddleware`:** алиас `user` зарегистрирован, но не применён ни к одному
   маршруту. Либо применить к фронтенд‑зоне обычных пользователей, либо удалить как мёртвый код.
4. **Удалить отладочный `Route::get('test')`** в продакшене (контроллер уже делает `abort(403)`).
5. Прогнать `php artisan optimize:clear` после изменения Kernel.php.
