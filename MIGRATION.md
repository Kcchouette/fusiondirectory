# Guide de Migration des Plugins FusionDirectory

Ce document décrit les changements d'API dans FusionDirectory core et comment migrer les plugins existants.

---

## Table des matières

1. [Renommage des classes](#1-renommage-des-classes)
2. [Renommage des fichiers](#2-renommage-des-fichiers)
3. [Renommage des méthodes](#3-renommage-des-méthodes)
4. [Types ajoutés aux propriétés](#4-types-ajoutés-aux-propriétés)
5. [Injection de dépendance](#5-injection-de-dépendance)
6. [God classes découpées](#6-god-classes-découpées)
7. [Script de migration automatique](#7-script-de-migration-automatique)

---

## 1. Renommage des classes

Les classes ont été renommées en `PascalCase`. Les anciens noms ne fonctionnent plus.

| Ancien | Nouveau |
|--------|---------|
| `config` | `Config` |
| `userinfo` | `UserInfo` |
| `session` | `Session` |
| `pluglist` | `Pluglist` |
| `acl` | `Acl` |
| `logging` | `Logging` |
| `tests` | `Tests` |
| `baseSelector` | `BaseSelector` |
| `divSelectBox` | `DivSelectBox` |
| `ldapFilter` | `LdapFilter` |
| `ldapMultiplexer` | `LdapMultiplexer` |
| `msgPool` | `MsgPool` |
| `msg_dialog` | `MsgDialog` |
| `objects` | `Objects` |
| `passwordRecovery` | `PasswordRecovery` |
| `template` | `Template` |
| `templateHandling` | `TemplateHandling` |
| `timezone` | `Timezone` |
| `simplePlugin` | `SimplePlugin` |
| `simpleService` | `SimpleService` |
| `simpleTabs` | `SimpleTabs` |
| `multiPlugin` | `MultiPlugin` |
| `management` | `Management` |
| `managementFilter` | `ManagementFilter` |
| `managementListing` | `ManagementListing` |
| `selectManagement` | `SelectManagement` |
| `templateDialog` | `TemplateDialog` |
| `passwordMethod` | `PasswordMethod` |

### Migration

```php
// Avant
class monPlugin extends simplePlugin
{
    function plInfo()
    {
        return ['plShortName' => _('Mon Plugin')];
    }
}

// Après
class monPlugin extends SimplePlugin
{
    public static function plInfo(): array
    {
        return ['plShortName' => _('Mon Plugin')];
    }
}
```

---

## 2. Renommage des fichiers

Les fichiers ont été renommés de `class_Name.inc` à `ClassName.php`.

| Ancien | Nouveau |
|--------|---------|
| `class_config.inc` | `Config.php` |
| `class_userinfo.inc` | `UserInfo.php` |
| `class_session.inc` | `Session.php` |
| `class_pluglist.inc` | `Pluglist.php` |
| `class_ldap.inc` | `Ldap.php` |
| `simpleplugin/class_simplePlugin.inc` | `simpleplugin/SimplePlugin.php` |
| `management/class_management.inc` | `management/Management.php` |
| Tous les `*.inc` | `*.php` |

### Migration

Dans vos plugins, mettez à jour les `require_once` :

```php
// Avant
require_once('class_simplePlugin.inc');

// Après
require_once('SimplePlugin.php');
```

---

## 3. Renommage des méthodes

Les méthodes `snake_case` ont été renommées en `camelCase`.

| Ancien | Nouveau |
|--------|---------|
| `get_objectclasses()` | `getObjectClasses()` |
| `acl_is_writeable()` | `aclIsWriteable()` |
| `acl_is_readable()` | `aclIsReadable()` |
| `is_this_account()` | `isThisAccount()` |
| `loadLDAPInfo()` | `loadLdapInfo()` |
| `get_template_path()` | `getTemplatePath()` |
| `convert_department_dn()` | `convertDepartmentDn()` |
| `array_remove_entries()` | `arrayRemoveEntries()` |
| `fusiondirectory_log()` | `fusiondirectoryLog()` |

### Migration

Recherchez et remplacez dans vos plugins :

```bash
# Trouver les appels à renommer
grep -rn 'get_objectclasses\|acl_is_writeable\|is_this_account' plugins/
```

---

## 4. Types ajoutés aux propriétés

Toutes les propriétés de classes ont des types PHP.

```php
// Avant
class monPlugin extends simplePlugin
{
    var $monAttribut = FALSE;
    protected $monObjet = NULL;
}

// Après
class monPlugin extends SimplePlugin
{
    public bool $monAttribut = false;
    protected ?object $monObjet = null;
}
```

### Migration

- Remplacez `var $` par `public`/`protected`/`private`
- Ajoutez les types : `bool`, `string`, `int`, `array`, `?type`, `mixed`
- Remplacez `FALSE` par `false`, `TRUE` par `true`, `NULL` par `null`

---

## 5. Injection de dépendance

Les globals `$config`, `$ui`, `$plist` sont remplacés par des fonctions d'accès.

```php
// Avant
function maMethode()
{
    global $config, $ui;
    $base = $config->get_cfg_value('base');
    $dn = $ui->dn;
}

// Après
function maMethode(): void
{
    $base = config()->get_cfg_value('base');
    $dn = user_info()->dn;
}
```

### Fonctions disponibles

| Fonction | Remplace |
|----------|----------|
| `config()` | `global $config` |
| `user_info()` | `global $ui` |
| `pluglist()` | `global $plist` |
| `container()` | Container PSR-11 |

---

## 6. God classes découpées

`SimplePlugin` a été découpé en 4 composants. L'API reste disponible via la facade.

| Composant | Accès |
|-----------|-------|
| ACL | `$this->acl->isWriteable('attr')` |
| Rendu | `$this->renderer->render()` |
| Hooks | `$this->hooks->callHook('save')` |
| LDAP | `$this->ldapReader->ldapSave()` |

L'ancien code fonctionne toujours (la facade délège), mais le nouveau code doit utiliser les composants.

---

## 7. Script de migration automatique

Un script de migration est disponible :

```bash
# Migration des classes
bash tools/migrate-plugins.sh /path/to/fusiondirectory-plugins/
```

Le script effectue :
1. Renommage des classes (`simplePlugin` → `SimplePlugin`)
2. Renommage des fichiers (`.inc` → `.php`)
3. Renommage des méthodes (`snake_case` → `camelCase`)
4. Remplacement des globals (`global $config` → `config()`)
5. Ajout des types aux propriétés

### Vérification après migration

```bash
# Vérifier la syntaxe
find /path/to/plugins -name '*.php' | xargs php -l

# Lancer les tests
cd /path/to/fusiondirectory && make test
```

---

## Checklist de migration

- [ ] Classes renommées en PascalCase
- [ ] Fichiers renommés en `.php`
- [ ] Méthodes renommées en camelCase
- [ ] Propriétés typées
- [ ] Globals remplacés par fonctions d'accès
- [ ] `var $` remplacé par visibilité
- [ ] `declare(strict_types=1)` ajouté
- [ ] Syntaxe PHP vérifiée (`php -l`)
- [ ] Tests passent (`make test`)
