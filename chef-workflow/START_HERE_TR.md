# Chef geliştirme sırası

Bu paket, onayladığın önizlemeyi veritabanlı sisteme dönüştürmek için hazırlanmıştır.
13 ana prompt ve iki yardımcı prompt içerir.
Promptlar İngilizcedir. Kullanıcı yanıtları Türkçedir.

## Şimdi yap

1. Önizleme paketini Alacart proje klasörüne ekle.
2. Bu paketi aynı klasöre çıkar.
3. Proje kökünde chef-workflow klasörünün bulunduğunu kontrol et.
4. Codex'te GPT-6.1 Sol seç. Görünmüyorsa mevcut GPT-6 Sol modelini kullan.
5. Aşağıdaki başlangıç metnini gönder.

```text
Read chef-workflow/prompts/00_SETUP.md.
The user approved the supplied chef-onizleme.html design.
Complete task 00 using the actual local files.
Merge existing project instructions without deleting unrelated rules.
Install only missing permitted skills.
Update PROJECT.md with evidence and the next task.
Stop after task 00.
```

Model, kurulu sürümüne veya hesabına göre farklı görünebilir.
Listede bulunmayan bir model adını zorla yapılandırma.
İlk prompt, mevcut modelleri ve araçları kaydeder.

## Sonra Gemini'ye geç

Codex'in görevi bitince Antigravity'de Gemini 3.8 Flash seç.
Aynı proje klasörünü kullan.
Şu metni gönder:

```text
Read AGENTS.md and PROJECT.md.
Execute chef-workflow/prompts/01_BROWSER_BASELINE.md.
Use local Chrome and save the required evidence.
Do not change application source.
Update PROJECT.md and return the next prompt filename.
```

Sonraki görevleri TASKS.md sırasıyla uygula.
Her görevin model tercihi kendi dosyasında yazılıdır.
Tüm promptları aynı anda yapıştırma.
Aynı dosyaları iki ajana aynı anda düzenletme.
İki istemci aynı proje klasörünü okuduğu için dosyaları tekrar göndermen gerekmez.

## Genel görev başlatma

Dosya adını sıradaki promptla değiştir:

```text
Read AGENTS.md and PROJECT.md.
Execute chef-workflow/prompts/02_DATABASE.md.
Complete its acceptance checks.
Update PROJECT.md with your actual model and evidence.
Stop at this task's handoff.
```

## Limit biterse

Mevcut ajanı durdur veya görevin bitmesini bekle.
Yeni ajana şu metni gönder:

```text
Read chef-workflow/prompts/R2_RESUME.md.
Continue the active task recorded in PROJECT.md.
```

## Araçlar

Yeni bir MCP bağlantısı gerekmiyor.
Mevcut Chrome, Context7, Motion, Semgrep ve GitHub araçları yeterli.
Eksik skill kurulumunu ilk prompt yaptırır.
Superpowers kapsam dışındadır.
Figma ve görsel üretimi bu aşamada gerekli değildir.
Hostinger MCP, yerel geliştirme için gerekli değildir.

## Dosyalar

CONTRACT.md ortak kuralları içerir.
TASKS.md sıra ve bitiş şartlarını gösterir.
docs/MODEL_ROUTING.md model seçimlerini açıklar.
docs/SKILL_ROUTING.md skill kaynaklarını ve görevlerini gösterir.
PROJECT.approved.md mevcut onayı ve ilerlemeyi içerir.
İlk ajan, bu kaydı mevcut PROJECT.md ile birleştirir.
ALL_PROMPTS.md toplu okuma içindir. Ajanlara tamamını yükleme.

Son görev, public_html.zip ve özel kurulum dosyalarını hazırlatır.
Canlı sunucuya yükleme, bu geliştirme sırasının dışındadır.
