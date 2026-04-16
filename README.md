# Laravel DB Sync

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%5E8.1-777BB4?style=flat&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PostgreSQL-316192?style=flat&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</p>

> 🚀 Production PostgreSQL bazasini local muhitga bir buyruq bilan ko'chiring.

Laravel dasturchilari uchun oddiy va xavfsiz vosita — prod serverdagi PostgreSQL bazasini SSH orqali local kompyuteringizga sinxronlash. Real ma'lumotlar bilan ishlash, debug qilish va test qilishni osonlashtiradi.

---

## ✨ Xususiyatlari

- 🔒 **Xavfsiz** — SSH orqali ulanish, prod bazaga faqat o'qish uchun kirish
- ⚡ **Tez** — PostgreSQL'ning native `pg_dump`/`pg_restore` vositalaridan foydalanadi
- 🎯 **Oddiy** — bitta buyruq (`php artisan db:sync-prod`)
- 🛡️ **Himoyalangan** — production muhitda ishlamaydi, har safar tasdiqlash so'raydi
- 🔧 **Avtomatik** — sequence'larni o'zi to'g'rilaydi, dump fayllarni o'zi tozalaydi
- 📊 **Moslashuvchan** — faqat data, fresh migrate, dump saqlash kabi rejimlar bor

---

## 📋 Talablar

Ishlash uchun quyidagilar kerak:

| Component | Versiya | Qayerda |
|-----------|---------|---------|
| PHP | ^8.1 | Local |
| Laravel | 10.x / 11.x / 12.x | Local |
| PostgreSQL client | 14+ | Local (pg_dump, pg_restore) |
| PostgreSQL server | 14+ | Prod server |
| SSH | Har qanday | Local → Prod |

**Local kompyuterda `pg_dump` va `pg_restore` o'rnatilganligini tekshiring:**

```bash
pg_dump --version
pg_restore --version
```

Agar yo'q bo'lsa, Ubuntu/Debian'da:
```bash
sudo apt install postgresql-client
```

---

## 📦 O'rnatish

### 1-qadam: Composer orqali

```bash
composer require nodir/db-sync --dev
```

> 💡 **Eslatma:** `--dev` flagi bilan o'rnatilyapti, chunki bu vosita faqat development muhiti uchun.

### 2-qadam: Config faylni publish qilish (ixtiyoriy)

Sozlamalarni o'zgartirmoqchi bo'lsangiz:

```bash
php artisan vendor:publish --tag=db-sync-config
```

Bu `config/db-sync.php` faylini yaratadi.

---

## ⚙️ Sozlash

### 1-qadam: SSH key sozlash

Prod serverga parolsiz kirish uchun SSH key ulanishini sozlang:

```bash
ssh-copy-id user@your-server.com
```

Tekshiring — parolsiz ishlashi kerak:
```bash
ssh user@your-server.com "echo OK"
```

### 2-qadam: `.env` faylga qo'shish

Laravel loyihangizning `.env` fayliga quyidagi o'zgaruvchilarni qo'shing:

```env
# SSH sozlamalari
PROD_SSH_HOST=your-server.com
PROD_SSH_USER=root
PROD_SSH_PORT=22

# Prod database sozlamalari
PROD_DB_NAME=your_production_db
PROD_DB_USER=postgres
PROD_DB_PASSWORD=your_production_db_password
PROD_DB_HOST=localhost
PROD_DB_PORT=5432
```

> ⚠️ **Muhim:** `.env` fayli `.gitignore` da borligiga ishonch hosil qiling. Prod parollari hech qachon Git'ga tushmasligi kerak!

### 3-qadam: Prod DB parolini topish

Prod serverdagi Laravel `.env` faylidan oling:

```bash
ssh user@your-server.com "cat /var/www/your-project/.env | grep DB_PASSWORD"
```

---

## 🚀 Foydalanish

### Oddiy sync

Barcha ma'lumotlar va schema'ni ko'chirish:

```bash
php artisan db:sync-prod
```

Natija:
```
Local bazangiz 'my_app' to'liq qayta yoziladi.
Prod server: your-server.com → production_db
Davom etamizmi? (yes/no) [no]: yes

📦 Proddan dump olinmoqda...
✅ Dump olindi: 24.5 MB
📥 Localga tiklanmoqda...
🔧 Sequence'lar to'g'rilanmoqda...
🗑  Dump fayl o'chirildi
🎉 Sync muvaffaqiyatli yakunlandi!
```

### Boshqa rejimlar

| Buyruq | Tavsif |
|--------|--------|
| `php artisan db:sync-prod` | To'liq sync (schema + data) |
| `php artisan db:sync-prod --fresh` | Avval `migrate:fresh` qiladi, keyin data yuklaydi |
| `php artisan db:sync-prod --data-only` | Faqat ma'lumotlarni oladi (schema tegilmaydi) |
| `php artisan db:sync-prod --keep-dump` | Dump faylini o'chirmaydi, `storage/app/db-sync/` da saqlaydi |

Flaglarni birlashtirish mumkin:

```bash
php artisan db:sync-prod --fresh --keep-dump
```

---

## 🔐 Xavfsizlik

### Nima qilinadi

| Baza | Amal | Tavsif |
|------|------|--------|
| **Prod** | ✅ Faqat o'qiladi | `pg_dump` faqat SELECT qiladi, hech narsa o'zgartirmaydi |
| **Local** | ⚠️ Qayta yoziladi | Eski jadvallar o'chadi, prod nusxasi bilan almashtiriladi |

### Ichki himoyalar

- ❌ **Production'da ishlamaydi** — `APP_ENV=production` bo'lsa, darhol to'xtaydi
- ✋ **Tasdiqlash so'raydi** — har safar `yes` javobi kerak
- 🔑 **SSH orqali** — PostgreSQL portini tashqariga ochmaydi
- 🧹 **Tozalash** — dump fayllar avtomatik o'chadi (agar `--keep-dump` qo'shilmasa)

### Tavsiyalar

1. **Sensitive data** — agar prod'da real foydalanuvchi ma'lumotlari bo'lsa, sync'dan keyin anonymizatsiya qiling:
   ```php
   DB::table('users')->update([
       'email' => DB::raw("CONCAT('user', id, '@test.local')"),
       'phone' => DB::raw("CONCAT('+99890', LPAD(id::text, 7, '0'))"),
   ]);
   ```

2. **Backup** — birinchi marta ishlatishdan oldin local bazani backup qiling:
   ```bash
   pg_dump -U postgres -d local_db -F c -f backup.dump
   ```

3. **Alohida backup user** — production'da `root` o'rniga faqat `pg_dump` huquqiga ega alohida SSH user yarating.

---

## 🐛 Muammolarni hal qilish

### "Permission denied (publickey)"

SSH key to'g'ri sozlanmagan. Qayta sozlang:
```bash
ssh-copy-id user@your-server.com
ssh user@your-server.com "echo OK"
```

### "pg_dump: command not found"

Prod serverda PostgreSQL client o'rnatilmagan:
```bash
ssh user@your-server.com "sudo apt install postgresql-client"
```

### "connection to server failed: fe_sendauth: no password supplied"

`.env` da `PROD_DB_PASSWORD` yo'q yoki noto'g'ri. Tekshiring:
```bash
ssh user@your-server.com "cat /var/www/your-project/.env | grep DB_PASSWORD"
```

### "pg_restore: error: could not execute query"

Odatda FK (foreign key) ogohlantirishlari, xavfsiz. Agar jiddiy xato bo'lsa, `--fresh` flagi bilan qayta urinib ko'ring:
```bash
php artisan db:sync-prod --fresh
```

### Katta bazalarda timeout

`.env` ga qo'shing:
```env
DB_SYNC_TIMEOUT=7200
```
(7200 soniya = 2 soat)

---

## 📖 Ichki ishlash tamoyili

```
┌──────────────┐      SSH      ┌──────────────┐
│    Local     │──────────────▶│     Prod     │
│              │  pg_dump      │              │
│              │◀──────────────│              │
│              │   .dump file  │              │
└──────┬───────┘               └──────────────┘
       │
       ▼ pg_restore
┌──────────────┐
│  Local DB    │
│  (yangilanadi)│
└──────────────┘
```

1. SSH orqali prod serverga ulanadi
2. Prod'da `pg_dump` ishga tushadi, natija local'ga stream bilan kelib tushadi
3. Local'da `pg_restore` orqali bazaga yoziladi
4. Sequence'lar avtomatik to'g'rilanadi
5. Dump fayl tozalanadi

---

## 🛣️ Yo'l xaritasi

Kelajakda qo'shilishi mumkin bo'lgan xususiyatlar:

- [ ] MySQL qo'llab-quvvatlash
- [ ] Bir nechta prod baza profillari
- [ ] Faqat tanlangan jadvallarni sync qilish (`--tables=users,orders`)
- [ ] Avtomatik anonymizatsiya konfiguratsiyasi
- [ ] Progress bar
- [ ] Docker'da PostgreSQL qo'llab-quvvatlash

---

## 🤝 Hissa qo'shish

Pull request'lar xush kelibsiz! Katta o'zgarishlar uchun avval issue oching.

1. Repository'ni fork qiling
2. Feature branch yarating (`git checkout -b feature/zo-r-narsa`)
3. Commit qiling (`git commit -m 'Zo"r narsa qo"shildi'`)
4. Push qiling (`git push origin feature/zo-r-narsa`)
5. Pull Request oching

---

## 📄 Litsenziya

[MIT License](LICENSE) - xohlaganingizcha ishlatishingiz mumkin.

---

## 👨‍💻 Muallif

**Nodir** — Senior PHP Developer, Uzbekistan

- 🌐 GitHub: [@Nodir7393](https://github.com/YOUR_USERNAME)
- 💼 Ish: Laravel, Yii2, Next.js, PostgreSQL

---

## ⭐ Loyihaga yordam berish

Agar paket yoqdi va ishingizga yaragan bo'lsa:

- ⭐ GitHub'da yulduzcha qo'ying
- 🐛 Topilgan bug'lar haqida xabar bering
- 💡 Yangi g'oyalar bilan bo'lishing
- 📢 Do'stlaringiz bilan ulashing

---

<p align="center">
  Made with ❤️ in Uzbekistan 🇺🇿
</p>
