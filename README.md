# Baltic Wave CMS

Moderni, lengva turinio valdymo sistema (PHP 8 + MySQL), sukurta „Baltic Wave“
projektui — pasauliniam realaus laiko muzikos renginiui.

## Galimybės

- **Vizualus drag & drop redaktorius** — kiekvienas puslapis yra laisva drobė:
  bloką (antraštę, tekstą, nuotrauką, YouTube video, mygtuką, citatą, galeriją,
  video sąrašą, laikmatį, kontaktų formą, HTML) padedate **bet kur**, keičiate
  plotį, sluoksnį ir padėtį pikselio tikslumu. Mobiliuose įrenginiuose blokai
  automatiškai išsirikiuoja vertikaliai.
- **Puslapių valdymas** — naujas puslapis automatiškai gauna savo fizinį
  `.php` failą (pvz. `partneriai.php`), SEO meta aprašymą, juodraščio būseną.
- **Meniu redaktorius** — tempiamas eiliškumas, dviejų lygių submeniu,
  matomumo valdymas.
- **Galerija** — albumai, kelių failų įkėlimas vilkimu, aprašymai, tempiamas
  rikiavimas, lightbox peržiūra svetainėje.
- **Video** — YouTube įrašų sąrašas su tempiamu rikiavimu.
- **Kontaktų forma + žinučių dėžutė** administravimo pulte.
- **Administratorių valdymas** — registracija atvira tik pirmam vartotojui,
  vėliau paskyras kuria administratorius; CSRF apsauga, bcrypt slaptažodžiai,
  bandymų ribojimas.
- **`setupdb.php` migracijos** — po kiekvieno kodo atnaujinimo paleidus šį
  failą automatiškai sukuriamos / atnaujinamos DB lentelės ir (pirmą kartą)
  užpildomas visas pradinis „Baltic Wave“ turinys.

## Diegimas

1. Nukopijuokite projektą į serverio šakninį katalogą (Apache / Nginx + PHP 8.1+,
   MySQL / MariaDB).
2. `config.php` faile nurodykite DB prisijungimą (`DB_HOST`, `DB_NAME`,
   `DB_USER`, `DB_PASS`) — arba naudokite `BW_DB_*` aplinkos kintamuosius.
3. Naršyklėje atidarykite **`setupdb.php`** — jis sukurs duomenų bazę,
   lenteles ir pradinį turinį.
4. Spauskite „Sukurti administratoriaus paskyrą“ ir susikurkite pirmąjį
   administratorių.
5. Valdykite svetainę per **`/admin/`**.

Katalogai `uploads/` ir šakninis katalogas (naujų puslapių `.php` failams)
turi būti įrašomi PHP procesui.

## Po kodo atnaujinimo

Naujos DB schemos pakeitimai registruojami `setupdb.php` faile
(`$BW_MIGRATIONS` masyve). Po `git pull` tiesiog atidarykite `setupdb.php` —
pritaikomos tik dar nepritaikytos migracijos, turinys nepažeidžiamas.

## Struktūra

```
config.php            DB ir kelio nustatymai
setupdb.php           diegimas + migracijos + pradinis turinys
index.php             pradinis puslapis (slug „home“)
page.php              dinaminis puslapių atvaizdavimas
<slug>.php            automatiškai generuojami puslapių failai
includes/             db, auth, funkcijos, blokų atvaizdavimas, šablonai
assets/               viešosios svetainės CSS/JS
admin/                administravimo pultas
admin/builder.php     vizualus drag & drop redaktorius
admin/api.php         JSON API (maketai, įkėlimai, rikiavimas, meniu)
uploads/              įkeltos nuotraukos
```
