# Chef À La Carte kurulum rehberi

Bu rehber Hostinger üzerinde temiz kurulum içindir.
Paketler yerelde test edildi. Canlı yayın bu rehberle henüz yapılmadı.
Komutlardaki `<...>` alanlarını kendi değerlerinizle değiştirin.

## 1. Gereksinimler

| Bileşen | Gereksinim | Yerel test |
|---|---|---|
| PHP | 8.3.x | 8.3.33 |
| PHP uzantıları | `pdo_mysql`, `fileinfo`, `mbstring`, `gd` (WebP desteği), `ctype`, `json` | Doğrulandı |
| PHP ayarları | `upload_max_filesize` ve `post_max_size` en az `6M`. `display_errors=0` | Doğrulandı |
| Veritabanı | MariaDB 10.6 veya üstü, ya da MySQL 8.0 veya üstü. InnoDB | MariaDB 11.8.9, MySQL 8.4.3 |
| Web sunucusu | `.htaccess` destekli Apache veya LiteSpeed | Apache 2.4.68 |
| HTTPS | Zorunlu. Admin paneli HTTP üzerinde açılmaz | Doğrulandı |
| Node.js | Sunucuda gerekmez | - |

Collation kararı: Tablolar yalnız `utf8mb4_unicode_ci`, `utf8mb4_turkish_ci` ve `ascii_bin` kullanır.
Bu collation değerleri eski ve yeni MariaDB ile MySQL sürümlerinde vardır.
`utf8mb4_uca1400_ai_ci` ve `utf8mb4_0900_ai_ci` kullanılmaz.

## 2. Dizin düzeni

```text
/home/<hesap>/
├── domains/<alan-adi>/public_html/   <- public_html.zip içeriği (belge kökü)
│   ├── index.html                    <- misafir menüsü
│   ├── .htaccess
│   ├── media/                        <- herkese açık ürün, kategori, kampanya görselleri
│   ├── database/lib.php              <- admin kodu kullanır. .htaccess 404 döndürür
│   └── admin/                    <- admin paneli ve API
└── chef-private/                     <- chef-private-setup.zip içeriği (belge kökü dışında)
    ├── config.php                    <- gerçek ayarlar, izin 0600
    ├── logs/                         <- uygulama günlüğü
    ├── media/                        <- özel geri bildirim görselleri
    ├── tmp/media/                    <- geçici yükleme dosyaları
    ├── legacy-uploads/               <- boş klasör. Temiz kurulumda eski yükleme yoktur
    ├── database/                     <- CLI, migration, seed, kurtarma araçları
    ├── admin/                    <- CLI araçlarının kullandığı iki PHP dosyası
    └── sql/chef-install-2026101501.sql  <- phpMyAdmin kurulum dosyası
```

Sahiplik varsayımı: Tüm dosyalar hesap kullanıcısına aittir. PHP aynı kullanıcı ile çalışır.
İzinler: public dosyalar `0644`, public klasörler `0755`, `config.php` `0600`, özel klasörler `0700`.

Alt dizin kurulumu: Site alan adı kökünde değilse `<BASE_PATH>` değerini kullanın.
Örnek: `/menu`. Bu değer `config.php` içindeki oturum yolu ve medya adresine yazılır.
Misafir menüsü ve admin paneli göreli yollar kullanır. Başka değişiklik gerekmez.

## 3. Kurulumdan önce yedek

1. hPanel > Dosyalar > Yedeklemeler bölümünden tam dosya yedeği alın.
2. phpMyAdmin ile mevcut veritabanını `Dışa aktar > Hızlı > SQL` olarak indirin.
3. Mevcut `public_html` klasörünü `public_html-yedek-<tarih>` olarak sıkıştırın ve indirin.
4. Yedek dosyalarını yerel bilgisayarda saklayın. Yedekleri `public_html` içinde bırakmayın.

## 4. Veritabanı ve dosyalar

1. hPanel > Veritabanları bölümünde yeni ve boş bir veritabanı oluşturun.
2. Bu veritabanı için güçlü parolalı yeni bir kullanıcı oluşturun.
3. File Manager ile `public_html.zip` dosyasını belge köküne yükleyin ve çıkarın.
4. `chef-private-setup.zip` dosyasını `/home/<hesap>/chef-private/` klasörüne yükleyin ve çıkarın.
5. Zip dosyalarını çıkarma işleminden sonra silin.
6. `chef-private` içinde `logs`, `media`, `tmp/media` ve `legacy-uploads` klasörlerini oluşturun.

## 5. Özel yapılandırma

1. `chef-private/config.production.example.php` dosyasını aynı klasörde `config.php` adıyla kopyalayın.
2. Tüm `<...>` alanlarını doldurun. Veritabanı sunucusu genellikle `localhost` olur.
3. `<RANDOM_HMAC_KEY>` için en az 32 karakterlik rastgele bir değer üretin.
4. `<DUMMY_PASSWORD_HASH>` için `--hash-only` çıktısını kullanın. Bu parolayı saklamayın.
5. `config.php` izinlerini `0600` yapın. File Manager > İzinler bölümünü kullanın.
6. Belge kökündeki `.htaccess` dosyasının en üstüne şu satırı ekleyin:

```apache
SetEnv CHEF_CONFIG_FILE /home/<hesap>/chef-private/config.php
```

7. hPanel > PHP Yapılandırması bölümünde `display_errors` değerini kapalı tutun.
8. hPanel > SSL bölümünde sertifikayı etkinleştirin. HTTPS yönlendirmesini açın.

## 6A. SSH ile kurulum (önerilen)

Önce ortam değişkenlerini tanımlayın:

```bash
cd /home/<hesap>/chef-private
export CHEF_DB_CONFIG=/home/<hesap>/chef-private/config.php
export CHEF_CONFIG_FILE=/home/<hesap>/chef-private/config.php
export DOCUMENT_ROOT=/home/<hesap>/domains/<alan-adi>/public_html
```

Sonra sırayla çalıştırın:

```bash
php database/cli.php migrate
php database/cli.php seed
php database/cli.php verify
php database/media-cli.php prepare
php -d variables_order=EGPCS database/recover-owner.php --create
```

Son komut kullanıcı adını ve parolayı gizli girişle ister.
Parola en az 12 karakter olmalıdır. `--create` yalnız hiç yönetici yokken çalışır.

## 6B. SSH olmadan kurulum (File Manager ve phpMyAdmin)

1. phpMyAdmin içinde yeni veritabanını seçin.
2. `İçe aktar` sekmesinde `chef-private/sql/chef-install-2026101501.sql` dosyasını seçin. Bu dosyayı önce bilgisayarınıza indirin.
3. Karakter seti `utf8mb4` olsun. İçe aktarmayı başlatın.
4. Dosya mevcut tabloları silmez. Dolu bir veritabanında hata verir. Bu beklenen davranıştır.
5. Yerel bilgisayarda Laragon PHP ile parola hash değeri üretin:

```powershell
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe database\recover-owner.php --hash-only
```

6. phpMyAdmin `SQL` sekmesinde şu sorguyu çalıştırın. Hash değerini tek tırnak içinde yapıştırın:

```sql
INSERT INTO admins (username, password_hash, auth_version) VALUES ('<kullanici-adi>', '<HASH>', 1);
```

7. File Manager ile şu klasörleri oluşturun:
   - `public_html/media/products`, `public_html/media/categories`, `public_html/media/campaigns`, `public_html/media/.pending`
   - `chef-private/media/feedback`, `chef-private/media/.pending`
8. `public_html/media/.htaccess` dosyası paketle gelir. Bu dosyayı silmeyin veya değiştirmeyin.

## 7. Zamanlanmış temizlik görevi (zorunlu)

Silinen görsellerin bekleyen temizlik işlemleri bu görevle tekrar denenir.
hPanel > Gelişmiş > Cron İşleri bölümüne beş dakikalık bir görev ekleyin:

```bash
CHEF_DB_CONFIG=/home/<hesap>/chef-private/config.php php /home/<hesap>/chef-private/database/media-cli.php cleanup
```

Kalıcı `cleanup_pending` kayıtlarını `php database/media-cli.php status` ile izleyin.

## 8. Kurulum sonrası kontroller

1. `https://<alan-adi>/` adresi menüyü 4 kategori ve 24 ürünle gösterir.
2. `https://<alan-adi>/admin/login.php` giriş sayfasını açar.
3. Oluşturduğunuz owner hesabı ile giriş yapın. Bir fiyatı değiştirip menüde kontrol edin.
4. Görselli bir geri bildirim gönderin. Admin gelen kutusundan silin.
5. Şu adresler içerik göstermemelidir (403 veya 404):
   - `https://<alan-adi>/database/lib.php`
   - `https://<alan-adi>/admin/config.php`
   - `https://<alan-adi>/media/`
6. `media` klasörüne geçici `test.php` koyun. İstek `403` döndürmelidir. Dosyayı hemen silin.
7. Ürün görseli yükleyin. Görsel adresi tarayıcıda `200` ile açılmalıdır.

## 9. CDN ve önbellek kuralları

Hostinger CDN etkinse şu yolları önbellek dışında tutun:

- `/admin/*` (admin sayfaları, API ve özel görseller)
- `/index.html` ve `/` için kısa veya sıfır önbellek süresi kullanın.

Admin yanıtları `Cache-Control: no-store` gönderir. Menü API `no-cache` ve `ETag` kullanır.
`/media/*` görselleri önbelleğe alınabilir. Dosya adları her yüklemede değişir.

## 10. Yükseltme

1. Bölüm 3'teki yedekleri alın.
2. Yeni `public_html.zip` içeriğini belge köküne çıkarın. `media/` klasörünü silmeyin.
   Paket dosyalarının tarihi sabittir. LiteSpeed eski sayfayı önbellekten verebilir.
   SSH ile şu komutu çalıştırın: `find <belge-koku> -path '<belge-koku>/media' -prune -o -type f -exec touch {} +`
3. Yeni `chef-private-setup.zip` içeriğini çıkarın. `config.php`, `logs/` ve `media/` klasörlerini koruyun.
4. `.htaccess` içindeki `SetEnv` satırını tekrar ekleyin.
5. `php database/cli.php migrate` ve `php database/cli.php verify` komutlarını çalıştırın.
6. Migration dosyaları yalnız ekleme yapar. Mevcut tabloları veya satırları silmez.
7. SSH yoksa yükseltmeyi ertelemeyin. Yeni sürümün yükseltme SQL dosyasını isteyin.

## 11. Yedekleme ve geri alma

Düzenli yedek:

- Veritabanı: phpMyAdmin ile haftalık SQL dışa aktarımı alın.
- Dosyalar: `public_html/media/` ve `chef-private/media/` klasörlerini haftalık indirin.
- Yapılandırma: `config.php` dosyasının bir kopyasını şifreli ve çevrimdışı saklayın.

Geri alma:

1. Siteyi geçici olarak bakım sayfasına alın veya hPanel ile parola koruması ekleyin.
2. Eski `public_html` yedeğini geri yükleyin.
3. Eski `chef-private` dosyalarını geri yükleyin. `config.php` dosyasını koruyun.
4. Migration veri değiştirdiyse veritabanını kurulum öncesi SQL yedeğinden geri yükleyin.
5. Geri alma sınırı: Yedekten sonra gelen geri bildirimler ve fiyat değişiklikleri kaybolur.
6. Geri alma sınırı: Yedekten sonra silinen görseller yalnız dosya yedeğinden geri gelir.
7. Bölüm 8 kontrollerini tekrar çalıştırın.

## 12. Açık hosting kontrolleri

Bu kontroller yerelde yapılamaz. İlk canlı kurulumda doğrulayın:

- LiteSpeed `SetEnv CHEF_CONFIG_FILE` değerini PHP'ye iletir mi? Admin `503` dönerse günlüğü kontrol edin.
- LiteSpeed `media/.htaccess` ile PHP yürütmeyi engelliyor mu? Bölüm 8 madde 6.
- `media-cli.php prepare` klasörleri `0700` oluşturur. Görseller `200` ile açılmıyorsa public `media` klasörlerini `0755` yapın.
- Cron işi beş dakikada bir çalışıyor mu? `media-cli.php status` çıktısını kontrol edin.
- Hostinger CDN admin yollarını önbelleğe almıyor mu?
- Hesabın gerçek MariaDB sürümü 10.6 veya üstü mü?
