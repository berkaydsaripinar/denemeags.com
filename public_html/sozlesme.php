<?php
// sozlesme.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Kullanıcı Sözleşmesi ve Gizlilik Politikası";
include_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0 card-theme">
                <div class="card-body p-5">
                    <h2 class="text-center text-theme-primary fw-bold mb-4">KULLANICI SÖZLEŞMESİ VE GİZLİLİK POLİTİKASI</h2>
                    <p class="text-muted text-center mb-5"><strong>Son Güncelleme Tarihi:</strong> <?php echo date('d.m.Y'); ?></p>

                    <div class="agreement-content text-dark">
                        <h4 class="text-theme-primary mt-4">1. TARAFLAR VE TANIMLAR</h4>
                        <p><strong>1.1.</strong> İşbu sözleşme, dijital eğitim materyalleri ve çevrimiçi sınav hizmetleri sağlayan <strong><?php echo escape_html(SITE_NAME); ?></strong> (bundan sonra "Platform" veya "Satıcı" olarak anılacaktır) ile <?php echo BASE_URL; ?> internet sitesine üye olan, siteyi ziyaret eden veya site içeriklerinden faydalanan gerçek veya tüzel kişi ("Üye" veya "Kullanıcı") arasında, elektronik ortamda onaylandığı andan itibaren yürürlüğe girmek üzere akdedilmiştir.</p>
                        <p><strong>1.2.</strong> Platform, Kullanıcı'ya sınav hazırlık süreçlerinde destek olmak amacıyla deneme sınavları, soru bankaları ve ilgili dijital dökümanları sağlayan çevrimiçi bir servistir.</p>

                        <h4 class="text-theme-primary mt-4">2. SÖZLEŞMENİN KONUSU VE KAPSAMI</h4>
                        <p><strong>2.1.</strong> İşbu sözleşmenin konusu, Kullanıcı'nın Platform üzerinden sunduğu tüm dijital içeriklerden (online deneme sınavları, indirilebilir PDF soru bankaları, çözüm kitapçıkları, analiz raporları vb.) faydalanma şartlarının, tarafların karşılıklı hak ve yükümlülüklerinin belirlenmesidir.</p>
                        <p><strong>2.2.</strong> Sözleşme, Kullanıcı'nın siteye üyeliği ile başlar ve üyeliğini iptal etmesi veya üyeliğinin Platform tarafından sonlandırılmasına kadar, satın alınan hizmetlerin kullanım süresi boyunca devam eder.</p>

                        <h4 class="text-theme-primary mt-4">3. ÜYELİK, ERİŞİM KODLARI VE HESAP GÜVENLİĞİ</h4>
                        <p><strong>3.1.</strong> Üyelik işlemi, Kullanıcı'nın kendisine fiziksel kitapla iletilen, e-posta yoluyla gönderilen veya doğrudan site üzerinden satın aldığı "Kayıt Kodu"nu sisteme girmesi ve gerekli kimlik/iletişim bilgilerini (Ad, Soyad, E-posta, Şifre) doğru, güncel ve eksiksiz olarak kaydetmesiyle tamamlanır.</p>
                        <p><strong>3.2.</strong> Kullanıcı, üyelik esnasında beyan ettiği bilgilerin doğruluğunu ve kendisine ait olduğunu taahhüt eder. Yanlış, eksik veya yanıltıcı bilgi verilmesinden doğacak her türlü hak kaybı ve zarardan münhasıran Kullanıcı sorumludur. Platform, bu tür durumlarda üyeliği askıya alma veya sonlandırma hakkını saklı tutar.</p>
                        <p><strong>3.3.</strong> Platform üzerindeki içeriklere erişim, sisteme tanımlı "Ürün Erişim Kodları" veya doğrudan ödeme yöntemleri ile sağlanır. Erişim kodları kişiye özeldir; Kullanıcı bu kodları veya üyelik bilgilerini (kullanıcı adı, şifre) üçüncü şahıslarla paylaşamaz, devredemez veya kiralayamaz. Hesabın yetkisiz kullanımı şüphesinde Platform, derhal müdahale etme yetkisine sahiptir.</p>

                        <h4 class="text-theme-primary mt-4">4. FİKRİ MÜLKİYET, TELİF HAKLARI VE KULLANIM LİSANSI (ÖNEMLİ)</h4>
                        <p><strong>4.1.</strong> Platform üzerinde sunulan tüm içerikler (özgün sorular, deneme kitapçıkları, çözüm videoları, PDF dosyaları, grafikler, yazılımlar ve tasarımlar) <?php echo escape_html(SITE_NAME); ?>'ye ve içerik üreticilerine aittir. Bu eserler, 5846 sayılı Fikir ve Sanat Eserleri Kanunu ve ilgili uluslararası mevzuat kapsamında korunmaktadır.</p>
                        <p><strong>4.2. DİJİTAL FİLİGRAN VE İZLENEBİLİRLİK:</strong> Kullanıcı, Platform üzerinden indirdiği PDF dökümanlarının (Soru Kitapçığı veya Çözüm Kitapçığı) kendisine özel olarak, anlık dinamik yöntemlerle oluşturulduğunu kabul eder. İndirilen her belgenin üzerine, Kullanıcı'nın <strong>Adı Soyadı, Erişim Kodu, IP Adresi ve İndirme Tarihi</strong> dijital filigran (damga) olarak, görünür veya gizli şekilde işlenmektedir. Bu işlem, korsan dağıtımın kaynağını tespit etmek amacıyla yapılmaktadır.</p>
                        <p><strong>4.3. PAYLAŞIM VE ÇOĞALTMA YASAĞI:</strong> Kullanıcı, indirdiği ve kendi kişisel bilgileriyle damgalanmış olan dökümanları, sadece kendi bireysel çalışması için kullanabilir. Bu dökümanların dijital veya fiziksel kopyalarını <strong>üçüncü şahıslarla paylaşması, fotokopi yoluyla çoğaltması, ücretli veya ücretsiz olarak satması, WhatsApp/Telegram grupları dahil olmak üzere herhangi bir internet ortamında veya sosyal medyada yayınlaması</strong> kesinlikle yasaktır.</p>
                        <p><strong>4.4.</strong> İhlalin tespiti halinde; Kullanıcı'nın üyeliği herhangi bir ihtara gerek kalmaksızın derhal iptal edilir. Platform, ihlale konu olan içeriğin telif hakları kapsamında uğradığı ve uğrayacağı tüm maddi/manevi zararların tazmini için Kullanıcı hakkında yasal işlem başlatma hakkını saklı tutar.</p>

                        <h4 class="text-theme-primary mt-4">5. HİZMETİN KULLANIMI VE SINAV KURALLARI</h4>
                        <p><strong>5.1.</strong> Kullanıcı, Platform üzerindeki deneme sınavlarına belirtilen tarih ve saat aralıklarında katılabilir. Teknik aksaklıklar, kullanıcının internet bağlantısı sorunları veya cihaz kaynaklı problemlerden Platform sorumlu tutulamaz.</p>
                        <p><strong>5.2.</strong> Sınav sonuçları, detaylı analizler ve Türkiye geneli sıralamalar, Platform yönetimi tarafından belirlenen takvime göre açıklanır. Sıralama sonuçları, sınavın aktif olduğu süre boyunca veya sonrasında, diğer kullanıcıların katılım durumuna göre dinamik olarak değişiklik gösterebilir.</p>
                        <p><strong>5.3.</strong> Kullanıcı, sınav esnasında dürüstlük ilkelerine bağlı kalacağını; kopya çekme, sistemi manipüle etme, soruları sınav süresi bitmeden dışarı sızdırma gibi girişimlerde bulunmayacağını taahhüt eder. Bu tür eylemlerin tespiti halinde ilgili sınav geçersiz sayılır ve üyelik iptal edilebilir.</p>

                        <h4 class="text-theme-primary mt-4">6. ÖDEME, TESLİMAT VE CAYMA HAKKI İSTİSNASI</h4>
                        <p><strong>6.1.</strong> Platform üzerindeki ücretli içeriklerin satışı, güvenli ödeme altyapısı sağlayan <strong>Shopier</strong> veya benzeri yetkili aracı kuruluşlar üzerinden gerçekleştirilir. Ödeme güvenliği, ilgili aracı kuruluşun sorumluluğundadır.</p>
                        <p><strong>6.2. CAYMA HAKKI İSTİSNASI:</strong> 27.11.2014 tarihli Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesinin (ğ) bendi uyarınca; "Elektronik ortamda anında ifa edilen hizmetler veya tüketiciye anında teslim edilen gayrimaddi mallar" (PDF indirme, dijital kod, online sınav erişimi vb.) cayma hakkının istisnaları arasındadır.</p>
                        <p><strong>6.3.</strong> Bu nedenle, Kullanıcı satın aldığı erişim kodunu sisteme tanımladığı, aktive ettiği veya herhangi bir dijital içeriği (PDF) indirdiği/görüntülediği andan itibaren <strong>hizmet ifa edilmiş sayılır ve hiçbir surette ücret iadesi yapılmaz.</strong> Kullanıcı, bu durumu bilerek satın alma işlemini gerçekleştirdiğini kabul eder.</p>

                        <h4 class="text-theme-primary mt-4">7. GİZLİLİK VE KİŞİSEL VERİLER</h4>
                        <p><strong>7.1.</strong> <?php echo escape_html(SITE_NAME); ?>, Kullanıcı'nın kişisel verilerini (Ad, Soyad, E-posta, IP adresi ve Sınav Performans Verileri) 6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında, veri sorumlusu sıfatıyla saklar ve işler.</p>
                        <p><strong>7.2.</strong> Kullanıcı verileri; üyelik işlemlerinin yürütülmesi, sınav sonuçlarının hesaplanması, kişiselleştirilmiş analizlerin sunulması, güvenlik (filigran oluşturma) ve yasal yükümlülüklerin yerine getirilmesi amacıyla kullanılır.</p>

                        <h4 class="text-theme-primary mt-4">8. SÖZLEŞME DEĞİŞİKLİKLERİ VE YÜRÜRLÜK</h4>
                        <p><strong>8.1.</strong> Platform, işbu sözleşme koşullarını dilediği zaman değiştirme hakkını saklı tutar. Güncel sözleşme metni site üzerinde yayınlandığı tarihte yürürlüğe girer. Kullanıcı, siteyi kullanmaya devam ederek bu değişiklikleri kabul etmiş sayılır.</p>
                        
                        <h4 class="text-theme-primary mt-4">9. YETKİLİ MAHKEME</h4>
                        <p>İşbu sözleşmenin uygulanmasından doğabilecek uyuşmazlıkların çözümünde İstanbul Mahkemeleri ve İcra Daireleri yetkilidir.</p>
                    </div>
                    
                    <div class="text-center mt-5">
                        <button onclick="window.close()" class="btn btn-theme-primary btn-lg px-5">Pencereyi Kapat</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/templates/footer.php'; ?>