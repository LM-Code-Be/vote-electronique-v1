# Application de gestion de Vote Electronique (LM-Code)

Application web de vote electronique orientee entreprise, developpee en PHP + MySQL, avec:

- portail votant,
- back-office admin,
- gestion des roles,
- suivi de participation live,
- notifications in-app et push navigateur.

Developpeur: **Michael - LM-Code**  
Site: https://lm-code.be

---
<img width="1917" height="939" alt="image" src="https://github.com/user-attachments/assets/5e3c4c2a-2ef7-4cf6-aa9d-f0c3158686e9" />

## Sommaire

1. [Fonctionnalites](#fonctionnalites)
2. [Stack technique](#stack-technique)
3. [Architecture](#architecture)
4. [Installation rapide](#installation-rapide)
5. [Roles et permissions](#roles-et-permissions)
6. [Captures UI](#captures-ui)
7. [Scripts utiles](#scripts-utiles)
8. [Documentation](#documentation)

---

## Fonctionnalites

- Types de scrutin: `SINGLE`, `MULTI`, `YESNO`, `RANKED`
- Audiences: `INTERNAL`, `HYBRID`, `EXTERNAL`
- Candidats lies a des utilisateurs existants
- Creation rapide de candidats externes
- Emargement (snapshot), participation, resultats, audit
- Notifications admin + inbox utilisateur live
- Cloture automatique des scrutins expires

---

## Stack technique

- Backend: PHP 8.x
- Base de donnees: MySQL / MariaDB
- Frontend: Bootstrap 5, AdminLTE, JS natif, jQuery, DataTables
- Architecture: Clean Architecture pragmatique (migration progressive)

---

## Architecture

- `src/Domain`: regles metier pures
- `src/Application`: cas d usage / services applicatifs
- `src/Infrastructure`: adaptateurs techniques (PDO, HTTP, composition)
- `src/Controller` + `views`: interface web MVC
- `api/ent-*.php`: endpoints JSON enterprise
- `app/*.php`: facades legacy de compatibilite

---

## Installation rapide

### 1) Recuperer le projet

```bash
git clone https://github.com/LM-Code-Be/vote-electronique-v1.git
cd vote-electronique-v1
```

### 2) Configurer l environnement

```bash
cp .env.example .env
```

Renseigner au minimum:

- `APP_BASE_PATH`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### 3) Creer la base de donnees

Exemple MySQL:

```sql
CREATE DATABASE vote_electronique CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4) Initialiser la base (migrations)

```bash
php scripts/migrate.php up
php scripts/migrate.php status
```

### 5) Creer un compte de depart

```bash
php scripts/user_create.php --username=superadmin_lmcode --password=lm-code.be --roles=SUPERADMIN,ADMIN,SCRUTATEUR,VOTER --user_type=INTERNAL
```

### 6) Ouvrir l application

- Login: `/enterprise/login.php`
- Portail: `/enterprise/elections.php`
- Admin: `/enterprise/admin/dashboard.php`

---

## Roles et permissions

### SUPERADMIN

- Acces total admin
- Acces exclusif: `Audit`, `Roles`, `Sauvegardes`
- Peut modifier/supprimer tous les scrutins

### ADMIN

- Acces admin metier (elections, users, groupes, candidats, notifications, etc.)
- Pas d acces `Audit`, `Roles`, `Sauvegardes`
- Peut modifier un scrutin seulement s il en est createur (sinon SUPERADMIN)

### SCRUTATEUR

- Acces supervision: dashboard, elections, emargement, participation, resultats
- Pas d administration utilisateurs/groupes/candidats
- Pas d acces audit

### VOTER

- Acces portail votant uniquement

---

## Captures UI

Ce README est pret pour inserer des captures d ecran.
Place tes images dans `assets/screenshots/` puis remplace les chemins ci-dessous.

### Login

![Login](assets/screenshots/login.png)

### Dashboard admin

![Dashboard](assets/screenshots/admin-dashboard.png)

### Wizard creation election

![Wizard election](assets/screenshots/admin-election-wizard.png)

### Gestion candidats

![Candidats](assets/screenshots/admin-candidates.png)

### Participation live

![Participation live](assets/screenshots/admin-participation-live.png)

### Portail votant

![Portail votant](assets/screenshots/portal-elections.png)

### Notification in-app

![Notifications](assets/screenshots/portal-notifications.png)

### Resultats

![Resultats](assets/screenshots/portal-results.png)

---

## Scripts utiles

- `php scripts/migrate.php up`
- `php scripts/migrate.php status`
- `php scripts/user_create.php ...`
- `php scripts/reset_demo.php --yes`
- `php scripts/close_elections.php`
- `php scripts/backup.php`

---

## Documentation

- Le depot public conserve la doc essentielle: `README.md`
- Découvrez nos articles et tutoriels complet sur https://lm-code.be

Le tutoriel complet est disponible ici :  
👉 **[Le tutoriel complet](https://lm-code.be/vote-electronique-php-mysql-tutoriel-complet/)**
