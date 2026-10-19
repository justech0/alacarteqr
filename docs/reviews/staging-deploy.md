# Staging deployment review

- Tarih: 2026-10-17
- Hedef: `https://taslak.bilincreklam.com`
- Sonuç: **FAIL**
- Kapsam: Adım 6 arşiv düzeltmesi ve özel dizinde extraction testi.
- Güvenlik: Parola, özel anahtar, yapılandırma içeriği ve oturum verisi kaydedilmedi.

| Adım | Kontrol | Sonuç | Kanıt özeti |
|---:|---|---|---|
| 1 | SSH anahtarı | PASS | Mevcut anahtar ile bağlantı doğrulandı. |
| 2 | PHP ve uzantılar | PASS | Önceki gerçek çalıştırmanın geçerli sonucu korundu. |
| 3 | Belge kökü | PASS | Alan adı içindeki belge kökü doğrulandı. |
| 4 | Veritabanı adı | PASS | Yasaklı eski ad bulunmadı. |
| 5 | Uzak yedek | PASS | Mevcut backup dosyası ve SHA-256 değeri tekrar doğrulandı. Yeni backup oluşturulmadı. |
| 6 | Arşiv aktarımı | FAIL | Düzeltilmiş arşivlerin özel test extraction işlemleri geçti. Mevcut public kök beklenen 33 dosyayı içermediği için aktivasyon çalıştırılmadı. |
| 7 | `.htaccess` kontrolü | NOT RUN | Aktivasyon başlamadı. |
| 8 | Özel yapılandırma | NOT RUN | Aktivasyon başlamadı. |
| 9 | `CHEF_CONFIG_FILE` | NOT RUN | Aktivasyon başlamadı. |
| 10 | Migration ve hazırlık | NOT RUN | Aktivasyon başlamadı. |
| 11 | Sahip kurtarma | NOT RUN | Aktivasyon başlamadı. |
| 12 | Medya cleanup cron | NOT RUN | Aktivasyon başlamadı. |
| 13 | HTTPS kontrolleri | NOT RUN | Aktivasyon başlamadı. |
| 14 | Readonly kontrolü | NOT RUN | Aktivasyon başlamadı. |
| 15 | Raporlama | PASS | Review ve PROJECT kaydı gizli değer olmadan güncellendi. |

## Doğrulanmış eski hata

Asıl başarısız komut:

```sh
unzip -oq "/home/u220042353/.chef-staging-deploy/public_html.zip" -d "/home/u220042353/domains/taslak.bilincreklam.com/public_html"
```

- Çıkış kodu: `1`.
- Temizlenmiş stderr: `warning: <archive> appears to use backslashes as path separators`.
- `set -e` private arşiv komutunu başlatmadı.
- Eski public ZIP girdilerinin 27 tanesi ters eğik çizgi kullandı.
- Eski private ZIP girdilerinin dokuz tanesi ters eğik çizgi kullandı.
- Uzak Info-ZIP `unzip 6.00`, dosyaları açtı ve uyarı nedeniyle `1` döndürdü.

## Düzeltme

- `deploy/normalize-staging-archives.ps1` iki arşivi aynı dosya içerikleriyle yeniden oluşturdu.
- ZIP girdileri yalnız `/` kullanır.
- Preflight mutlak yolu, `..` bölümünü, ters eğik çizgiyi ve normalize tekrarını reddeder.
- `Invoke-NativeCapture` stdout ve stderr akışlarını ayrı tutar.
- Adım 6 hatası komut etiketini, çıkış kodunu ve sınırlı temizlenmiş stderr metnini kaydeder.
- Başlamayan adımlar `NOT RUN` kalır.
- Resume modu mevcut backup dosyasını doğrular. Yeni backup oluşturmaz.
- Resume modu public dosyaların hash eşleşmesini zorunlu kılar. Eşleşme yoksa private aktivasyonu başlamaz.

## Arşiv kanıtı

| Arşiv | Dosya | Ters eğik çizgi | Traversal | Tekrar | SHA-256 |
|---|---:|---:|---:|---:|---|
| `public_html.zip` | 33 | 0 | 0 | 0 | `577a2318af0eb3df472905a199a6d7e9b3ed38e1014d2eabfc8b5e3e88a96fa3` |
| `chef-private-setup.zip` | 9 | 0 | 0 | 0 | `bbc2769eb7ef914d2a5351beabb2f60888a9e12a55b0e0217471aedfb0260c61` |

- Yerel ve uzak arşiv hashleri eşleşti.
- Eski ve yeni arşivlerin normalize yol ve dosya içerik özetleri eşleşti.
- Yerel ZIP bütünlük ve yol kontrolleri geçti.
- PowerShell parse kontrolü geçti.
- `deploy-staging.ps1 -LocalValidationOnly` çıkış kodu `0` oldu.
- Yerel harness, resume akışını ve `NOT RUN` durumlarını doğruladı.
- Yerel harness, stdout ile stderr ayrımını doğruladı.

## Uzak özel extraction testi

- Test kökü `$HOME/.chef-staging-deploy/verify-577a2318af0e-bbc2769eb7ef` oldu.
- Public extraction çıkış kodu `0` oldu.
- Private extraction çıkış kodu `0` oldu.
- Public dosya sayısı `33` oldu.
- Private dosya sayısı `9` oldu.
- Symlink sayısı `0` oldu.
- `media/.htaccess` ve `hvk/admin/.htaccess` bulundu.
- Test dizini kaldırıldı. Kaldırma kontrolü geçti.
- Test, public uygulama dizinine veya gerçek private dizine dosya yazmadı.

## Mevcut uzak durum ve engel

- Backup yolu: `/home/u220042353/backups/staging-20261017T162001Z.tar.gz`.
- Backup SHA-256: `0d7812f2cff04b213d731d7e90d1f5cf4b26a4c75d8e238d29a0db73fc4e0e40`.
- Backup dosyası değişmedi.
- Gerçek private dizindeki dosya sayısı `0` oldu.
- Güncel public belge kökündeki dosya sayısı `0` oldu.
- Önceki tanıda kaydedilen 33 public dosya güncel uzak durumda yoktur.
- Bu doğrulama public belge köküne yazmadı.
- Public durum farkı çözülmeden resume işlemi güvenli değildir.
- Genel deployment durumu `FAIL` kalır.
- Task 12 durumu değişmez.

## Güvenli devam komutu

Public dosya durumu doğrulandıktan sonra şu komutu kullanın:

```powershell
pwsh.exe -NoProfile -File .\deploy\deploy-staging.ps1 `
  -ConfirmDocumentRoot `
  -SkipOwner `
  -ResumeStep6 `
  -ExistingBackupPath "/home/u220042353/backups/staging-20261017T162001Z.tar.gz" `
  -ExpectedBackupSha256 "0d7812f2cff04b213d731d7e90d1f5cf4b26a4c75d8e238d29a0db73fc4e0e40"
```

Bu komut güncel public kök 33 dosyayla eşleşmezse adım 6 içinde durur.
Staging testleri bitince hPanel Remote MySQL kaydı kaldırılmalıdır.
