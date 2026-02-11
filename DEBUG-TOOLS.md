# 🔍 DEBUG TOOLS - Rolmar API Photos

## 📋 Dostępne narzędzia:

### 1. **Web Debug Panel** (zalecane) 🌐
```bash
# Otwórz w przeglądarce:
https://twoja-domena.pl/debug-rolmar-photos.php
```

**Co pokazuje:**
- ✅ Status połączenia z API
- 🔎 Analiza konkretnych SKU
- 📊 Statystyki wszystkich produktów
- 📋 **PEŁNA struktura JSON** dla wybranych SKU
- 💡 Instrukcje co dalej

**Idealny do:**
- Pokazania Rolmar support (ładny interfejs)
- Screenshotów
- Szczegółowej analizy

---

### 2. **CLI Debug** (szybki test) 🖥️
```bash
cd /home/user/API-rolmark
php debug-photos-cli.php
```

**Co pokazuje:**
- Szybka analiza w terminalu
- Raw JSON dla testowych SKU
- Statystyki
- Przykłady produktów ze zdjęciami

**Idealny do:**
- Szybkiego sprawdzenia statusu
- Copy-paste JSON do maila
- Automatyzacji

---

## 🎯 Testowane SKU (domyślnie):

```
V-6-MBRV-02PK220
V-POM-BK
V-4WE6-CG1-24V
```

### Jak zmienić testowane SKU?

Edytuj w pliku (linia ~20):
```php
$test_skus = array(
    'V-6-MBRV-02PK220',
    'V-POM-BK',
    'TWOJ-SKU-TUTAJ',  // Dodaj swoje
);
```

---

## 📧 Template maila do Rolmar Support:

```
Temat: getPhotos API - brak URLi zdjęć dla produktów VOIMA

Witam,

Endpoint getPhotos nie zwraca URLi zdjęć dla następujących produktów:

SKU:
- V-6-MBRV-02PK220
- V-POM-BK
- V-4WE6-CG1-24V
(+ kolejne produkty marki VOIMA)

API zwraca wpisy dla tych SKU, ale pole "Photo" jest puste
lub nie zawiera tablicy z URLami.

W załączniku screenshot z debug-rolmar-photos.php pokazujący
dokładną strukturę odpowiedzi API.

Czy te produkty mają zdjęcia w waszym systemie?
Jeśli tak, proszę o sprawdzenie dlaczego nie są zwracane przez API.

Pozdrawiam,
[Twoje dane]
```

**📎 Załącz:** Screenshot z `debug-rolmar-photos.php`

---

## 🔧 Troubleshooting:

### Błąd "Klucz API nie skonfigurowany"
→ Skonfiguruj klucz w WooCommerce → Rolmar Integration

### Błąd "File not found: wp-load.php"
→ Upewnij się że skrypty są w głównym katalogu WordPress

### Timeout
→ API Rolmar może być wolne (120s timeout ustawiony)

### Nie działa w przeglądarce
→ Sprawdź czy katalog jest dostępny przez HTTP
→ Może być zablokowany przez .htaccess

---

## 💡 Co dalej?

1. **Uruchom debug** (Web lub CLI)
2. **Zrób screenshot/copy JSON**
3. **Wyślij do Rolmar support** z templatem wyżej
4. **Poczekaj na odpowiedź** od Rolmar

---

## ⚙️ Struktura plików:

```
/home/user/API-rolmark/
├── debug-rolmar-photos.php    # Web interface (browser)
├── debug-photos-cli.php        # CLI tool (terminal)
└── DEBUG-TOOLS.md             # Ten plik
```

---

## 🎯 Oczekiwany rezultat:

Po kontakcie z Rolmar powinieneś otrzymać:

✅ **"Dodaliśmy zdjęcia"** → Uruchom ponownie sync
✅ **"Użyj innego endpointa"** → Zaktualizujemy kod
❌ **"Nie ma zdjęć"** → Trzeba dostarczyć zdjęcia produktów

---

**Utworzone:** 2026-02-11
**Dla projektu:** Rolmar WooCommerce Integration
**Session:** claude/fix-download-attribute-errors-8KfDZ
