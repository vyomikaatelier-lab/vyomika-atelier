# DEPLOY NOW — copy each block into Hostinger SSH

## STATUS CHECK (what is done)
- [x] Code built (shop, cart, checkout, admin, leads)
- [x] Design (luxury atelier theme)
- [x] GitHub pushed
- [x] SSL active on Hostinger
- [x] MySQL database created
- [x] .env configured on your PC
- [ ] **App deployed on server** ← YOU ARE HERE
- [ ] Website live at vyomikaatelier.com

---

## STEP 1 — Open SSH (PowerShell on your PC)

```bash
ssh -p 65002 u550969814@82.25.106.229
```

Enter your Hostinger SSH password when asked.

---

## STEP 2 — Copy ALL of this into SSH at once

```bash
cd ~
rm -rf vyomika-atelier
git clone https://github.com/vyomikaatelier-lab/vyomika-atelier.git vyomika-atelier
cd vyomika-atelier
cp .env.example .env
```

---

## STEP 3 — Edit .env on server

```bash
nano .env
```

Change these lines (use your real DB password):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vyomikaatelier.com
DB_DATABASE=u550969814_vyomika
DB_USERNAME=u550969814_vyomika
DB_PASSWORD=YOUR_DB_PASSWORD_HERE
ADMIN_EMAIL=admin@vyomikaatelier.com
ADMIN_PASSWORD=YOUR_STRONG_PASSWORD_HERE
```

Save: `Ctrl+O` → Enter → `Ctrl+X`

---

## STEP 4 — Run setup (one command)

```bash
bash setup-hostinger.sh
```

Wait 2-5 minutes. You should see `=== Done ===`

---

## STEP 5 — Open your site

https://vyomikaatelier.com (no www)

Admin: https://vyomikaatelier.com/admin
Login: admin@vyomikaatelier.com (password = ADMIN_PASSWORD from .env)

---

## If you get an error

```bash
tail -30 ~/vyomika-atelier/storage/logs/laravel.log
```

Paste the output in chat.
