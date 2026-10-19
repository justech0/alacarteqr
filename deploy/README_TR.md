# Hostinger staging deploy

Bu betik, Chef staging paketini tek komutla kurar. Betiği kullanıcı çalıştırır.

## Ön koşullar

- PowerShell 7 veya daha yeni bir sürüm bulunmalıdır.
- Windows üzerinde `ssh.exe`, `scp.exe`, `ssh-keygen.exe` ve `curl.exe` bulunmalıdır.
- Şu iki arşiv hazır olmalıdır:
  - `dist/hostinger-staging/public_html.zip`
  - `dist/hostinger-staging/chef-private-setup.zip`
- `taslak.bilincreklam.com` için ayrı subdomain ve ayrı staging veritabanı hazır olmalıdır.
- hPanel Remote MySQL kaydı yalnız staging test süresince açık kalmalıdır.
- Otomatik HTTPS kontrolleri sırasında site Basic Auth arkasında olmamalıdır.
- Başarılı kontrolden sonra hPanel directory password protection etkinleştirilmelidir.

## Çalıştırma

Depo kökünde şu komutu çalıştırın:

```powershell
pwsh.exe -NoProfile -File .\deploy\deploy-staging.ps1
```

Betiği yönetici olarak çalıştırmak gerekmez.

Sunucuya bağlanmadan yerel betik kontrollerini çalıştırmak için:

```powershell
pwsh.exe -NoProfile -File .\deploy\deploy-staging.ps1 -LocalValidationOnly
```

## Kullanıcının girdiği bilgiler

İlk çalıştırmada şu girişler istenir:

1. OpenSSH, hesap parolasını bir kez ister. Bu parola yalnız açık anahtarı kurar.
2. Betik bulunan belge kökünü gösterir. Onay için tam olarak `EVET` yazın.
3. PowerShell, staging veritabanı parolasını gizli girişle ister.
4. Uzak kurtarma aracı, owner kullanıcı adını ve yeni parolayı ister.

Parolalar ekranda görünmez. Betik bunları rapora veya komut satırına yazmaz.
SSH özel anahtarı kullanıcı profilindeki `.ssh` dizininde kalır.
Veritabanı parolası yalnız sunucudaki izinleri kısıtlı özel yapılandırmaya yazılır.

## Betik ne yapar

- Mevcut belge kökünü overwrite öncesinde `~/backups` altında yedekler.
- Public arşivi bulunan belge köküne çıkarır.
- Private arşivi `~/chef-private` altında çıkarır.
- Uzak PHP sürümünü ve gerekli PHP uzantılarını kontrol eder.
- Veritabanını önce `127.0.0.1`, gerekirse `localhost` ile sınar.
- Migration, seed, medya hazırlığı ve owner recovery komutlarını çalıştırır.
- Beş dakikalık medya cleanup cron kaydını tekilleştirir.
- HTTPS, PHP hata görünürlüğü, upload yürütme koruması ve dizin listeleme kontrollerini yapar.
- `readonly-check.php row-counts` kontrolünü geçici dosyayla bir kez çalıştırır.
- Sonucu `docs/reviews/staging-deploy.md` ve `PROJECT.md` içine kaydeder.
- Task 12 durumunu değiştirmez.

Uzak MySQL sunucusu kullanılmaz. Uygulama sunucu içi bağlantıyı kullanır.

## Sonucu okuma

Betiğin sonunda 15 satırlı bir `PASS` veya `FAIL` tablosu görünür.

- Tüm satırlar `PASS` ise staging deploy tamamlanmıştır.
- Bir satır `FAIL` ise betik o adımda durur.
- Admin isteği `503` döndürürse özel günlüğün son 20 satırı gösterilir.
- `503`, LiteSpeed altında `SetEnv` değerinin PHP'ye ulaşmadığını gösterebilir.
- Betik bu durumda geçici çözüm üretmez.
- Sunucuda `crontab` yoksa Hostinger cron paneline eklenecek kesin satır yazdırılır.

## Tekrar çalıştırma

Betik ikinci çalıştırmada aynı SSH anahtarını kullanır.
`SetEnv` ve cron kayıtlarını çoğaltmaz.
Her çalıştırma yeni bir belge kökü yedeği oluşturur.
Public test dosyaları kontrolden sonra kaldırılır.

## Adım 6 devamı

Adım 6 arşiv çıkarma hatasından sonra şu komutu kullanın.
Önce public kökün 33 paket dosyasıyla eşleştiğini doğrulayın.
2026-10-17 son kontrolünde public kök boştu. Bu fark çözülmeden komutu çalıştırmayın.

```powershell
pwsh.exe -NoProfile -File .\deploy\deploy-staging.ps1 `
  -ConfirmDocumentRoot `
  -SkipOwner `
  -ResumeStep6 `
  -ExistingBackupPath "/home/u220042353/backups/staging-20261017T162001Z.tar.gz" `
  -ExpectedBackupSha256 "0d7812f2cff04b213d731d7e90d1f5cf4b26a4c75d8e238d29a0db73fc4e0e40"
```

Bu mod mevcut backup dosyasını ve hashini doğrular. Yeni backup oluşturmaz.
Betik mevcut 33 public dosyanın hashini doğrular. Public dosyaları tekrar yazmaz.
Betik private arşivi çıkarır. Sonra adım 7 ile devam eder.
Başlamayan adımlar `NOT RUN` görünür. Manuel owner adımı genel sonucu `FAIL` tutar.

Staging testleri bitince hPanel Remote MySQL kaydını kaldırın.
Son kontrolden sonra staging dizininde hPanel directory password protection etkinleştirin.
