# Vote Electronique v1 (LM-Code)

Application de vote electronique orientee entreprise, en PHP + MySQL, avec portail votant et back-office admin.

Developpeur: **Michael (LM-Code)**  
Site: https://lm-code.be

## 1. Objectif

Ce projet permet de gerer des scrutins internes/externe avec:

- authentification obligatoire,
- gestion des roles metier,
- types de scrutin multiples,
- emargement et suivi de participation,
- resultats et audit,
- notifications in-app et push navigateur.

## 2. Stack technique

- PHP 8.x
- MySQL / MariaDB
- Apache (mod_rewrite)
- Bootstrap 5 + AdminLTE
- JavaScript natif + jQuery/DataTables

## 3. Fonctionnalites principales

### 3.1 Scrutins

- Types: `SINGLE`, `MULTI`, `YESNO`, `RANKED`
- Audience: `INTERNAL`, `HYBRID`, `EXTERNAL`
- Wizard de creation (infos, regles, eligibilite, resume)
- Publication / cloture / archivage / duplication
- Synchronisation des candidats
- Verrouillage des regles critiques apres publication

### 3.2 Vote

- Controle d eligibilite par audience + role `VOTER` + emargement/groupes
- Vote modifiable si autorise (hors vote anonyme)
- Recu de vote
- Prevention du double vote selon les regles

### 3.3 Participation et emargement

- Snapshot de la liste electorale (emargement)
- Suivi participation en temps reel (mode live)
- Export CSV
- Derniers votes recus en direct

### 3.4 Notifications

- Notifications admin (bannieres portail)
- Inbox utilisateur in-app (badge + liste)
- Push navigateur (si permission accordee)
- Lien cible par notification
- Evenements automatiques:
  - scrutin publie
  - scrutin cloture
  - vote enregistre (notification personnelle)

### 3.5 Cloture automatique

Si un scrutin publie depasse sa date de fin, il est cloture automatiquement:

- a chaque acces portail,
- a chaque appel API enterprise,
- ou via script CLI `php scripts/close_elections.php`.

## 4. Roles et droits

### 4.1 SUPERADMIN

- Acces portail votant
- Acces admin complet
- Audit, Roles et Sauvegardes reserves a ce role
- Peut modifier/supprimer n importe quel scrutin

### 4.2 ADMIN

- Acces portail votant
- Acces admin metier:
  - dashboard, elections, emargement, resultats, participation
  - utilisateurs, groupes, candidats, notifications
- Pas d acces a `Audit`, `Roles`, `Sauvegardes`
- Ne peut modifier un scrutin que s il en est le createur (ou SUPERADMIN)

### 4.3 SCRUTATEUR

- Acces portail votant
- Acces supervision:
  - dashboard, elections (lecture/suivi), emargement, resultats, participation
- Pas d acces utilisateurs/groupes/candidats/notifications
- Pas d acces audit

### 4.4 VOTER

- Acces portail votant uniquement:
  - scrutins, resultats, profil

## 5. Installation rapide

1. Copier `.env.example` vers `.env`
2. Configurer la base de donnees
3. Executer les migrations

```bash
php scripts/migrate.php up
php scripts/migrate.php status
```

4. Creer un premier compte

```bash
php scripts/user_create.php --username=superadmin_lmcode --password=lm-code.be --roles=SUPERADMIN,ADMIN,SCRUTATEUR,VOTER --user_type=INTERNAL
```

5. Ouvrir l application

- Login: `/enterprise/login.php`
- Portail: `/enterprise/elections.php`
- Admin: `/enterprise/admin/dashboard.php`

## 6. Scripts utiles

- `php scripts/migrate.php up`
- `php scripts/migrate.php status`
- `php scripts/user_create.php ...`
- `php scripts/reset_demo.php --yes`
- `php scripts/close_elections.php`
- `php scripts/backup.php`

## 7. Structure du projet

- `enterprise/` pages portail et admin
- `api/ent-*.php` endpoints JSON enterprise
- `app/` bootstrap et facades legacy
- `src/Domain` regles metier
- `src/Application` cas d usage
- `src/Infrastructure` adaptateurs techniques
- `src/Controller` + `views/` interface MVC
- `assets/` JS/CSS/branding
- `migrations/` schema SQL versionne
- `scripts/` outils CLI

## 8. Clean Architecture (pragmatique)

Le projet migre vers une clean architecture sans casser les routes existantes:

- logique metier dans `src/Domain` + `src/Application`,
- details techniques en peripherie (`src/Infrastructure`),
- compatibilite legacy conservee via `app/*.php`.

## 9. Documentation publiee

Le depot public conserve volontairement une doc minimale:

- `README.md` versionne,
- autres documents de travail (`*.md`) ignores via `.gitignore`.

Le tutoriel complet est publie sur **lm-code.be**.

