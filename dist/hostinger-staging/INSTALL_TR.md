# Chef staging kurulumu

Bu paket yalnız staging doğrulaması içindir. Üretim yayını değildir.

1. Mevcut veritabanının ve tüm site dosyalarının yedeğini alın.
2. Staging için ayrı bir alt alan adı ve ayrı bir veritabanı oluşturun.
3. Staging dizinine parola koruması ekleyin. Genel erişimi kapatın.
4. `public_html.zip` içeriğini staging belge köküne çıkarın.
5. `chef-private-setup.zip` içeriğini `public_html` dışındaki özel dizine çıkarın.
6. `database/config.example.php` dosyasını özel dizinde `config.php` olarak kopyalayın.
7. Yer tutucuları staging veritabanı ve özel medya yollarıyla değiştirin.
8. `CHEF_CONFIG_FILE` değerini özel `config.php` mutlak yoluna ayarlayın.
9. PHP için `display_errors=0` ayarını Hostinger panelinden doğrulayın.
10. HTTPS sertifikasını etkinleştirin. HTTP isteklerini HTTPS adresine yönlendirin.
11. Özel dizinlerin, günlüklerin ve feedback görsellerinin `public_html` dışında kaldığını doğrulayın.
12. CLI ile `php database/cli.php migrate` ve `php database/cli.php seed` komutlarını çalıştırın.
13. Gerekirse `php database/recover-owner.php` ile sahibi güvenli terminal girişinden oluşturun.
14. `php database/media-cli.php prepare` komutunu çalıştırın.
15. Temizliği beş dakikada bir `php database/media-cli.php cleanup` komutuyla çalıştırın.

## Zorunlu upload yürütme testi

1. Public medya dizinine zararsız `upload-test.php` dosyası koyun.
2. Dosyanın içeriğini yalnız `<?php echo 'EXECUTED';` yapın.
3. HTTPS ile bu dosyayı isteyin.
4. Yanıtın `EXECUTED` metnini üretmediğini doğrulayın.
5. Dizinin dosya listesini göstermediğini doğrulayın.
6. Test dosyasını hemen kaldırın.
7. `.phtml`, `.phar` ve `image.php.jpg` adlarının da reddedildiğini doğrulayın.

LiteSpeed davranışı ve cron çalışması staging üzerinde doğrulanmalıdır.
Task 05 entegrasyon kontrolleri staging üzerinde doğrulanmalıdır.
Guest menü, Task 07 tamamlanana kadar paketlenmiş preview verisini gösterir.
