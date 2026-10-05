# Dépannage MongoDB Atlas — TLS / « No suitable servers found »

## Symptôme

```
TLS handshake failed: error:14094438:SSL routines:ssl3_read_bytes:tlsv1 alert internal error
No suitable servers found (`serverSelectionTryOnce` set)
```

## 1. Autoriser votre IP dans Atlas (cause la plus fréquente)

1. [MongoDB Atlas](https://cloud.mongodb.com) → votre projet **TexTileCycle**
2. **Network Access** (menu gauche)
3. **Add IP Address** → **Add Current IP Address**
4. Attendre 1–2 minutes, puis retester

En développement seulement : `0.0.0.0/0` (accès depuis n’importe quelle IP — **ne pas utiliser en production**).

## 2. Certificats TLS (Windows / XAMPP)

Le fichier `storage/certs/cacert.pem` est fourni (bundle Mozilla). Laravel l’utilise automatiquement si présent.

Optionnel dans `.env` :

```env
MONGODB_TLS_CA_FILE=C:\chemin\vers\storage\certs\cacert.pem
```

Mettre à jour aussi `php.ini` :

```ini
openssl.cafile=C:\Users\MSI\Desktop\plateforme-tex-tile-cycle\storage\certs\cacert.pem
extension=mongodb
```

Redémarrer Apache / le terminal après modification de `php.ini`.

## 3. Tester la connexion

```powershell
cd C:\Users\MSI\Desktop\plateforme-tex-tile-cycle
php artisan config:clear
php artisan mongodb:ping
```

## 4. Si l’erreur persiste

- Vérifier **Database Access** : utilisateur `missaouimourad_db_user` actif, mot de passe régénéré si besoin (mettre à jour `MONGODB_URI` dans `.env`).
- Désactiver temporairement antivirus / VPN qui inspecte le SSL.
- Passer à **PHP 8.2+** (OpenSSL 3) — XAMPP 8.0 peut échouer avec certains clusters Atlas récents.
- Tester avec **MongoDB Compass** (même URI) : si Compass échoue aussi → problème réseau/Atlas ; si Compass OK → PHP/OpenSSL.

## 5. Sécurité

Ne commitez jamais `.env`. Si le mot de passe MongoDB a été exposé, **régénérez-le** dans Atlas → Database Access → Edit user.
