# phpLicenseWatcher

A web-based tool for monitoring FlexLM and MathLM license servers. but... ☝️a little bit better modified on my opinion

---


## Important Notes

- Tested with Apache2
- Tested with PHP version 8.4
- Tested with phpmailer version 6.11
- Tested with Debian 12 & 13 operating systems
- Tested with functional lmutil and monitorlm (binaries are only executable in Linux Standard Base)

# Requirements

- Web server capable of running PHP
- MySQL-compatible database

## Project Structure

```text
|----tools.php
|----lmtools_lib.php
|----features_admin.php
|----features_admin_db.php
|----composer.lock
|----usage.php
|----servers_admin_db.php
|----LICENSE
|----composer.json
|----license_cache.php
|----footer.html
|----docker
| |----httpd
| | |----php.ini
| | |----config.php
| | |----Dockerfile
| | |----crontab
| | |----entrypoint.sh
| |----README.md
| |----docker-compose.yml
|----features_admin_func.php
|----style.css
|----servers_edit_jquery.js
|----vagrant_provision
| |----apache
| | |----phplw.conf
| |----logrotate
| | |----phplw.conf
| |----README.md
| |----config
| | |----config.php
| |----lmtools
| | |----readme.md
| |----pl
| | |----provision.pl
| | |----update_code.pl
| | |----config.pm
|----.gitignore
|----.github
| |----ISSUE_TEMPLATE
| | |----bug_report.md
| | |----feature_request.md
|----lmtools.php
|----README.md
|----sample-config.php
|----graph_data.php
|----html_table.php
|----.git
| |----HEAD
| |----info
| | |----exclude
| |----objects
| | |----info
| | |----pack
| | | |----pack-eba2ae50a42b2bd895162c819d4ad0b55d1f1698.idx
| | | |----pack-eba2ae50a42b2bd895162c819d4ad0b55d1f1698.rev
| | | |----pack-eba2ae50a42b2bd895162c819d4ad0b55d1f1698.pack
| |----packed-refs
| |----branches
| |----config
| |----description
| |----hooks
| | |----pre-rebase.sample
| | |----fsmonitor-watchman.sample
| | |----pre-merge-commit.sample
| | |----push-to-checkout.sample
| | |----pre-receive.sample
| | |----commit-msg.sample
| | |----prepare-commit-msg.sample
| | |----post-update.sample
| | |----pre-push.sample
| | |----applypatch-msg.sample
| | |----update.sample
| | |----pre-applypatch.sample
| | |----sendemail-validate.sample
| | |----pre-commit.sample
| |----logs
| | |----HEAD
| | |----refs
| | | |----remotes
| | | | |----origin
| | | | | |----HEAD
| | | |----heads
| | | | |----master
| |----index
| |----refs
| | |----tags
| | |----remotes
| | | |----origin
| | | | |----HEAD
| | |----heads
| | | |----master
|----index.php
|----monitor_detail.php
|----vagrantfile
|----details.php
|----servers_admin_jquery.js
|----common.php
|----header.html
|----database
| |----phplicensewatcher.sql
| |----phplicensewatcher.maria.sql
| |----readme.md
| |----migrations
| | |----readme.md
| | |----migration-01.sql
| | |----migration-02.sql
| | |----migration-03.sql
|----overview_detail.php
|----logs
| |----readme.txt
|----mathematica
| |----license_util__update_servers.template
| |----license_cache.template
| |----license_util__update_licenses.template
| |----details__list_licenses_in_use.template
| |----tools__build_license_expiration_array.template
|----check_installation.php
|----license_util.php
|----servers_admin.php
|----features_admin_jquery.js
|----license_alert.php
```

---

## Installation

### 1) System Preparation

```bash
sudo apt install apache2 php mariadb-server mariadb-client php-mysql composer
```

Secure-installation (optional)
```bash
sudo mariadb-secure-installation
```

### 2) Clone the Repository

```bash
git clone https://github.com/cootera/phpLicenseWatcher.git /var/www/html/phpLicenseWatcher
cd /var/www/html/phpLicenseWatcher
```

### 3) Database Setup

```bash
mariadb
```

```sql
CREATE DATABASE licenses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON licenses.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

```SQL
exit
```

```bash
mariadb -f licenses < ./database/phplicensewatcher.maria.sql
```

### 4) PHPMailer Installation

```bash
composer require phpmailer/phpmailer
```

Update the notification email in `config.php`.

### 5) Configure Binaries

Set the path to your `lmutil` binary in `config.php & index.php` if you change it and for `monitorlm` (if available).

```bash
sudo chmod 755 /opt/lmtools/lmutil
sudo chown www-data:www-data /opt/lmtools/monitorlm
sudo chmod 755 /opt/lmtools/monitorlm
```

### 6) Setup Crontab

```bash
crontab -e
```
```cron
15 * * * * php /var/www/html/license_util.php >> /dev/null
10 * * * * php /var/www/html/license_cache.php >> /dev/null
5 8 * * * php /var/www/html/license_alert.php >> /dev/null
```

### 7) Last Steps

Configure the sample-config.php file. Rename it to config.php and change the following contents:
- Path to lmutil and/or monitorlm, if this is not /opt/lmtools, change the path to the binary files in the index.php file in lines 14 and 71
- notify_address
- smtp_host, smtp_login, smtp_password, smtp_tls, smtp_port, reply_address, lead_time, smtp_debug
- db_username and db_password

---

## Verification

After completing all steps, it should look like this:

<img width="1900" height="739" alt="Verify" src="https://github.com/user-attachments/assets/936c3c7d-0e76-44e2-8278-4a5fe2abd552" />
<img width="1916" height="777" alt="Sample-1" src="https://github.com/user-attachments/assets/0c24e985-eac0-4311-9cf6-bcb2ff76e498" />
<img width="1915" height="1048" alt="Sample-2" src="https://github.com/user-attachments/assets/2678775c-32f5-4eac-9b4f-56c406f99d70" />

---

## Changes Made

- Moved `sample-config.php` out of the `config` folder → must be renamed to `config.php`.
- In `header.html`, added a back button on the left, centered Status/Usage, and aligned admin menu on the right.
- Status **UP** is now displayed in green on `index.php` and `servers_admin.php`.
- Added **Update Servers** button in `servers_admin.php` that triggers `license_util.php` once.
- Backend in `index.php` extended: live query via `lmstat` and `monitorlm` with DB update logic (2s timeout, fallback).
- In `details.php`, changed column name **#Cur. avail** → **Total Licenses** and removed redundant license details text.


## You can find the owner of this project at the following link: https://github.com/phpLicenseWatcher/phpLicenseWatcher 
